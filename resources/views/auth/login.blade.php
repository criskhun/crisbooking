@extends('layouts.app')

@section('title', 'Log in — Davao Rent Zone')
@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a class="brand brand-light" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                <span class="brand-name">{{ $branding->site_name }}</span>
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
                    <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                    <span class="brand-name">{{ $branding->site_name }}</span>
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
                        <x-fa-icon name="g" />
                        Google
                    </a>
                    <a class="social-button facebook-button" href="{{ route('auth.facebook.redirect') }}">
                        <x-fa-icon name="f" />
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

                    <button class="button button-primary button-full" type="submit">Log in <x-fa-icon name="arrow-right" /></button>
                </form>

                <p class="form-footer">New to {{ $branding->site_name }}? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </section>
    </main>
@endsection
