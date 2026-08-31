<?php

namespace App\Http\Controllers;

use App\Models\ServiceProviderApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceProviderApplicationController extends Controller
{
    public function store(Request $request, AppNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'host_id' => ['required', 'integer', 'exists:users,id'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', Rule::in(array_keys(ServiceProviderApplication::SERVICE_OPTIONS))],
            'application_message' => ['required', 'string', 'min:10', 'max:2000'],
            'application_images' => ['nullable', 'array', 'max:6'],
            'application_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $host = User::query()
            ->whereKey($validated['host_id'])
            ->where('role', 'host')
            ->where('is_active', true)
            ->whereHas('units', fn ($units) => $units->whereIn('category', ['car', 'condo']))
            ->firstOrFail();
        abort_if($host->id === $request->user()->id, 422, 'You cannot apply to yourself.');

        $existing = ServiceProviderApplication::query()
            ->where('applicant_user_id', $request->user()->id)
            ->where('host_id', $host->id)
            ->first();
        if ($existing?->status === 'accepted') {
            throw ValidationException::withMessages(['host_id' => 'This host has already approved you as a service provider.']);
        }
        $existingImages = $existing?->application_images ?? [];
        if (count($existingImages) + count($validated['application_images'] ?? []) > 6) {
            throw ValidationException::withMessages([
                'application_images' => 'You can keep up to 6 application images. Submit fewer images.',
            ]);
        }

        $application = ServiceProviderApplication::query()->updateOrCreate(
            ['applicant_user_id' => $request->user()->id, 'host_id' => $host->id],
            [
                'services' => collect($validated['services'])->unique()->values()->all(),
                'status' => 'pending',
                'application_message' => trim($validated['application_message']),
                'review_note' => null,
                'reviewed_at' => null,
            ],
        );
        $storedPaths = [];
        try {
            $newImages = collect($validated['application_images'] ?? [])->map(function ($image) use ($application, &$storedPaths) {
                $path = $image->store('service-provider-applications/'.$application->id, 'local');
                $storedPaths[] = $path;

                return ['path' => $path, 'name' => $image->getClientOriginalName()];
            })->all();
            if ($newImages !== []) {
                $application->update(['application_images' => [...$existingImages, ...$newImages]]);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        $notifications->send(
            $host,
            'service_provider_application',
            'New service provider application',
            $request->user()->name.' applied to help with '.$application->serviceLabels().'.',
            route('service-work.index'),
            'provider-application:'.$application->id.':pending:'.$application->updated_at->timestamp,
        );

        return back()->with('status', 'Your service-provider application was sent to '.$host->name.'.');
    }

    public function review(Request $request, ServiceProviderApplication $application, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->is_admin || $application->host_id === $request->user()->id, 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($application, $validated) {
            $locked = ServiceProviderApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->status === 'pending', 422, 'This application has already been reviewed.');
            $locked->update([
                'status' => $validated['status'],
                'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
                'reviewed_at' => now(),
            ]);
        });
        $application->refresh()->loadMissing('applicant', 'host');
        $notifications->send(
            $application->applicant,
            'service_provider_application_status',
            $application->status === 'accepted' ? 'Service provider application approved' : 'Service provider application declined',
            $application->host->name.' '.$application->status.' your application for '.$application->serviceLabels().'.',
            route('service-work.index'),
            'provider-application:'.$application->id.':'.$application->status,
        );

        return back()->with('status', $application->status === 'accepted'
            ? $application->applicant->name.' can now be assigned to applicable booking expenses.'
            : 'The service-provider application was declined.');
    }

    public function image(Request $request, ServiceProviderApplication $application, int $image): StreamedResponse
    {
        abort_unless(
            $request->user()->is_admin
                || $application->applicant_user_id === $request->user()->id
                || $application->host_id === $request->user()->id,
            403,
        );
        $file = ($application->application_images ?? [])[$image] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->response($file['path'], $file['name'] ?? null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
