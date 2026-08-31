@extends('layouts.app')

@section('title', 'Choose a new password — Davao Rent Zone')
@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a class="brand brand-light" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                <span class="brand-name">{{ $branding->site_name }}</span>
            </a>
            <div class="auth-panel-copy">
                <span class="eyebrow eyebrow-light">Secure recovery</span>
                <h1>Choose a strong<br>new password.</h1>
                <p>Use at least eight characters with both letters and numbers.</p>
            </div>
            <p class="auth-panel-note">After resetting, you can log in immediately.</p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="mobile-brand brand" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
                    <span class="brand-name">{{ $branding->site_name }}</span>
                </a>
                <div class="form-heading">
                    <span class="form-kicker">Account recovery</span>
                    <h2>Set a new password</h2>
                    <p>Confirm your account email and enter your new password.</p>
                </div>

                <form method="POST" action="{{ route('password.store') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="field-group">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus class="@error('email') field-error @enderror">
                        @error('email')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-group">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 letters and numbers" class="@error('password') field-error @enderror">
                        @error('password')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-group">
                        <label for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Repeat your new password">
                    </div>

                    <button class="button button-primary button-full" type="submit">Reset password <x-fa-icon name="arrow-right" /></button>
                </form>
            </div>
        </section>
    </main>
@endsection
