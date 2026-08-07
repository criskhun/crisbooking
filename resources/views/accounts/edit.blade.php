@extends('layouts.app')

@section('title', 'Manage '.$account->name.' — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <a class="back-link" href="{{ route('accounts.index') }}">← Back to users</a>
                    <h1>Manage account</h1>
                </div>
                @include('partials.user-badge')
            </header>

            <section class="account-edit-card">
                <div class="edit-account-heading">
                    @php
                        $avatar = $account->google_avatar ?: $account->facebook_avatar;
                    @endphp
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="" referrerpolicy="no-referrer">
                    @else
                        <span>{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                    @endif
                    <div>
                        <span class="eyebrow">Account #{{ $account->id }}</span>
                        <h2>{{ $account->name }}</h2>
                        <p>{{ $account->email }}</p>
                    </div>
                </div>

                @error('account')
                    <div class="oauth-error" role="alert">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('accounts.update', $account) }}" class="account-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="field-group">
                        <label for="name">Display name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $account->name) }}" required maxlength="255" class="@error('name') field-error @enderror">
                        @error('name')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <fieldset class="role-selector management-role-selector">
                        <legend>Account role</legend>
                        <div class="role-options">
                            <label><input type="radio" name="role" value="client" @checked(old('role', $account->role) === 'client')><span><strong>Client</strong><small>Can browse and request bookings</small></span></label>
                            <label><input type="radio" name="role" value="host" @checked(old('role', $account->role) === 'host')><span><strong>Host</strong><small>Can register units and manage requests</small></span></label>
                        </div>
                    </fieldset>

                    <div class="management-options">
                        <label class="management-option">
                            <input type="hidden" name="is_admin" value="0">
                            <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $account->is_admin))>
                            <span><strong>Administrator access</strong><small>Can view, update, suspend, and delete other users.</small></span>
                        </label>
                        <label class="management-option">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))>
                            <span><strong>Active account</strong><small>Can sign in and use the Davao Rent Zone dashboard.</small></span>
                        </label>
                    </div>

                    @if (auth()->user()->is($account))
                        <p class="self-protection-note">You can update your name, but you cannot demote or suspend the account you are currently using.</p>
                    @endif

                    <div class="edit-form-actions">
                        <a class="button button-ghost" href="{{ route('accounts.index') }}">Cancel</a>
                        <button class="button button-primary" type="submit">Save changes</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
