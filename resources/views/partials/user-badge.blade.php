<div class="header-user-tools">
@include('partials.listing-search')
@include('partials.page-guide')
@include('partials.notification-center')
<details class="profile-menu">
    <summary class="user-badge" aria-label="Open profile and appearance settings">
        @include('partials.avatar', ['avatarUser' => auth()->user(), 'avatarClass' => 'header-user-avatar', 'avatarAlt' => auth()->user()->name])
        <div class="user-badge-copy">
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ ucfirst(auth()->user()->role) }}{{ auth()->user()->is_admin ? ' · Administrator' : '' }}</small>
        </div>
        @unless (auth()->user()->hasCompleteProfile())
            @php
                $verificationReminder = auth()->user()->isHost()
                    ? 'Verification required: complete your profile before your listings appear to clients and you can receive inquiries.'
                    : 'Verification required: complete your profile before you can inquire or request a booking.';
            @endphp
            <span class="profile-verification-warning" tabindex="0" role="img" aria-label="{{ $verificationReminder }}" data-tooltip="{{ $verificationReminder }}">!</span>
        @endunless
        <span class="profile-chevron" aria-hidden="true">⌄</span>
    </summary>

    <div class="profile-popover">
        <div class="profile-heading">
            <span class="profile-label">Profile</span>
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ auth()->user()->email }}</small>
            <small>{{ ucfirst(auth()->user()->role) }} account{{ auth()->user()->is_admin ? ' · Administrator' : '' }}</small>
            <small>{{ auth()->user()->hasCompleteProfile() ? '✓ Identity profile complete' : '⚠ Identity profile required' }}</small>
        </div>

        <a class="profile-settings-link" href="{{ route('profile.edit') }}">Edit verification profile →</a>

        <div class="profile-divider"></div>

        <section class="appearance-settings" aria-labelledby="appearance-heading">
            <div class="appearance-heading">
                <div>
                    <span class="profile-label">Settings</span>
                    <h2 id="appearance-heading">Appearance</h2>
                </div>
                <span class="appearance-icon" aria-hidden="true">✦</span>
            </div>

            <label class="appearance-switch" for="dark-mode-toggle">
                <span>
                    <strong>Dark mode</strong>
                    <small>Reduce brightness across your workspace</small>
                </span>
                <input id="dark-mode-toggle" type="checkbox" role="switch">
                <span class="switch-track" aria-hidden="true"><span></span></span>
            </label>

            <fieldset class="theme-picker">
                <legend>Color theme</legend>
                <div class="theme-options">
                    <button class="theme-option theme-option-system" type="button" data-theme-option="system" aria-pressed="true">
                        <span class="theme-swatch theme-swatch-system" aria-hidden="true"></span>
                        <span>System default</span>
                        <span class="theme-check" aria-hidden="true">✓</span>
                    </button>
                    <button class="theme-option" type="button" data-theme-option="forest" aria-pressed="false">
                        <span class="theme-swatch theme-swatch-forest" aria-hidden="true"></span>
                        <span>Forest</span>
                        <span class="theme-check" aria-hidden="true">✓</span>
                    </button>
                    <button class="theme-option" type="button" data-theme-option="ocean" aria-pressed="false">
                        <span class="theme-swatch theme-swatch-ocean" aria-hidden="true"></span>
                        <span>Ocean</span>
                        <span class="theme-check" aria-hidden="true">✓</span>
                    </button>
                    <button class="theme-option" type="button" data-theme-option="violet" aria-pressed="false">
                        <span class="theme-swatch theme-swatch-violet" aria-hidden="true"></span>
                        <span>Violet</span>
                        <span class="theme-check" aria-hidden="true">✓</span>
                    </button>
                    <button class="theme-option" type="button" data-theme-option="sunset" aria-pressed="false">
                        <span class="theme-swatch theme-swatch-sunset" aria-hidden="true"></span>
                        <span>Sunset</span>
                        <span class="theme-check" aria-hidden="true">✓</span>
                    </button>
                </div>
            </fieldset>
            <p class="sr-only" data-theme-status role="status" aria-live="polite"></p>
        </section>
    </div>
</details>
</div>
