@extends('layouts.app')

@section('title', 'System settings — '.$branding->site_name)
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')

        <main class="dashboard-main settings-page">
            <header class="dashboard-header">
                <div>
                    <span class="form-kicker">Administrator controls</span>
                    <h1>System settings</h1>
                </div>
                @include('partials.user-badge')
            </header>

            @if (session('status'))
                <div class="flash-message settings-alert" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="settings-layout" data-branding-form>
                @csrf
                @method('PUT')

                <div class="settings-form-stack">
                    <section class="settings-card" aria-labelledby="identity-heading">
                        <div class="settings-card-heading">
                            <span class="settings-step">01</span>
                            <div><h2 id="identity-heading">Brand identity</h2><p>The name and message people see throughout the platform.</p></div>
                        </div>

                        <div class="settings-fields two-column">
                            <div class="field-group">
                                <label for="site_name">System name</label>
                                <input id="site_name" name="site_name" type="text" value="{{ old('site_name', $settings->site_name) }}" maxlength="80" required data-preview-name>
                                @error('site_name')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="field-group">
                                <label for="short_name">Short name</label>
                                <input id="short_name" name="short_name" type="text" value="{{ old('short_name', $settings->short_name) }}" maxlength="30" required>
                                <small>Used when space is limited, such as an installed app.</small>
                                @error('short_name')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="field-group settings-field-wide">
                                <label for="tagline">Tagline</label>
                                <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $settings->tagline) }}" maxlength="160" placeholder="Rentals and services in one place" data-preview-tagline>
                                @error('tagline')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="field-group settings-field-wide">
                                <label for="description">System description</label>
                                <textarea id="description" name="description" rows="3" maxlength="500" placeholder="A short description for install screens and search previews.">{{ old('description', $settings->description) }}</textarea>
                                @error('description')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="settings-card" aria-labelledby="assets-heading">
                        <div class="settings-card-heading">
                            <span class="settings-step">02</span>
                            <div><h2 id="assets-heading">Logo and browser icon</h2><p>Upload brand assets without replacing files on the server.</p></div>
                        </div>

                        <div class="asset-upload-grid">
                            <div class="asset-upload-card">
                                <div class="asset-preview logo-preview"><img src="{{ $settings->logo_url }}" alt="Current system logo" data-logo-preview></div>
                                <div class="asset-upload-copy"><strong>Main logo</strong><small>PNG, JPG, or WebP · up to 4 MB. A transparent, wide logo works best.</small></div>
                                <label class="button button-ghost button-small" for="logo">Choose logo</label>
                                <input class="sr-only" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-image-input data-preview-target="[data-logo-preview]">
                                @if($settings->logo_path)
                                    <label class="asset-reset"><input type="checkbox" name="remove_logo" value="1"><span>Restore default logo</span></label>
                                @endif
                                @error('logo')<p class="error-text">{{ $message }}</p>@enderror
                            </div>

                            <div class="asset-upload-card">
                                <div class="asset-preview favicon-preview"><img src="{{ $settings->favicon_url }}" alt="Current browser icon" data-favicon-preview></div>
                                <div class="asset-upload-copy"><strong>Browser and app icon</strong><small>Square PNG, JPG, or WebP · up to 1 MB. At least 192 × 192 is recommended.</small></div>
                                <label class="button button-ghost button-small" for="favicon">Choose icon</label>
                                <input class="sr-only" id="favicon" name="favicon" type="file" accept="image/png,image/jpeg,image/webp" data-image-input data-preview-target="[data-favicon-preview]">
                                @if($settings->favicon_path)
                                    <label class="asset-reset"><input type="checkbox" name="remove_favicon" value="1"><span>Restore default icon</span></label>
                                @endif
                                @error('favicon')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="settings-card" aria-labelledby="colors-heading">
                        <div class="settings-card-heading">
                            <span class="settings-step">03</span>
                            <div><h2 id="colors-heading">Brand colors</h2><p>These colors style primary actions, navigation, and highlights.</p></div>
                        </div>

                        <div class="color-settings-grid">
                            @foreach([
                                'primary_color' => ['Primary', 'Buttons and navigation'],
                                'secondary_color' => ['Secondary', 'Hover and emphasis'],
                                'accent_color' => ['Accent', 'Badges and highlights'],
                            ] as $field => [$label, $help])
                                <label class="color-setting" for="{{ $field }}">
                                    <input id="{{ $field }}" name="{{ $field }}" type="color" value="{{ old($field, $settings->{$field}) }}" data-color-preview="{{ $field }}">
                                    <span><strong>{{ $label }}</strong><small>{{ $help }}</small><code data-color-code="{{ $field }}">{{ old($field, $settings->{$field}) }}</code></span>
                                </label>
                                @error($field)<p class="error-text">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    </section>

                    <section class="settings-card" aria-labelledby="contact-heading">
                        <div class="settings-card-heading">
                            <span class="settings-step">04</span>
                            <div><h2 id="contact-heading">Support contact</h2><p>Keep the official contact details ready for customer-facing pages and messages.</p></div>
                        </div>

                        <div class="settings-fields two-column">
                            <div class="field-group">
                                <label for="support_email">Support email</label>
                                <input id="support_email" name="support_email" type="email" value="{{ old('support_email', $settings->support_email) }}" maxlength="255" placeholder="support@example.com">
                                @error('support_email')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="field-group">
                                <label for="support_phone">Support phone</label>
                                <input id="support_phone" name="support_phone" type="text" value="{{ old('support_phone', $settings->support_phone) }}" maxlength="40" placeholder="+63 900 000 0000">
                                @error('support_phone')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="settings-preview-panel">
                    <span class="eyebrow">Live preview</span>
                    <div class="brand-preview-card" data-brand-preview style="--preview-primary: {{ old('primary_color', $settings->primary_color) }}; --preview-secondary: {{ old('secondary_color', $settings->secondary_color) }}; --preview-accent: {{ old('accent_color', $settings->accent_color) }};">
                        <div class="brand-preview-nav">
                            <span><img src="{{ $settings->logo_url }}" alt="" data-logo-preview></span>
                            <strong data-preview-name-output>{{ old('site_name', $settings->site_name) }}</strong>
                        </div>
                        <div class="brand-preview-body">
                            <small>Your platform</small>
                            <h3 data-preview-name-output>{{ old('site_name', $settings->site_name) }}</h3>
                            <p data-preview-tagline-output>{{ old('tagline', $settings->tagline) ?: 'Your tagline will appear here.' }}</p>
                            <button type="button">Primary action <span>→</span></button>
                            <div><i></i><span>Accent color</span></div>
                        </div>
                    </div>
                    <p class="settings-preview-note">Changes become visible throughout the system after you save.</p>
                    @if($settings->exists && $settings->updated_at)
                        <small class="settings-updated">Last saved {{ $settings->updated_at->diffForHumans() }}@if($settings->updatedBy) by {{ $settings->updatedBy->name }}@endif.</small>
                    @endif
                    <button class="button button-primary settings-save" type="submit">Save system settings</button>
                </aside>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-branding-form]');
            if (!form) return;
            const preview = form.querySelector('[data-brand-preview]');
            const syncText = (inputSelector, outputSelector, fallback = '') => {
                const input = form.querySelector(inputSelector);
                const update = () => form.querySelectorAll(outputSelector).forEach((output) => output.textContent = input.value.trim() || fallback);
                input?.addEventListener('input', update);
            };
            syncText('[data-preview-name]', '[data-preview-name-output]', 'Your system');
            syncText('[data-preview-tagline]', '[data-preview-tagline-output]', 'Your tagline will appear here.');
            form.querySelectorAll('[data-color-preview]').forEach((input) => input.addEventListener('input', () => {
                const property = {primary_color: '--preview-primary', secondary_color: '--preview-secondary', accent_color: '--preview-accent'}[input.dataset.colorPreview];
                preview.style.setProperty(property, input.value);
                form.querySelector(`[data-color-code="${input.dataset.colorPreview}"]`).textContent = input.value;
            }));
            form.querySelectorAll('[data-image-input]').forEach((input) => input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                form.querySelectorAll(input.dataset.previewTarget).forEach((image) => image.src = url);
            }));
        });
    </script>
@endsection
