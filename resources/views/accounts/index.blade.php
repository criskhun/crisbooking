@extends('layouts.app')

@section('title', 'Registered accounts — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <span class="form-kicker">User management</span>
                    <h1>Registered accounts</h1>
                </div>
                @include('partials.user-badge')
            </header>

            <section class="accounts-section">
                @if (session('status'))
                    <div class="flash-message account-alert" role="status">{{ session('status') }}</div>
                @endif

                @error('account')
                    <div class="oauth-error account-alert" role="alert">{{ $message }}</div>
                @enderror

                <div class="accounts-summary">
                    <div class="account-summary-stat">
                        <span class="eyebrow">All users</span>
                        <h2>{{ $accounts->count() }} {{ Str::plural('account', $accounts->count()) }}</h2>
                    </div>
                    <div class="account-summary-stat">
                        <span class="eyebrow">Clients</span>
                        <h2>{{ $clientCount }}</h2>
                    </div>
                    <div class="account-summary-stat">
                        <span class="eyebrow">Hosts</span>
                        <h2>{{ $hostCount }}</h2>
                    </div>
                    <p>{{ $activeCount }} active · {{ $adminCount }} {{ Str::plural('administrator', $adminCount) }}. Manage roles and account status for everyone registered with Davao Rent Zone.</p>
                </div>

                <div class="accounts-table-wrap">
                    <table class="accounts-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Email</th>
                                <th>Sign-up method</th>
                                <th>Date registered</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($accounts as $account)
                                @php
                                    $avatar = $account->google_avatar ?: $account->facebook_avatar;
                                    $method = $account->google_id ? 'Google' : ($account->facebook_id ? 'Facebook' : 'Email');
                                @endphp
                                <tr>
                                    <td>
                                        <div class="account-identity">
                                            @if ($avatar)
                                                <img src="{{ $avatar }}" alt="" referrerpolicy="no-referrer">
                                            @else
                                                <span>{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                                            @endif
                                            <div>
                                                <strong>{{ $account->name }}</strong>
                                                <small>{{ $account->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span @class(['role-badge', 'role-host' => $account->role === 'host'])>{{ ucfirst($account->role) }}</span>@if($account->is_admin)<small class="admin-marker">Admin</small>@endif</td>
                                    <td><span @class(['account-status', 'status-suspended' => ! $account->is_active])><i></i>{{ $account->is_active ? 'Active' : 'Suspended' }}</span></td>
                                    <td><span @class(['verification-badge', 'verification-pending' => ! $account->hasVerifiedEmail()])>{{ $account->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</span></td>
                                    <td><span class="method-badge method-{{ strtolower($method) }}">{{ $method }}</span></td>
                                    <td>
                                        <span class="registered-date">{{ $account->created_at->format('M j, Y') }}</span>
                                        <small class="registered-time">{{ $account->created_at->format('g:i A') }}</small>
                                    </td>
                                    <td>
                                        <div class="account-actions">
                                            <a href="{{ route('accounts.edit', $account) }}">Edit</a>
                                            @unless (auth()->user()->is($account))
                                                <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Permanently delete {{ addslashes($account->name) }}? This cannot be undone.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit">Delete</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="accounts-empty">No accounts have registered yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="accounts-privacy-note">Only administrators can open this page. Passwords, session data, and social login IDs are never displayed.</p>
            </section>
        </main>
    </div>
@endsection
