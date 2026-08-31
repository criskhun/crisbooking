<!DOCTYPE html>
<html lang="en" data-color-mode="light" data-color-theme="system" data-system-theme-color="{{ $branding->primary_color }}" style="--system-primary: {{ $branding->primary_color }}; --system-secondary: {{ $branding->secondary_color }}; --system-accent: {{ $branding->accent_color }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $branding->primary_color }}">
    <meta name="application-name" content="{{ $branding->site_name }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->short_name }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ str_replace('Davao Rent Zone', $branding->site_name, trim($__env->yieldContent('title', $branding->site_name))) }}</title>
    <link rel="manifest" href="{{ route('app.manifest') }}">
    <link rel="icon" href="{{ $branding->favicon_url }}">
    <link rel="apple-touch-icon" href="{{ $branding->favicon_path ? $branding->favicon_url : asset('apple-touch-icon.png') }}">
    <script>
        try {
            document.documentElement.dataset.colorMode = localStorage.getItem('mybooking-color-mode') || 'light';
            document.documentElement.dataset.colorTheme = localStorage.getItem('mybooking-color-theme') || 'system';
        } catch (error) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-shell-v5.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-menu-v6.css') }}?v={{ filemtime(public_path('css/mobile-menu-v6.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-form-v7.css') }}">
    <link rel="stylesheet" href="{{ asset('css/profile-controls-v8.css') }}">
    <link rel="stylesheet" href="{{ asset('css/address-combobox-v9.css') }}">
    <script src="{{ asset('js/address-combobox-v9.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    <script src="{{ asset('js/maps.js') }}?v={{ filemtime(public_path('js/maps.js')) }}" defer></script>
    <script src="{{ asset('js/mobile-shell-v5.js') }}" defer></script>
    <script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}" data-service-worker="{{ asset('sw.js') }}" defer></script>
    @if (config('services.google.maps_api_key'))
        <script>
            window.initDavaoRentZoneGoogleMaps = () => window.dispatchEvent(new Event('mybooking:maps-ready'));
            window.gm_authFailure = () => {
                window.myBookingMapsAuthFailed = true;
                window.dispatchEvent(new Event('mybooking:maps-auth-failure'));
            };
        </script>
        <script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_api_key')) }}&loading=async&libraries=geometry,places&callback=initDavaoRentZoneGoogleMaps"></script>
    @endif
</head>
<body class="@yield('body-class', 'site-body') is-booting">
    @yield('content')

    <div class="global-loading-overlay" data-global-loader aria-hidden="true">
        <div class="global-loading-card" role="status" aria-live="polite" aria-atomic="true">
            <span class="global-loading-brand" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
            <span class="global-loading-spinner" aria-hidden="true"></span>
            <strong data-global-loader-title>Loading your workspace</strong>
            <small data-global-loader-message>Please wait while the page gets ready.</small>
        </div>
    </div>

    <aside class="pwa-install-banner" data-pwa-install-banner hidden aria-labelledby="pwa-install-title">
        <div class="pwa-install-icon" aria-hidden="true">
            <img src="{{ $branding->favicon_path ? $branding->favicon_url : asset('icons/icon-192.png') }}" alt="">
        </div>
        <div class="pwa-install-copy">
            <strong id="pwa-install-title">Install {{ $branding->site_name }}</strong>
            <p data-pwa-install-message>Use it like an app from your home screen or desktop.</p>
        </div>
        <button class="button button-primary button-small" type="button" data-pwa-install-action>Install app</button>
        <button class="pwa-install-dismiss" type="button" data-pwa-install-dismiss aria-label="Dismiss install suggestion">×</button>
    </aside>

    <div class="connectivity-status" data-connectivity-status hidden role="status" aria-live="polite"></div>
    @auth<div class="notification-toast-stack" data-notification-toasts aria-live="polite" aria-atomic="false"></div>@endauth
</body>
</html>
