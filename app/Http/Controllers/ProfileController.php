<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\User;
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
        return view('profiles.edit', ['profileUser' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.today()->subYears(18)->format('Y-m-d')],
            'nationality' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'bio' => ['required', 'string', 'min:20', 'max:1000'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'government_id_type' => ['required', Rule::in(['national_id', 'drivers_license', 'passport', 'sss', 'umid', 'postal_id', 'voters_id', 'other'])],
            'government_id_number' => ['required', 'string', 'max:120'],
            'government_id' => [Rule::requiredIf(! $user->government_id_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
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
