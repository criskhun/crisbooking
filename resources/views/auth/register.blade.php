@extends('layouts.app')

@section('title', 'Create account — Davao Rent Zone')
@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <section class="auth-brand-panel auth-register-panel">
            <a class="brand brand-light" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                <span class="brand-name">{{ $branding->site_name }}</span>
            </a>
            <div class="auth-panel-copy">
                <span class="eyebrow eyebrow-light">Start organized</span>
                <h1>One account for<br>your whole business.</h1>
                <p>Join as a client to book available listings, or as a host to register units and services.</p>
            </div>
            <p class="auth-panel-note">Simple setup. Secure access. Mobile ready.</p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="mobile-brand brand" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                    <span class="brand-name">{{ $branding->site_name }}</span>
                </a>
                <div class="form-heading">
                    <span class="form-kicker">Get started</span>
                    <h2>Create your account</h2>
                    <p>Enter your details to set up your booking workspace.</p>
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

                <div class="auth-divider"><span>or register with email</span></div>

                <form method="POST" action="{{ route('register') }}" class="auth-form">
                    @csrf
                    <fieldset class="role-selector">
                        <legend>I want to</legend>
                        <div class="role-options">
                            <label><input type="radio" name="role" value="client" @checked(old('role', 'client') === 'client')><span><strong>Book as a client</strong><small>Find units and services</small></span></label>
                            <label><input type="radio" name="role" value="host" @checked(old('role') === 'host')><span><strong>List as a host</strong><small>Register and manage bookings</small></span></label>
                        </div>
                        @error('role')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="field-group">
                        <label for="name">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus placeholder="Your full name" class="@error('name') field-error @enderror">
                        @error('name')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-group">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com" class="@error('email') field-error @enderror">
                        @error('email')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 characters" class="@error('password') field-error @enderror">
                        </div>
                        <div class="field-group">
                            <label for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Repeat password">
                        </div>
                    </div>
                    @error('password')<p class="error-text error-after-row">{{ $message }}</p>@enderror
                    <p class="password-hint">Use at least 8 characters, including a letter and number.</p>

                    <button class="button button-primary button-full" type="submit">Create account <span aria-hidden="true">→</span></button>
                </form>

                <p class="form-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
            </div>
        </section>
    </main>
@endsection
