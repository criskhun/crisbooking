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
                        <x-fa-icon name="g" />
                        Google
                    </a>
                    <a class="social-button facebook-button" href="{{ route('auth.facebook.redirect') }}">
                        <x-fa-icon name="f" />
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

                    <button class="button button-primary button-full" type="submit">Create account <x-fa-icon name="arrow-right" /></button>
                </form>

                <p class="form-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
            </div>
        </section>
    </main>
@endsection
