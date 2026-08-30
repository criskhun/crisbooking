<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\ProfileImage;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\ProfileOptions;
use App\Services\SystemBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $request->user()->load('profileImages');

        return view('profiles.edit', [
            'profileUser' => $request->user(),
            'countries' => ProfileOptions::countries(),
            'nationalities' => ProfileOptions::nationalities(),
        ]);
    }

    public function storeImage(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profileImages()->count() >= 20) {
            return back()->withErrors(['profile_image' => 'You can keep up to 20 profile photos. Delete one of your old photos before uploading another.']);
        }

        $validated = $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $path = $validated['profile_image']->store('profile-images/'.$user->id, 'public');

        try {
            DB::transaction(function () use ($user, $path) {
                $user->profileImages()->create(['path' => $path]);
                $user->update(['profile_image_path' => $path]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return back()->with('status', 'Your new profile photo is now used throughout '.app(SystemBranding::class)->settings()->site_name.'.');
    }

    public function selectImage(Request $request, ProfileImage $profileImage): RedirectResponse
    {
        abort_unless($profileImage->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('public')->exists($profileImage->path), 404);

        $request->user()->update(['profile_image_path' => $profileImage->path]);

        return back()->with('status', 'Your selected profile photo is now active.');
    }

    public function destroyImage(Request $request, ProfileImage $profileImage): RedirectResponse
    {
        abort_unless($profileImage->user_id === $request->user()->id, 403);
        $user = $request->user();
        $wasCurrent = $user->profile_image_path === $profileImage->path;
        $replacement = $wasCurrent
            ? $user->profileImages()->whereKeyNot($profileImage->id)->first()
            : null;

        DB::transaction(function () use ($user, $profileImage, $wasCurrent, $replacement) {
            if ($wasCurrent) {
                $user->update(['profile_image_path' => $replacement?->path]);
            }
            $profileImage->delete();
        });
        Storage::disk('public')->delete($profileImage->path);

        return back()->with('status', $wasCurrent
            ? 'The photo was deleted and your next saved photo is now active.'
            : 'The old profile photo was deleted.');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $normalizedPhone = PhoneNumber::normalize($request->string('phone')->toString());
        if ($normalizedPhone) {
            $request->merge(['phone' => $normalizedPhone]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.today()->subYears(17)->format('Y-m-d')],
            'nationality' => ['required', 'string', Rule::in(ProfileOptions::nationalities())],
            'address' => ['required', 'string', 'max:500'],
            'country' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'barangay' => ['required', 'string', 'max:120'],
            'bio' => ['required', 'string', 'min:20', 'max:1000'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'government_id_type' => ['required', Rule::in(['national_id', 'drivers_license', 'passport', 'sss', 'umid', 'postal_id', 'voters_id', 'other'])],
            'government_id_number' => ['required', 'string', 'max:120'],
            'government_id' => [Rule::requiredIf(! $user->government_id_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'date_of_birth.before_or_equal' => 'You must be at least 17 years old to complete verification.',
            'phone.regex' => 'Enter a valid mobile number, including its country code.',
        ]);

        $oldPath = $user->government_id_path;
        $newPath = $request->file('government_id')?->store('identity-documents/'.$user->id, 'local');

        $user->update([
            ...collect($validated)->except('government_id')->all(),
            'government_id_path' => $newPath ?: $oldPath,
            'profile_completed_at' => now(),
        ]);

        if ($newPath && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('profile.edit')->with('status', 'Your verification profile is complete and ready to share with booking partners.');
    }

    public function show(Request $request, User $profile): View
    {
        abort_unless($this->canView($request->user(), $profile), 403);

        $profile->load(['reviewsReceived' => fn ($query) => $query->with(['reviewer:id,name,profile_image_path,google_avatar,facebook_avatar', 'booking.unit:id,name', 'affiliatePartnership:id'])->latest()]);
        $reviewSummaries = $profile->reviewsReceived->groupBy('reviewee_context')->map(fn ($reviews) => [
            'count' => $reviews->count(),
            'average' => round((float) $reviews->avg('rating'), 1),
        ]);

        return view('profiles.show', compact('reviewSummaries') + ['profileUser' => $profile]);
    }

    public function document(Request $request, User $profile): StreamedResponse
    {
        abort_unless($this->canView($request->user(), $profile), 403);
        abort_unless($profile->government_id_path && Storage::disk('local')->exists($profile->government_id_path), 404);

        return Storage::disk('local')->response($profile->government_id_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function documentPreview(Request $request, User $profile): View
    {
        abort_unless($this->canView($request->user(), $profile), 403);
        abort_unless($profile->government_id_path && Storage::disk('local')->exists($profile->government_id_path), 404);

        $backUrl = $request->user()->is($profile) ? route('profile.edit') : route('profiles.show', $profile);
        $backLabel = 'Back to profile';
        $application = $profile->hostApplication;

        if ($request->string('from')->toString() === 'host-application'
            && $application
            && $application->id === $request->integer('application')) {
            $backUrl = $request->user()->is_admin
                ? route('admin.host-applications.show', $application)
                : route('host-applications.show');
            $backLabel = 'Back to host application';
        }

        return view('profiles.document', [
            'profileUser' => $profile,
            'documentUrl' => route('profiles.document', $profile),
            'isPdf' => str_ends_with(strtolower($profile->government_id_path), '.pdf'),
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
        ]);
    }

    private function canView(User $viewer, User $profile): bool
    {
        if ($viewer->is_admin || $viewer->is($profile)) {
            return true;
        }

        $hasInquiry = Inquiry::query()->where(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $viewer->id)->where('host_id', $profile->id);
        })->orWhere(function ($query) use ($viewer, $profile) {
            $query->where('host_id', $viewer->id)->where('client_id', $profile->id);
        })->exists();

        if ($hasInquiry) {
            return true;
        }

        if (AffiliatePartnership::query()->where(function ($query) use ($viewer, $profile) {
            $query->where('marketer_id', $viewer->id)->where('host_id', $profile->id);
        })->orWhere(function ($query) use ($viewer, $profile) {
            $query->where('host_id', $viewer->id)->where('marketer_id', $profile->id);
        })->exists()) {
            return true;
        }

        return Booking::query()->where(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $viewer->id)->whereHas('unit', fn ($units) => $units->where('host_id', $profile->id));
        })->orWhere(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $profile->id)->whereHas('unit', fn ($units) => $units->where('host_id', $viewer->id));
        })->exists();
    }
}
