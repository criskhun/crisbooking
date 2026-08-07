<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\ProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profiles.edit', [
            'profileUser' => $request->user(),
            'countries' => ProfileOptions::countries(),
            'nationalities' => ProfileOptions::nationalities(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $normalizedPhone = PhoneNumber::normalize($request->string('phone')->toString());
        if ($normalizedPhone) $request->merge(['phone' => $normalizedPhone]);

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

        if ($newPath && $oldPath) Storage::disk('local')->delete($oldPath);
        return redirect()->route('profile.edit')->with('status', 'Your verification profile is complete and ready to share with booking partners.');
    }

    public function show(Request $request, User $profile): View
    {
        abort_unless($this->canView($request->user(), $profile), 403);

        return view('profiles.show', ['profileUser' => $profile]);
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

    private function canView(User $viewer, User $profile): bool
    {
        if ($viewer->is_admin || $viewer->is($profile)) return true;

        $hasInquiry = Inquiry::query()->where(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $viewer->id)->where('host_id', $profile->id);
        })->orWhere(function ($query) use ($viewer, $profile) {
            $query->where('host_id', $viewer->id)->where('client_id', $profile->id);
        })->exists();

        if ($hasInquiry) return true;

        return Booking::query()->where(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $viewer->id)->whereHas('unit', fn ($units) => $units->where('host_id', $profile->id));
        })->orWhere(function ($query) use ($viewer, $profile) {
            $query->where('client_id', $profile->id)->whereHas('unit', fn ($units) => $units->where('host_id', $viewer->id));
        })->exists();
    }
}
