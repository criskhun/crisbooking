@extends('layouts.app')

@section('title', 'Accounting ledger — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Cashflow control</span><h1>Accounting ledger</h1></div>@include('partials.user-badge')</header>
            <section class="accounting-page">
                @if(session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="oauth-error account-alert" role="alert"><strong>The accounting change could not be saved.</strong><br>{{ $errors->first() }}</div>@endif

                <section class="accounting-hero">
                    <div><span class="eyebrow">One source of truth</span><h2>Know where every peso came from and where it went.</h2><p>Collections, deposits, service payments, unit costs, and financing payments appear here after you select the cash, bank, card, or e-wallet account used.</p></div>
                    <a class="button button-primary" href="#financial-accounts"><x-fa-icon name="plus" /> Add an account</a>
                </section>

                <div class="accounting-summary-grid">
                    <article><span class="accounting-summary-icon balance"><x-fa-icon name="wallet" /></span><div><small>Tracked account balance</small><strong>₱{{ number_format($report['summary']['account_balance'], 2) }}</strong><p>Opening balances plus all assigned cash movements</p></div></article>
                    <article><span class="accounting-summary-icon income"><x-fa-icon name="arrow-down" /></span><div><small>Money in{{ $month ? ' · '.$month->format('M Y') : '' }}</small><strong>₱{{ number_format($report['summary']['money_in'], 2) }}</strong><p>Collections and deposits received</p></div></article>
                    <article><span class="accounting-summary-icon expense"><x-fa-icon name="arrow-up" /></span><div><small>Money out{{ $month ? ' · '.$month->format('M Y') : '' }}</small><strong>₱{{ number_format($report['summary']['money_out'], 2) }}</strong><p>Refunds, services, costs, and installments</p></div></article>
                    <article><span class="accounting-summary-icon net"><x-fa-icon name="scale-balanced" /></span><div><small>Net cash flow</small><strong class="{{ $report['summary']['net_cash_flow'] < 0 ? 'negative' : 'positive' }}">₱{{ number_format($report['summary']['net_cash_flow'], 2) }}</strong><p>{{ $report['summary']['transaction_count'] }} filtered {{ Str::plural('transaction', $report['summary']['transaction_count']) }}</p></div></article>
                </div>

                @if($report['summary']['unassigned_count'])
                    <section class="accounting-unassigned-alert" role="status"><span><x-fa-icon name="triangle-exclamation" /></span><div><strong>{{ $report['summary']['unassigned_count'] }} historical {{ Str::plural('transaction', $report['summary']['unassigned_count']) }} need an account</strong><p>Assign the correct account in the ledger below so its balance and cashflow become complete. Unassigned total: ₱{{ number_format($report['summary']['unassigned_amount'], 2) }}.</p></div><a href="#accounting-ledger">Review now →</a></section>
                @endif

                <section class="financial-accounts-panel" id="financial-accounts">
                    <div class="accounting-section-heading"><div><span class="eyebrow">Cash locations</span><h2>Your financial accounts</h2><p>Add multiple accounts, then choose the exact source or destination whenever money moves.</p></div><strong>{{ $report['accounts']->where('is_active', true)->count() }} active</strong></div>
                    <div class="financial-account-layout">
                        <form class="financial-account-form" method="POST" action="{{ route('accounting.accounts.store') }}">
                            @csrf
                            <div class="financial-account-form-heading"><span><x-fa-icon name="landmark" /></span><div><strong>Register an account</strong><small>Only the last four digits are stored—never enter a full account number.</small></div></div>
                            <label><span>Account name</span><input name="name" maxlength="100" value="{{ old('name') }}" required placeholder="Business cash, BDO checking, GCash…"></label>
                            <label><span>Account type</span><select name="type" required>@foreach($accountTypeOptions as $value => $label)<option value="{{ $value }}" @selected(old('type', 'cash') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label><span>Bank or provider <i>Optional</i></span><input name="institution_name" maxlength="120" value="{{ old('institution_name') }}" placeholder="BDO, BPI, GCash, Maya…"></label>
                            <label><span>Last four digits <i>Optional</i></span><input name="last_four" inputmode="numeric" minlength="2" maxlength="4" value="{{ old('last_four') }}" placeholder="1234"></label>
                            <label><span>Opening balance</span><div class="money-input"><span>₱</span><input name="opening_balance" inputmode="decimal" value="{{ old('opening_balance', '0.00') }}" required data-accounting-input></div><small>Balance before the first transaction tracked here.</small></label>
                            <label><span>Balance start date <i>Optional</i></span><input name="opened_on" type="date" value="{{ old('opened_on', today()->toDateString()) }}"></label>
                            <input type="hidden" name="is_active" value="1">
                            <button class="button button-primary" type="submit">Add financial account</button>
                        </form>

                        <div class="financial-account-list">
                            @forelse($report['account_summaries'] as $accountSummary)
                                @php
                                    $account = $accountSummary['account'];
                                @endphp
                                <details class="financial-account-card {{ $account->is_active ? '' : 'inactive' }}">
                                    <summary>
                                        <span class="financial-account-card-icon"><x-fa-icon :name="match($account->type) { 'cash' => 'money-bill-wave', 'bank' => 'building-columns', 'e_wallet' => 'mobile-screen-button', 'credit_card' => 'credit-card', default => 'wallet' }" /></span>
                                        <span><small>{{ $account->typeLabel() }}{{ $account->institution_name ? ' · '.$account->institution_name : '' }}</small><strong>{{ $account->displayLabel() }}</strong><em>{{ $account->is_active ? 'Available for transactions' : 'Archived · ledger only' }}</em></span>
                                        <span><small>Current balance</small><strong class="{{ $accountSummary['balance'] < 0 ? 'negative' : '' }}">₱{{ number_format($accountSummary['balance'], 2) }}</strong><em>₱{{ number_format($accountSummary['money_in'], 2) }} in · ₱{{ number_format($accountSummary['money_out'], 2) }} out</em></span>
                                        <b>Manage</b>
                                    </summary>
                                    <form method="POST" action="{{ route('accounting.accounts.update', $account) }}">@csrf @method('PATCH')
                                        <label><span>Account name</span><input name="name" maxlength="100" value="{{ $account->name }}" required></label>
                                        <label><span>Type</span><select name="type" required>@foreach($accountTypeOptions as $value => $label)<option value="{{ $value }}" @selected($account->type === $value)>{{ $label }}</option>@endforeach</select></label>
                                        <label><span>Bank or provider</span><input name="institution_name" maxlength="120" value="{{ $account->institution_name }}"></label>
                                        <label><span>Last four digits</span><input name="last_four" inputmode="numeric" minlength="2" maxlength="4" value="{{ $account->last_four }}"></label>
                                        <label><span>Opening balance</span><div class="money-input"><span>₱</span><input name="opening_balance" inputmode="decimal" value="{{ number_format((float) $account->opening_balance, 2, '.', ',') }}" required data-accounting-input></div></label>
                                        <label><span>Balance start date</span><input name="opened_on" type="date" value="{{ $account->opened_on?->toDateString() }}"></label>
                                        <label class="availability-toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)><span><strong>Active account</strong><small>Inactive accounts stay in history but cannot be selected for new payments.</small></span></label>
                                        <button class="button button-ghost button-small" type="submit">Save account</button>
                                    </form>
                                </details>
                            @empty
                                <div class="accounting-empty"><span><x-fa-icon name="wallet" /></span><strong>No financial accounts yet.</strong><p>Register where you keep or spend business money before recording another collection or payment.</p></div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="accounting-ledger-panel" id="accounting-ledger">
                    <div class="accounting-section-heading"><div><span class="eyebrow">Transaction history</span><h2>Accounting ledger</h2><p>Every row links back to the booking, unit, cost, or obligation that created it.</p></div><strong>{{ $report['movements']->count() }} shown</strong></div>
                    <form class="accounting-filter-form" method="GET" action="{{ route('accounting.index') }}">
                        <label><span>Account</span><select name="account"><option value="">All accounts, including unassigned</option>@foreach($report['accounts'] as $account)<option value="{{ $account->id }}" @selected($selectedAccount?->id === $account->id)>{{ $account->displayLabel() }}{{ $account->is_active ? '' : ' (archived)' }}</option>@endforeach</select></label>
                        <label><span>Month</span><input name="month" type="month" value="{{ $month?->format('Y-m') }}"></label>
                        <label><span>Cash direction</span><select name="direction"><option value="">Money in and out</option><option value="in" @selected($direction === 'in')>Money in</option><option value="out" @selected($direction === 'out')>Money out</option></select></label>
                        <button class="button button-primary button-small" type="submit">Apply filters</button>
                        @if($selectedAccount || $month || $direction)<a href="{{ route('accounting.index') }}">Clear</a>@endif
                    </form>

                    <div class="accounting-ledger-table-wrap">
                        <table class="accounting-ledger-table">
                            <thead><tr><th>Date</th><th>Transaction</th><th>Account</th><th>Money in</th><th>Money out</th><th>Balance after</th></tr></thead>
                            <tbody>
                                @forelse($report['movements'] as $movement)
                                    <tr class="{{ $movement['account'] ? '' : 'unassigned' }}">
                                        <td data-label="Date"><strong>{{ $movement['occurred_at']->format('M j, Y') }}</strong><small>{{ $movement['occurred_at']->format('g:i A') }}</small></td>
                                        <td data-label="Transaction"><a href="{{ $movement['url'] }}">{{ $movement['title'] }}</a><small>{{ $movement['description'] }}</small>@if($movement['notes'])<em>{{ $movement['notes'] }}</em>@endif</td>
                                        <td data-label="Account">
                                            @if($report['accounts']->where('is_active', true)->isNotEmpty())
                                                <form method="POST" action="{{ route('accounting.transactions.assign') }}" class="ledger-account-assignment">@csrf @method('PATCH')<input type="hidden" name="source_type" value="{{ $movement['source_type'] }}"><input type="hidden" name="source_id" value="{{ $movement['source_id'] }}"><select name="financial_account_id" required aria-label="Account for {{ $movement['title'] }}"><option value="">Unassigned</option>@foreach($report['accounts']->where('is_active', true) as $account)<option value="{{ $account->id }}" @selected($movement['account']?->id === $account->id)>{{ $account->displayLabel() }}</option>@endforeach</select><button type="submit">{{ $movement['account'] ? 'Change' : 'Assign' }}</button></form>
                                            @else
                                                <a class="ledger-unassigned-link" href="#financial-accounts">Add account first</a>
                                            @endif
                                        </td>
                                        <td data-label="Money in" class="ledger-money-in">{{ $movement['direction'] === 'in' ? '₱'.number_format($movement['amount'], 2) : '—' }}</td>
                                        <td data-label="Money out" class="ledger-money-out">{{ $movement['direction'] === 'out' ? '₱'.number_format($movement['amount'], 2) : '—' }}</td>
                                        <td data-label="Balance after">{{ $movement['balance_after'] !== null ? '₱'.number_format($movement['balance_after'], 2) : 'Pending account' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="accounting-empty"><span><x-fa-icon name="book" /></span><strong>No ledger transactions match these filters.</strong><p>Record a collection or payment, or clear the current filters.</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>
@endsection
