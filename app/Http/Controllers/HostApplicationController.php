<?php

namespace App\Http\Controllers;

use App\Models\HostApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HostApplicationController extends Controller
{
    public function show(Request $request): View
    {
        $application = $request->user()->hostApplication()->with(['reviewer', 'histories.actor'])->first();

        return view('host-applications.show', compact('application'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $application = $user->hostApplication()->first();

        if (! $user->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors([
                'profile' => 'Complete your verification profile before applying to become a host.',
            ]);
        }

        if ($user->isHost() || ($application && ! $application->canBeEditedByApplicant())) {
            return back()->withErrors([
                'application' => 'This application is already being reviewed and cannot be changed right now.',
            ]);
        }

        $validated = $request->validate([
            'account_type' => ['required', Rule::in(['individual', 'business'])],
            'business_name' => ['nullable', 'required_if:account_type,business', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'required_if:account_type,business', 'string', 'max:255'],
            'business_document' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('account_type') === 'business' && ! $application?->business_document_path),
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'hosting_experience' => ['required', Rule::in(['none', 'less_than_one_year', 'one_to_three_years', 'more_than_three_years'])],
            'motivation' => ['required', 'string', 'min:30', 'max:2000'],
            'payout_method' => ['required', Rule::in(['bank_transfer', 'e_wallet'])],
            'payout_provider' => ['required', 'string', 'max:120'],
            'payout_account_name' => ['required', 'string', 'max:255'],
            'payout_account_number' => [
                'nullable',
                Rule::requiredIf(fn () => ! $application?->payout_account_number),
                'string',
                'min:5',
                'max:120',
            ],
            'authority_confirmed' => ['accepted'],
            'safety_confirmed' => ['accepted'],
            'terms_accepted' => ['accepted'],
            'privacy_consented' => ['accepted'],
        ]);

        $oldBusinessDocument = $application?->business_document_path;
        $newBusinessDocument = $request->file('business_document')?->store('host-application-documents/'.$user->id, 'local');
        $removeBusinessDocument = $validated['account_type'] === 'individual';

        try {
            DB::transaction(function () use ($user, $application, $validated, $newBusinessDocument): void {
                $application ??= new HostApplication(['user_id' => $user->id]);
                $fromStatus = $application->exists ? $application->status : null;
                $confirmationTime = now();

                $application->fill([
                    'status' => HostApplication::STATUS_SUBMITTED,
                    'account_type' => $validated['account_type'],
                    'business_name' => $validated['account_type'] === 'business' ? $validated['business_name'] : null,
                    'business_registration_number' => $validated['account_type'] === 'business' ? $validated['business_registration_number'] : null,
                    'business_document_path' => $validated['account_type'] === 'business'
                        ? ($newBusinessDocument ?: $application->business_document_path)
                        : null,
                    'hosting_experience' => $validated['hosting_experience'],
                    'motivation' => $validated['motivation'],
                    'payout_method' => $validated['payout_method'],
                    'payout_provider' => $validated['payout_provider'],
                    'payout_account_name' => $validated['payout_account_name'],
                    'payout_account_number' => $validated['payout_account_number'] ?: $application->payout_account_number,
                    'authority_confirmed_at' => $confirmationTime,
                    'safety_confirmed_at' => $confirmationTime,
                    'terms_accepted_at' => $confirmationTime,
                    'privacy_consented_at' => $confirmationTime,
                    'submitted_at' => $confirmationTime,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ])->save();

                $application->histories()->create([
                    'actor_id' => $user->id,
                    'from_status' => $fromStatus,
                    'to_status' => HostApplication::STATUS_SUBMITTED,
                    'note' => $fromStatus ? 'Application updated and resubmitted.' : 'Application submitted.',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newBusinessDocument) {
                Storage::disk('local')->delete($newBusinessDocument);
            }

            throw $exception;
        }

        if ($oldBusinessDocument && ($newBusinessDocument || $removeBusinessDocument)) {
            Storage::disk('local')->delete($oldBusinessDocument);
        }

        return redirect()->route('host-applications.show')->with('status', 'Your host application has been submitted for review.');
    }

    public function businessDocument(Request $request, HostApplication $hostApplication): StreamedResponse
    {
        abort_unless($request->user()->is_admin || $request->user()->is($hostApplication->user), 403);
        abort_unless($hostApplication->business_document_path && Storage::disk('local')->exists($hostApplication->business_document_path), 404);

        return Storage::disk('local')->download(
            $hostApplication->business_document_path,
            'business-document.'.pathinfo($hostApplication->business_document_path, PATHINFO_EXTENSION),
        );
    }
}
