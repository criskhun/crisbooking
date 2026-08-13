@extends('layouts.app')

@section('title', 'Recover your account — Davao Rent Zone')
@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a class="brand brand-light" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
                <span class="brand-name">Davao Rent Zone</span>
            </a>
            <div class="auth-panel-copy">
                <span class="eyebrow eyebrow-light">Account recovery</span>
                <h1>Get safely back<br>to your bookings.</h1>
                <p>We will send a secure password-reset link to the email address used for your account.</p>
            </div>
            <p class="auth-panel-note">Reset links expire after 60 minutes.</p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="mobile-brand brand" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
                    <span class="brand-name">Davao Rent Zone</span>
                </a>
                <div class="form-heading">
                    <span class="form-kicker">Forgot email or password?</span>
                    <h2>Reset your password</h2>
                    <p>Your account username is your email address. Enter it below and we will email your reset link.</p>
                </div>

                @if (session('status'))
                    <div class="flash-message" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                    @csrf
                    <div class="field-group">
                        <label for="email">Account email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="you@example.com" class="@error('email') field-error @enderror">
                        @error('email')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <button class="button button-primary button-full" type="submit">Email reset link <span aria-hidden="true">→</span></button>
                </form>

                <p class="form-footer">Remembered your details? <a href="{{ route('login') }}">Return to login</a></p>
                <p class="account-recovery-note">If you no longer remember or can access your account email, contact <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
            </div>
        </section>
    </main>
@endsection
