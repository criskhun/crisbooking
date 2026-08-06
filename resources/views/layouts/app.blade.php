<!DOCTYPE html>
<html lang="en" data-color-mode="light" data-color-theme="forest">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173c34">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <script>
        try {
            document.documentElement.dataset.colorMode = localStorage.getItem('mybooking-color-mode') || 'light';
            document.documentElement.dataset.colorTheme = localStorage.getItem('mybooking-color-theme') || 'forest';
        } catch (error) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/maps.js') }}" defer></script>
    @if (config('services.google.maps_api_key'))
        <script>
            window.initMyBookingGoogleMaps = () => window.dispatchEvent(new Event('mybooking:maps-ready'));
            window.gm_authFailure = () => {
                window.myBookingMapsAuthFailed = true;
                window.dispatchEvent(new Event('mybooking:maps-auth-failure'));
            };
        </script>
        <script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_api_key')) }}&loading=async&libraries=geometry&callback=initMyBookingGoogleMaps"></script>
    @endif
</head>
<body class="@yield('body-class', 'site-body')">
    @yield('content')
</body>
</html>
