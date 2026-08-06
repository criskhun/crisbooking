@extends('layouts.app')

@section('title', 'Verify your email — MyBooking')
@section('body-class', 'auth-body')

@section('content')
    <main class="verification-shell">
        <section class="verification-card">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-mark"><svg viewBox="0 0 32 32"><path d="M8 5v4M24 5v4M6 11h20M8 7h16a3 3 0 0 1 3 3v15a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V10a3 3 0 0 1 3-3Z"/><path d="m11 19 3 3 7-8"/></svg></span>
                <span>MyBooking</span>
            </a>

            <span class="verification-icon">✉</span>
            <span class="form-kicker">One last step</span>
            <h1>Verify your email address</h1>
            <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open that email and select the verification button to activate your account.</p>

            @if (session('status'))
                <div class="flash-message verification-flash" role="status">{{ session('status') }}</div>
            @endif

            <div class="verification-actions">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Resend verification email</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="verification-logout" type="submit">Use a different account</button>
                </form>
            </div>
            <small>Check your spam folder if the message does not arrive within a few minutes.</small>
        </section>
    </main>
@endsection
