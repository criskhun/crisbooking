@extends('layouts.app')

@section('title', 'Sales and profitability — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Business intelligence</span><h1>Sales & profitability</h1></div>@include('partials.user-badge')</header>
            <section class="sales-dashboard">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="oauth-error account-alert" role="alert"><strong>The financial record could not be saved.</strong><br>{{ $errors->first() }}</div>@endif

                <form class="sales-report-filter aligned-filter-form" method="GET" action="{{ route('sales.index') }}">
                    <div><span class="eyebrow">Reporting scope</span><h2>{{ $reportMonth ? $reportMonth->format('F Y') : 'All-time business report' }}</h2></div>
                    <label><span>Unit</span><select name="unit"><option value="">All units</option>@foreach($accessibleUnits->when($selectedCategory, fn ($units) => $units->where('category', $selectedCategory)) as $filterUnit)<option value="{{ $filterUnit->id }}" @selected($selectedUnitId === $filterUnit->id)>{{ $filterUnit->name }}</option>@endforeach</select></label>
                    <label><span>Category</span><select name="category"><option value="">All categories</option>@foreach($categories as $tabCategory)<option value="{{ $tabCategory }}" @selected($selectedCategory === $tabCategory)>{{ str($tabCategory)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                    <label><span>Month</span><input name="month" type="month" value="{{ $reportMonth?->format('Y-m') }}"><small>Leave empty for all time.</small></label>
                    <div class="aligned-filter-actions"><button class="button button-primary button-small" type="submit">Apply report</button>
                    @if($selectedUnit || $selectedCategory || $reportMonth)<a class="button button-ghost button-small" href="{{ route('sales.index') }}">Clear</a>@endif</div>
                </form>

                <div class="sales-metric-grid sales-profit-metrics">
                    <article class="primary"><span>Gross booking sales</span><strong>₱{{ number_format($metrics['sales_total'], 2) }}</strong><small>{{ $metrics['confirmed_count'] }} confirmed {{ Str::plural('booking', $metrics['confirmed_count']) }}</small></article>
                    <article><span>Operating expenses</span><strong>₱{{ number_format($metrics['operating_expenses'], 2) }}</strong><small>Booking and unit-level costs</small></article>
                    <article class="{{ $metrics['operating_profit'] < 0 ? 'negative' : 'positive' }}"><span>Operating profit</span><strong>₱{{ number_format($metrics['operating_profit'], 2) }}</strong><small>{{ number_format($metrics['profit_margin'], 1) }}% margin</small></article>
                    <article><span>Cash after obligations</span><strong>₱{{ number_format($metrics['cash_after_obligations'], 2) }}</strong><small>After financing and improvements</small></article>
                    <article class="{{ $metrics['due_payables'] > 0 ? 'attention' : '' }}"><span>Payables due</span><strong>₱{{ number_format($metrics['due_payables'], 2) }}</strong><small>Unpaid costs and installments</small></article>
                    <article><span>Customer collectibles</span><strong>₱{{ number_format($metrics['receivables'], 2) }}</strong><small>Confirmed booking balances</small></article>
                </div>

                <section class="sales-unit-report-card">
                    <div class="sales-chart-heading"><div><span class="eyebrow">Unit performance</span><h2>Profitability by unit</h2></div><div class="unit-profit-heading-actions"><small>{{ $reportMonth ? $reportMonth->format('M Y') : 'All recorded activity' }}</small>@if($selectedUnitReport)<button class="button button-ghost button-small" type="button" data-unit-profit-report-open>View & print report</button>@endif</div></div>
                    <div class="unit-profit-table">
                        <div class="unit-profit-head"><span>Unit</span><span>Gross sales</span><span>Operating costs</span><span>Profit</span><span>Owner share</span><span>Manager share</span><span>Due</span></div>
                        @forelse($unitReports as $report)
                            <a href="{{ route('sales.index', array_filter(['unit' => $report['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">
                                <span><strong>{{ $report['unit']->name }}</strong><small>{{ str($report['unit']->category)->replace('_', ' ')->title() }} · {{ $report['profile']->management_type === 'managed_for_owner' ? 'Managed for '.$report['profile']->owner_name : 'Owner-managed' }}</small></span>
                                <b>₱{{ number_format($report['gross_sales'], 2) }}</b>
                                <b>₱{{ number_format($report['total_operating_expenses'], 2) }}</b>
                                <b class="{{ $report['operating_profit'] < 0 ? 'negative' : 'positive' }}">₱{{ number_format($report['operating_profit'], 2) }}<small>{{ number_format($report['profit_margin'], 1) }}%</small></b>
                                <b>₱{{ number_format($report['owner_share'], 2) }}<small>{{ number_format((float) $report['profile']->owner_share_percentage, 2) }}%</small></b>
                                <b>₱{{ number_format($report['manager_share'], 2) }}<small>{{ number_format((float) $report['profile']->manager_share_percentage, 2) }}%</small></b>
                                <b class="{{ $report['due_payables'] > 0 ? 'attention' : '' }}">₱{{ number_format($report['due_payables'], 2) }}</b>
                            </a>
                        @empty
                            <div class="overview-empty"><strong>No units match this report.</strong><p>Clear the filters or add a listing first.</p></div>
                        @endforelse
                    </div>
                </section>

                <div class="sales-chart-grid sales-profit-chart-grid">
                    <section class="sales-chart-card monthly-sales-chart">
                        <div class="sales-chart-heading"><div><span class="eyebrow">12-month trend</span><h2>Sales by month: gross versus operating profit</h2></div><small>Select a month to drill down</small></div>
                        <div class="sales-bars profit-bars" role="img" aria-label="Monthly gross sales and operating profit chart">
                            @foreach ($monthlySales as $monthSale)
                                <a href="{{ route('sales.index', array_filter(['unit' => $selectedUnitId ?: null, 'category' => $selectedCategory ?: null, 'month' => $monthSale['month']])) }}" data-sales-drilldown-key="month:{{ $monthSale['month'] }}">
                                    <span class="sales-bar-value">Sales ₱{{ number_format($monthSale['value'], 2) }} · Expenses ₱{{ number_format($monthSale['expenses'], 2) }} · Profit ₱{{ number_format($monthSale['profit'], 2) }}</span>
                                    <span class="profit-bar-pair"><i style="height: {{ max(3, round($monthSale['value'] / $maxMonthlySale * 100)) }}%"></i><b class="{{ $monthSale['profit'] < 0 ? 'negative' : '' }}" style="height: {{ max(3, round(abs($monthSale['profit']) / $maxMonthlySale * 100)) }}%"></b></span>
                                    <small>{{ $monthSale['label'] }}</small>
                                </a>
                            @endforeach
                        </div>
                        <div class="sales-chart-legend"><span><i></i>Gross sales</span><span><i></i>Operating profit</span></div>
                    </section>

                    <section class="sales-chart-card sales-health-card">
                        <div class="sales-chart-heading"><div><span class="eyebrow">Business health</span><h2>Can the business cover its bills?</h2></div></div>
                        <dl class="sales-health-list">
                            <div><dt>Operating margin</dt><dd>{{ number_format($metrics['profit_margin'], 1) }}%</dd></div>
                            <div><dt>Average confirmed sale</dt><dd>₱{{ number_format($metrics['average_sale'], 2) }}</dd></div>
                            <div><dt>Pending pipeline</dt><dd>₱{{ number_format($metrics['pending_total'], 2) }}</dd></div>
                            <div><dt>Available after due payables</dt><dd class="{{ $metrics['available_after_due'] < 0 ? 'negative' : 'positive' }}">₱{{ number_format($metrics['available_after_due'], 2) }}</dd></div>
                        </dl>
                    </section>

                    <section class="sales-chart-card">
                        <div class="sales-chart-heading"><div><span class="eyebrow">Revenue mix</span><h2>Sales by category</h2></div><small>Select a bar</small></div>
                        <div class="category-sales-bars">
                            @forelse ($categorySales as $categorySale)
                                <button type="button" data-sales-drilldown-key="category:{{ $categorySale['category'] }}"><span><strong>{{ str($categorySale['category'])->replace('_', ' ')->title() }}</strong><small>{{ $categorySale['count'] }} bookings</small></span><i><b style="width: {{ max(3, round($categorySale['value'] / $maxCategorySale * 100)) }}%"></b></i><em>₱{{ number_format($categorySale['value'], 2) }}</em></button>
                            @empty<div class="overview-empty compact"><strong>No confirmed sales yet.</strong></div>@endforelse
                        </div>
                    </section>

                    <section class="sales-chart-card sales-source-card" data-sales-source-chart>
                        <div class="sales-chart-heading"><div><span class="eyebrow">Marketing attribution</span><h2>Where sales came from</h2></div><small>Select a source</small></div>
                        <div class="sales-source-bars">
                            @forelse($sourceSales as $sourceSale)
                                <button type="button" data-sales-drilldown-key="source:{{ $sourceSale['source'] }}">
                                    <span class="sales-source-rank">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span><strong>{{ $sourceSale['label'] }}</strong><small>{{ $sourceSale['count'] }} confirmed {{ Str::plural('booking', $sourceSale['count']) }}</small></span>
                                    <i><b style="width:{{ max(3, round($sourceSale['value'] / $maxSourceSale * 100)) }}%"></b></i>
                                    <em>₱{{ number_format($sourceSale['value'], 2) }}</em>
                                </button>
                            @empty
                                <div class="overview-empty compact"><strong>No attributed sales yet.</strong><p>Confirmed bookings from Airbnb, Agoda, Facebook, direct sales, and other sources will appear here.</p></div>
                            @endforelse
                        </div>
                    </section>

                    @php
                        $statusBookingCount = $metrics['confirmed_count'] + $metrics['pending_count'] + $metrics['cancelled_count'];
                        $statusTotal = max(1, $statusBookingCount);
                        $confirmedDegrees = round($metrics['confirmed_count'] / $statusTotal * 360);
                        $pendingDegrees = round($metrics['pending_count'] / $statusTotal * 360);
                    @endphp
                    <section class="sales-chart-card sales-status-card">
                        <div class="sales-chart-heading"><div><span class="eyebrow">Pipeline health</span><h2>Booking status mix</h2></div><small>Select a segment</small></div>
                        <div class="sales-status-visual"><button class="sales-donut" type="button" data-sales-drilldown-key="status:all" style="--confirmed-angle: {{ $confirmedDegrees }}deg; --pending-angle: {{ $confirmedDegrees + $pendingDegrees }}deg"><strong>{{ $statusBookingCount }}</strong><small>Total</small></button><ul><li><button type="button" data-sales-drilldown-key="status:confirmed"><i class="confirmed"></i><span>Confirmed</span><strong>{{ $metrics['confirmed_count'] }}</strong></button></li><li><button type="button" data-sales-drilldown-key="status:pending"><i class="pending"></i><span>Pending</span><strong>{{ $metrics['pending_count'] }}</strong></button></li><li><button type="button" data-sales-drilldown-key="status:cancelled"><i class="cancelled"></i><span>Cancelled</span><strong>{{ $metrics['cancelled_count'] }}</strong></button></li></ul></div>
                    </section>
                </div>

                @if($selectedUnit && $selectedUnitReport)
                    @php
                        $profile = $selectedUnitReport['profile'];
                    @endphp
                    <section class="unit-finance-workspace">
                        <div class="unit-finance-heading"><div><span class="eyebrow">Unit finance center</span><h2>{{ $selectedUnit->name }}</h2><p>Configure ownership, record bills and improvements, and track every monthly obligation.</p></div><span>Tracked unit value<strong>₱{{ number_format($selectedUnitReport['current_asset_value'], 2) }}</strong></span></div>

                        <div class="unit-finance-summary">
                            <article><small>Gross</small><strong>₱{{ number_format($selectedUnitReport['gross_sales'], 2) }}</strong></article>
                            <article><small>Booking expenses</small><strong>₱{{ number_format($selectedUnitReport['booking_expenses'], 2) }}</strong></article>
                            <article><small>Unit overhead</small><strong>₱{{ number_format($selectedUnitReport['operating_costs'], 2) }}</strong></article>
                            <article><small>Operating profit</small><strong>₱{{ number_format($selectedUnitReport['operating_profit'], 2) }}</strong></article>
                            <article><small>Capital additions</small><strong>₱{{ number_format($selectedUnitReport['capital_improvements'], 2) }}</strong></article>
                            <article><small>Financing paid</small><strong>₱{{ number_format($selectedUnitReport['financing_payments'], 2) }}</strong></article>
                        </div>

                        <div class="unit-finance-config-grid">
                            <details class="unit-finance-panel" open>
                                <summary><span><strong>Ownership & sharing</strong><small>Who owns and who manages this unit</small></span><b>Edit</b></summary>
                                <form method="POST" action="{{ route('sales.units.financial-profile.update', $selectedUnit) }}" class="unit-finance-form">@csrf @method('PATCH')
                                    <label><span>Management</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-house"></i></span><select name="management_type" required><option value="owner_managed" @selected(old('management_type', $profile->management_type) === 'owner_managed')>I / host own and manage it</option><option value="managed_for_owner" @selected(old('management_type', $profile->management_type) === 'managed_for_owner')>Managed for another owner</option></select></div></label>
                                    <label><span>Owner name</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-user"></i></span><input name="owner_name" maxlength="120" value="{{ old('owner_name', $profile->owner_name) }}" placeholder="Person or company"></div></label>
                                    <label><span>Owner share %</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-percent"></i></span><input name="owner_share_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('owner_share_percentage', $profile->owner_share_percentage) }}" required></div></label>
                                    <label><span>Managing host share %</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-percent"></i></span><input name="manager_share_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('manager_share_percentage', $profile->manager_share_percentage) }}" required></div></label>
                                    <label><span>Calculate shares from</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span><select name="share_basis" required><option value="operating_profit" @selected(old('share_basis', $profile->share_basis) === 'operating_profit')>Operating profit after expenses</option><option value="gross_sales" @selected(old('share_basis', $profile->share_basis) === 'gross_sales')>Gross booking sales</option></select></div></label>
                                    <label><span>Initial unit / asset value</span><div class="money-input finance-control-money"><span>₱</span><input name="initial_asset_value" inputmode="decimal" value="{{ number_format((float) old('initial_asset_value', $profile->initial_asset_value), 2, '.', ',') }}" required data-accounting-input></div></label>
                                    <p>Owner and manager percentages must total 100%. Capital improvements automatically increase the tracked unit value.</p>
                                    <button class="button button-primary button-small" type="submit">Save ownership setup</button>
                                </form>
                            </details>

                            <details class="unit-finance-panel">
                                <summary><span><strong>Add bill, maintenance, or improvement</strong><small>Operating cost, payable, repair, or added value</small></span><b>Add</b></summary>
                                <form method="POST" action="{{ route('sales.units.costs.store', $selectedUnit) }}" class="unit-finance-form">@csrf
                                    <label><span>Cost type</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-tag"></i></span><select name="category" required>@foreach($costCategoryOptions as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div></label>
                                    <label><span>Amount</span><div class="money-input finance-control-money"><span>₱</span><input name="amount" inputmode="decimal" value="{{ old('amount') }}" required data-accounting-input></div></label>
                                    <label><span>Payment status</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span><select name="status" required><option value="payable" @selected(old('status', 'payable') === 'payable')>Still payable</option><option value="paid" @selected(old('status') === 'paid')>Already paid</option></select></div></label>
                                    <label><span>Cost month / date</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-calendar-day"></i></span><input name="incurred_on" type="date" value="{{ old('incurred_on', ($reportMonth ?: now())->toDateString()) }}" required></div></label>
                                    <label><span>Due date <small>Optional</small></span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span><input name="due_on" type="date" value="{{ old('due_on') }}"></div></label>
                                    <label><span>Vendor <small>Optional</small></span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-store"></i></span><input name="vendor_name" maxlength="120" value="{{ old('vendor_name') }}"></div></label>
                                    <label class="wide"><span>Notes <small>Optional</small></span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-note-sticky"></i></span><input name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Receipt, repair details, or improvement description"></div></label>
                                    <p class="wide">Routine maintenance and repairs reduce profit. A capital improvement is tracked separately and increases the unit’s value.</p>
                                    <button class="button button-primary button-small" type="submit">Record unit cost</button>
                                </form>
                            </details>

                            <details class="unit-finance-panel">
                                <summary><span><strong>Add monthly payable</strong><small>Amortization, lease, insurance, or recurring dues</small></span><b>Add</b></summary>
                                <form method="POST" action="{{ route('sales.units.obligations.store', $selectedUnit) }}" class="unit-finance-form">@csrf
                                    <label><span>Payable name</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span><input name="name" maxlength="120" value="{{ old('name') }}" placeholder="Example: Vehicle loan" required></div></label>
                                    <label><span>Type</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-list"></i></span><select name="category" required>@foreach($obligationCategoryOptions as $value => $label)<option value="{{ $value }}" @selected(old('category', 'amortization') === $value)>{{ $label }}</option>@endforeach</select></div></label>
                                    <label><span>Monthly amount</span><div class="money-input finance-control-money"><span>₱</span><input name="monthly_amount" inputmode="decimal" value="{{ old('monthly_amount') }}" required data-accounting-input></div></label>
                                    <label><span>Start month</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-calendar"></i></span><input name="start_month" type="month" value="{{ old('start_month', now()->format('Y-m')) }}" required></div></label>
                                    <label><span>Term in months</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span><input name="term_months" type="number" min="1" max="600" list="obligation_term_options" value="{{ old('term_months', 24) }}" required><datalist id="obligation_term_options">@foreach([6,12,24,36,48,60,72] as $term)<option value="{{ $term }}">{{ $term }} months</option>@endforeach</datalist></div></label>
                                    <label><span>Due every month on</span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span><input name="due_day" type="number" min="1" max="28" value="{{ old('due_day', 1) }}" required></div></label>
                                    <label class="wide"><span>Notes <small>Optional</small></span><div class="finance-control"><span aria-hidden="true"><i class="fa-solid fa-note-sticky"></i></span><input name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Lender, account reference, or terms"></div></label>
                                    <p class="wide">Amortization affects cash available to pay bills but is shown separately from operating profit.</p>
                                    <button class="button button-primary button-small" type="submit">Create payment schedule</button>
                                </form>
                            </details>
                        </div>

                        <div class="unit-finance-ledger-grid">
                            <section class="unit-ledger-panel">
                                <div class="sales-chart-heading"><div><span class="eyebrow">Bills & unit costs</span><h2>Recent costs</h2></div><small>{{ $selectedUnit->costs->count() }} records</small></div>
                                <div class="unit-cost-list">
                                    @forelse($selectedUnit->costs->take(20) as $cost)
                                        <article><span class="unit-cost-icon">{{ $cost->classification === 'capital' ? '＋' : '−' }}</span><div><strong>{{ $cost->categoryLabel() }}</strong><small>{{ $cost->incurred_on->format('M j, Y') }}{{ $cost->vendor_name ? ' · '.$cost->vendor_name : '' }}</small>@if($cost->notes)<p>{{ $cost->notes }}</p>@endif</div><div><b>₱{{ number_format((float) $cost->amount, 2) }}</b><span class="booking-status {{ $cost->status === 'paid' ? 'status-confirmed' : 'status-payment_submitted' }}">{{ ucfirst($cost->status) }}</span></div>@if($cost->status === 'payable')<form method="POST" action="{{ route('sales.units.costs.paid', [$selectedUnit, $cost]) }}">@csrf @method('PATCH')<button class="button button-ghost button-small" type="submit">Mark paid</button></form>@endif</article>
                                    @empty<p>No unit-level costs yet.</p>@endforelse
                                </div>
                            </section>

                            <section class="unit-ledger-panel">
                                <div class="sales-chart-heading"><div><span class="eyebrow">Monthly commitments</span><h2>Payable schedules</h2></div><small>As of {{ $asOfMonth->format('M Y') }}</small></div>
                                <div class="unit-obligation-list">
                                    @forelse($selectedUnit->obligations as $obligation)
                                        @php
                                            $paidCount = $obligation->payments->count();
                                            $progress = min(100, round($paidCount / max(1, $obligation->term_months) * 100));
                                        @endphp
                                        <article>
                                            <header><span><strong>{{ $obligation->name }}</strong><small>{{ $obligation->categoryLabel() }} · due on day {{ $obligation->due_day }} each month</small></span><b>₱{{ number_format((float) $obligation->monthly_amount, 2) }}/mo</b></header>
                                            <div class="obligation-progress"><i style="width:{{ $progress }}%"></i></div>
                                            <div class="obligation-facts"><span>{{ $paidCount }}/{{ $obligation->term_months }} paid</span><span>{{ $obligation->start_month->format('M Y') }}–{{ $obligation->endMonth()->format('M Y') }}</span><span>{{ ucfirst($obligation->status) }}</span></div>
                                            @if($obligation->status === 'active')
                                                <form method="POST" action="{{ route('sales.units.obligations.payments.store', [$selectedUnit, $obligation]) }}">@csrf<input name="installment_month" type="month" value="{{ old('installment_month', $asOfMonth->format('Y-m')) }}" required><div class="money-input"><span>₱</span><input name="amount" inputmode="decimal" value="{{ number_format((float) $obligation->monthly_amount, 2, '.', ',') }}" required data-accounting-input></div><button class="button button-primary button-small" type="submit">Record paid month</button></form>
                                            @endif
                                        </article>
                                    @empty<p>No monthly payable schedules yet.</p>@endforelse
                                </div>
                            </section>
                        </div>

                        @if($selectedUnitReport['outstanding_installments']->isNotEmpty() || $selectedUnitReport['cost_payables']->isNotEmpty())
                            <section class="due-payables-panel">
                                <div><span class="eyebrow">Action needed</span><h2>Unpaid obligations through {{ $asOfMonth->format('F Y') }}</h2><p>These amounts reduce what is available after the unit’s operating cash flow.</p></div><strong>₱{{ number_format($selectedUnitReport['due_payables'], 2) }}</strong>
                                <ul>@foreach($selectedUnitReport['outstanding_installments'] as $due)<li><span><b>{{ $due['obligation']->name }}</b><small>{{ $due['month']->format('F Y') }} installment · due {{ $due['due_date']->format('M j') }}</small></span><strong>₱{{ number_format($due['amount'], 2) }}</strong></li>@endforeach @foreach($selectedUnitReport['cost_payables'] as $cost)<li><span><b>{{ $cost->categoryLabel() }}</b><small>{{ $cost->due_on ? 'Due '.$cost->due_on->format('M j, Y') : 'Recorded '.$cost->incurred_on->format('M j, Y') }}</small></span><strong>₱{{ number_format((float) $cost->amount, 2) }}</strong></li>@endforeach</ul>
                            </section>
                        @endif
                    </section>
                @endif

                <section class="sales-ledger-card">
                    <div class="sales-chart-heading"><div><span class="eyebrow">Booking ledger</span><h2>Recent booking sales</h2></div><small>{{ $selectedUnit?->name ?? ($selectedCategory ? str($selectedCategory)->replace('_', ' ')->title() : 'All units') }}</small></div>
                    <div class="sales-ledger-table"><div class="sales-ledger-head"><span>Booking</span><span>Client / source</span><span>Category</span><span>Status</span><span>Gross value</span></div>@forelse($recentBookings as $sale)<a href="{{ route('bookings.show', $sale) }}"><span><strong>#{{ $sale->id }} · {{ $sale->unit->name }}</strong><small>{{ $sale->start_at->format('M j, Y · g:i A') }}</small></span><span>{{ $sale->customerDisplayName() }}@if($sale->isManualBooking())<small>{{ $sale->sourceDisplayLabel() }}</small>@endif</span><span>{{ str($sale->unit->category)->replace('_', ' ')->title() }}</span><span><em class="booking-status status-{{ $sale->status }}">{{ $sale->statusLabel() }}</em></span><strong>{{ $sale->status === 'confirmed' ? '₱'.number_format($sale->revenueAmount(), 2) : '—' }}</strong></a>@empty<div class="overview-empty"><strong>No booking records in this report.</strong></div>@endforelse</div>
                </section>
            </section>
        </main>
    </div>
    @if($selectedUnit && $selectedUnitReport)
        @include('sales._unit-profit-report')
    @endif
    @include('sales._chart-drilldown')
@endsection
