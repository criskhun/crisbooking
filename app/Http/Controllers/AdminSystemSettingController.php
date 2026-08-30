<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SystemBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSystemSettingController extends Controller
{
    public function edit(SystemBranding $branding): View
    {
        return view('admin.settings.edit', ['settings' => $branding->settings()]);
    }

    public function update(Request $request, SystemBranding $branding): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'short_name' => ['required', 'string', 'max:30'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email:rfc', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', Rule::dimensions()->maxWidth(2400)->maxHeight(2400), 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'favicon' => ['nullable', 'image', Rule::dimensions()->maxWidth(1024)->maxHeight(1024), 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [
            'primary_color.regex' => 'Choose a valid six-digit primary color.',
            'secondary_color.regex' => 'Choose a valid six-digit secondary color.',
            'accent_color.regex' => 'Choose a valid six-digit accent color.',
        ]);

        $settings = SystemSetting::query()->first() ?? SystemSetting::create([
            ...SystemSetting::defaults(),
            'updated_by' => $request->user()->id,
        ]);

        $oldLogoPath = $settings->logo_path;
        $oldFaviconPath = $settings->favicon_path;

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $validated['logo_path'] = null;
        }

        if ($request->hasFile('favicon')) {
            $validated['favicon_path'] = $request->file('favicon')->store('branding', 'public');
        } elseif ($request->boolean('remove_favicon')) {
            $validated['favicon_path'] = null;
        }

        unset($validated['logo'], $validated['favicon'], $validated['remove_logo'], $validated['remove_favicon']);

        $settings->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        if ($oldLogoPath && $oldLogoPath !== $settings->logo_path) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        if ($oldFaviconPath && $oldFaviconPath !== $settings->favicon_path) {
            Storage::disk('public')->delete($oldFaviconPath);
        }

        $branding->forget();

        return back()->with('status', 'System branding and contact details were updated.');
    }
}
