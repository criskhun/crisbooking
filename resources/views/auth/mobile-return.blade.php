<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#173f35">
    <title>Return to Davao Rent Zone</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; color: #18382f; background: linear-gradient(145deg, #edf5ef, #f9f7ed); }
        main { width: min(430px, 100%); padding: 32px; border: 1px solid rgba(23, 63, 53, .12); border-radius: 24px; background: #fff; box-shadow: 0 24px 70px rgba(23, 63, 53, .15); text-align: center; }
        .mark { display: grid; place-items: center; width: 58px; height: 58px; margin: 0 auto 20px; border-radius: 18px; color: #fff; background: #173f35; font-size: 28px; }
        h1 { margin: 0 0 10px; font-size: 1.55rem; }
        p { margin: 0 0 24px; color: #63716c; line-height: 1.6; }
        a { display: flex; align-items: center; justify-content: center; min-height: 50px; padding: 0 22px; border-radius: 14px; color: #fff; background: #173f35; font-weight: 800; text-decoration: none; }
        small { display: block; margin-top: 16px; color: #7b8682; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">↗</div>
        <h1>{{ $hasError ? 'Return to the app' : 'Sign-in completed' }}</h1>
        <p>{{ $hasError ? 'Open Davao Rent Zone to see the sign-in message and try again.' : 'Your account is ready. Return to Davao Rent Zone to finish signing in.' }}</p>
        <a href="{{ $intentUrl }}" id="open-app">Open Davao Rent Zone</a>
        <small>If the app does not open automatically, tap the button above.</small>
    </main>
    <script>
        window.setTimeout(() => {
            window.location.href = @json($appUrl);
        }, 250);
    </script>
</body>
</html>
