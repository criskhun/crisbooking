<?php

namespace App\Http\Controllers;

use App\Models\InquiryMessage;
use App\Models\UnitImage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('accounts.index', [
            'accounts' => User::query()->latest()->get(),
            'adminCount' => User::query()->where('is_admin', true)->count(),
            'activeCount' => User::query()->where('is_active', true)->count(),
            'hostCount' => User::query()->where('role', 'host')->count(),
            'clientCount' => User::query()->where('role', 'client')->count(),
        ]);
    }

    public function edit(User $account): View
    {
        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_admin' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'role' => ['sometimes', Rule::in(['client', 'host'])],
        ]);

        $isAdmin = (bool) $validated['is_admin'];
        $isActive = (bool) $validated['is_active'];

        if ($request->user()->is($account) && (! $isAdmin || ! $isActive)) {
            return back()->withErrors([
                'account' => 'You cannot remove your own administrator access or suspend your own account.',
            ]);
        }

        if ($this->wouldRemoveLastActiveAdmin($account, $isAdmin, $isActive)) {
            return back()->withErrors([
                'account' => 'At least one active administrator must remain.',
            ]);
        }

        $account->update([
            'name' => $validated['name'],
            'is_admin' => $isAdmin,
            'is_active' => $isActive,
            'role' => $validated['role'] ?? $account->role,
        ]);

        return redirect()->route('accounts.index')->with('status', "{$account->name}'s account was updated.");
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        if ($request->user()->is($account)) {
            return back()->withErrors([
                'account' => 'You cannot delete the account you are currently using.',
            ]);
        }

        if ($this->wouldRemoveLastActiveAdmin($account, false, false)) {
            return back()->withErrors([
                'account' => 'The last active administrator cannot be deleted.',
            ]);
        }

        $name = $account->name;
        $photoPaths = UnitImage::query()->whereHas('unit', fn ($query) => $query->where('host_id', $account->id))->pluck('path')
            ->merge($account->units()->whereNotNull('photo_path')->pluck('photo_path'))->unique();
        $profileImagePaths = $account->profileImages()->pluck('path');
        $wifiQrPaths = $account->units()->whereNotNull('wifi_qr_path')->pluck('wifi_qr_path');
        $governmentIdPath = $account->government_id_path;
        $hostApplicationDocumentPath = $account->hostApplication?->business_document_path;
        $hostApplicationIdentityPaths = array_filter([
            $account->hostApplication?->face_selfie_path,
            $account->hostApplication?->id_selfie_path,
        ]);
        $inquiryAttachmentPaths = InquiryMessage::query()->whereNotNull('attachment_path')->whereHas('inquiry', fn ($query) => $query->where('client_id', $account->id)->orWhere('host_id', $account->id))->pluck('attachment_path');
        $account->delete();
        Storage::disk('public')->delete($photoPaths->all());
        Storage::disk('public')->delete($profileImagePaths->all());
        Storage::disk('local')->delete($wifiQrPaths->all());
        if ($governmentIdPath) {
            Storage::disk('local')->delete($governmentIdPath);
        }
        if ($hostApplicationDocumentPath) {
            Storage::disk('local')->delete($hostApplicationDocumentPath);
        }
        Storage::disk('local')->delete($hostApplicationIdentityPaths);
        Storage::disk('local')->delete($inquiryAttachmentPaths->all());

        return redirect()->route('accounts.index')->with('status', "{$name}'s account was deleted.");
    }

    private function wouldRemoveLastActiveAdmin(User $account, bool $willBeAdmin, bool $willBeActive): bool
    {
        if (! $account->is_admin || ! $account->is_active || ($willBeAdmin && $willBeActive)) {
            return false;
        }

        return User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->count() <= 1;
    }
}
