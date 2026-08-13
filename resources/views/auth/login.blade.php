@extends('layouts.app')

@section('title', 'Log in — Davao Rent Zone')
@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a class="brand brand-light" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
                <span class="brand-name">Davao Rent Zone</span>
            </a>
            <div class="auth-panel-copy">
                <span class="eyebrow eyebrow-light">Welcome back</span>
                <h1>Keep every booking<br>under control.</h1>
                <p>Your calendar, customers, and sales will live together in one organized workspace.</p>
            </div>
            <p class="auth-panel-note">Built for busy rental and transport businesses.</p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="mobile-brand brand" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
                    <span class="brand-name">Davao Rent Zone</span>
                </a>
                <div class="form-heading">
                    <span class="form-kicker">Account access</span>
                    <h2>Log in to your account</h2>
                    <p>Enter your details to open your booking dashboard.</p>
                </div>

                @error('google')
                    <div class="oauth-error" role="alert">{{ $message }}</div>
                @enderror

                @error('facebook')
                    <div class="oauth-error" role="alert">{{ $message }}</div>
                @enderror

                <div class="social-buttons">
                    <a class="social-button google-button" href="{{ route('auth.google.redirect') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.5-.2-2.2H12v4h5.4a4.7 4.7 0 0 1-2 3v2.6h3.3c1.9-1.8 2.9-4.4 2.9-7.4Z"/><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.7-2.4L15.4 17c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3v2.7A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.4 13.9A6 6 0 0 1 6.1 12c0-.7.1-1.3.3-1.9V7.4H3A10 10 0 0 0 2 12c0 1.7.4 3.2 1 4.6l3.4-2.7Z"/><path fill="#EA4335" d="M12 6c1.5 0 2.8.5 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2a10 10 0 0 0-9 5.4l3.4 2.7C7.2 7.8 9.4 6 12 6Z"/></svg>
                        Google
                    </a>
                    <a class="social-button facebook-button" href="{{ route('auth.facebook.redirect') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M24 12.1C24 5.4 18.6 0 12 0S0 5.4 0 12.1C0 18.1 4.4 23 10.1 24v-8.4h-3v-3.5h3V9.4c0-3 1.8-4.7 4.6-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9V24C19.6 23 24 18.1 24 12.1Z"/></svg>
                        Facebook
                    </a>
                </div>

                <div class="auth-divider"><span>or use your email</span></div>

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf
                    <div class="field-group">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="you@example.com" class="@error('email') field-error @enderror">
                        @error('email')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-group">
                        <div class="field-label-row">
                            <label for="password">Password</label>
                            <a href="{{ route('password.request') }}">Forgot email or password?</a>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" class="@error('password') field-error @enderror">
                        @error('password')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <label class="check-row">
                        <input type="checkbox" name="remember" value="1">
                        <span>Keep me logged in</span>
                    </label>

                    <button class="button button-primary button-full" type="submit">Log in <span aria-hidden="true">→</span></button>
                </form>

                <p class="form-footer">New to Davao Rent Zone? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </section>
    </main>
@endsection
