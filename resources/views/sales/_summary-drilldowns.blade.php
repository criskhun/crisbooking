<dialog class="sales-drilldown-dialog sales-summary-dialog" data-sales-summary-dialog aria-labelledby="sales-summary-title">
    <article>
        <header>
            <div><span class="eyebrow">Financial drill-down</span><h2 id="sales-summary-title" data-sales-summary-title>Financial details</h2><p data-sales-summary-subtitle>Select a summary card to review its records.</p></div>
            <button class="icon-only-button" type="button" data-sales-summary-close aria-label="Close financial details"><x-fa-icon name="xmark" /></button>
        </header>

        <section class="sales-summary-panel" data-sales-summary-panel="gross" hidden>
            <div class="sales-drilldown-summary"><div><small>Confirmed bookings</small><strong>{{ $summaryConfirmedBookings->count() }}</strong></div><div><small>Gross booking sales</small><strong>₱{{ number_format($metrics['sales_total'], 2) }}</strong></div></div>
            <div class="sales-summary-list sales-summary-booking-list">
                @forelse($summaryConfirmedBookings as $booking)
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="receipt" /></span><span><a href="{{ route('bookings.show', $booking) }}">Booking #{{ $booking->id }}</a><small>{{ $booking->unit->name }} · {{ $booking->start_at->format('M j, Y') }}</small></span></div>
                        <div><strong>{{ $booking->customerDisplayName() }}</strong><small>{{ $booking->isManualBooking() ? $booking->sourceDisplayLabel() : $booking->acquisitionSourceLabel() }}</small></div>
                        <div><span class="summary-status {{ $booking->outstandingBalance() > 0 ? 'is-pending' : 'is-complete' }}">{{ $booking->paymentStatusLabel() }}</span><small>Collected ₱{{ number_format($booking->paymentTotal(), 2) }}</small></div>
                        <div><strong>₱{{ number_format($booking->revenueAmount(), 2) }}</strong><small>{{ $booking->outstandingBalance() > 0 ? '₱'.number_format($booking->outstandingBalance(), 2).' remaining' : 'No balance due' }}</small></div>
                    </article>
                @empty
                    <div class="sales-drilldown-empty"><strong>No confirmed bookings in this report.</strong><p>Try clearing or changing the reporting filters.</p></div>
                @endforelse
            </div>
        </section>

        <section class="sales-summary-panel" data-sales-summary-panel="expenses" hidden>
            <div class="sales-drilldown-summary"><div><small>Expense records</small><strong>{{ $bookingExpenseRows->count() + $unitOperatingCostRows->count() }}</strong></div><div><small>Operating expenses</small><strong>₱{{ number_format($metrics['operating_expenses'], 2) }}</strong></div></div>
            <div class="sales-summary-section-heading"><div><h3>Booking expenses</h3><p>Costs attached to confirmed bookings.</p></div><strong>₱{{ number_format($bookingExpenseRows->sum(fn ($row) => (float) $row['expense']->amount), 2) }}</strong></div>
            <div class="sales-summary-list">
                @forelse($bookingExpenseRows as $row)
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="broom" /></span><span><a href="{{ route('bookings.show', $row['booking']) }}">{{ $row['expense']->categoryLabel() }}</a><small>Booking #{{ $row['booking']->id }} · {{ $row['booking']->unit->name }}</small></span></div>
                        <div><strong>{{ $row['expense']->vendor_name ?: 'No vendor recorded' }}</strong><small>{{ $row['expense']->statusLabel() }}</small></div>
                        <div><span class="summary-status {{ in_array($row['expense']->status, ['paid', 'payment_received'], true) ? 'is-complete' : 'is-neutral' }}">{{ str($row['expense']->status)->replace('_', ' ')->title() }}</span></div>
                        <div><strong>₱{{ number_format((float) $row['expense']->amount, 2) }}</strong><small><a href="{{ route('bookings.show', $row['booking']) }}">Open booking →</a></small></div>
                    </article>
                @empty<div class="sales-summary-empty-row">No booking expenses in this report.</div>@endforelse
            </div>
            <div class="sales-summary-section-heading"><div><h3>Unit operating costs</h3><p>Bills, repairs, and routine costs for the selected units.</p></div><strong>₱{{ number_format($unitOperatingCostRows->sum(fn ($row) => (float) $row['cost']->amount), 2) }}</strong></div>
            <div class="sales-summary-list">
                @forelse($unitOperatingCostRows as $row)
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="wrench" /></span><span><a href="{{ route('sales.index', array_filter(['unit' => $row['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">{{ $row['cost']->categoryLabel() }}</a><small>{{ $row['unit']->name }} · {{ $row['cost']->incurred_on->format('M j, Y') }}</small></span></div>
                        <div><strong>{{ $row['cost']->vendor_name ?: 'No vendor recorded' }}</strong><small>{{ $row['cost']->notes ?: 'No note' }}</small></div>
                        <div><span class="summary-status {{ $row['cost']->status === 'paid' ? 'is-complete' : 'is-pending' }}">{{ ucfirst($row['cost']->status) }}</span></div>
                        <div><strong>₱{{ number_format((float) $row['cost']->amount, 2) }}</strong><small><a href="{{ route('sales.index', array_filter(['unit' => $row['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">Manage unit →</a></small></div>
                    </article>
                @empty<div class="sales-summary-empty-row">No unit operating costs in this report.</div>@endforelse
            </div>
        </section>

        <section class="sales-summary-panel" data-sales-summary-panel="profit" hidden>
            <div class="sales-drilldown-summary"><div><small>Reporting units</small><strong>{{ $unitReports->count() }}</strong></div><div><small>Operating profit</small><strong>₱{{ number_format($metrics['operating_profit'], 2) }}</strong></div></div>
            <div class="sales-summary-list sales-summary-unit-list">
                @forelse($unitReports as $report)
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="chart-line" /></span><span><a href="{{ route('sales.index', array_filter(['unit' => $report['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">{{ $report['unit']->name }}</a><small>{{ str($report['unit']->category)->replace('_', ' ')->title() }}</small></span></div>
                        <div><small>Gross sales</small><strong>₱{{ number_format($report['gross_sales'], 2) }}</strong></div>
                        <div><small>Operating expenses</small><strong>− ₱{{ number_format($report['total_operating_expenses'], 2) }}</strong></div>
                        <div><strong class="{{ $report['operating_profit'] < 0 ? 'negative' : 'positive' }}">₱{{ number_format($report['operating_profit'], 2) }}</strong><small>{{ number_format($report['profit_margin'], 1) }}% margin · <a href="{{ route('sales.index', array_filter(['unit' => $report['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">Open report →</a></small></div>
                    </article>
                @empty<div class="sales-drilldown-empty"><strong>No units match this report.</strong></div>@endforelse
            </div>
        </section>

        <section class="sales-summary-panel" data-sales-summary-panel="cash" hidden>
            <div class="sales-drilldown-summary"><div><small>Reporting units</small><strong>{{ $unitReports->count() }}</strong></div><div><small>Cash after obligations</small><strong>₱{{ number_format($metrics['cash_after_obligations'], 2) }}</strong></div></div>
            <div class="sales-summary-list sales-summary-unit-list">
                @forelse($unitReports as $report)
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="wallet" /></span><span><a href="{{ route('sales.index', array_filter(['unit' => $report['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">{{ $report['unit']->name }}</a><small>{{ str($report['unit']->category)->replace('_', ' ')->title() }}</small></span></div>
                        <div><small>Operating profit</small><strong>₱{{ number_format($report['operating_profit'], 2) }}</strong></div>
                        <div><small>Improvements + paid financing</small><strong>− ₱{{ number_format($report['capital_improvements'] + $report['financing_payments'], 2) }}</strong></div>
                        <div><strong class="{{ $report['cash_after_obligations'] < 0 ? 'negative' : 'positive' }}">₱{{ number_format($report['cash_after_obligations'], 2) }}</strong><small><a href="{{ route('sales.index', array_filter(['unit' => $report['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">Open finance center →</a></small></div>
                    </article>
                @empty<div class="sales-drilldown-empty"><strong>No units match this report.</strong></div>@endforelse
            </div>
        </section>

        <section class="sales-summary-panel" data-sales-summary-panel="payables" hidden>
            <div class="sales-drilldown-summary"><div><small>Items requiring action</small><strong>{{ $payableInstallmentRows->count() + $payableCostRows->count() }}</strong></div><div><small>Payables due</small><strong>₱{{ number_format($metrics['due_payables'], 2) }}</strong></div></div>
            <div class="sales-summary-list sales-summary-action-list">
                @foreach($payableInstallmentRows as $due)
                    @php
                        $overdue = $due['due_date']->isBefore(today());
                        $dueAccounts = $financialAccountsByHost->get($due['unit']->host_id, collect());
                    @endphp
                    <article>
                        <div><span class="sales-summary-icon attention"><x-fa-icon name="calendar-check" /></span><span><strong>{{ $due['obligation']->name }}</strong><small>{{ $due['unit']->name }} · {{ $due['month']->format('F Y') }} installment</small></span></div>
                        <div><span class="summary-status {{ $overdue ? 'is-overdue' : 'is-pending' }}">{{ $overdue ? 'Overdue' : 'Due '.$due['due_date']->format('M j') }}</span><small>{{ $due['obligation']->categoryLabel() }}</small></div>
                        <div><strong>₱{{ number_format($due['amount'], 2) }}</strong><small>Scheduled amount</small></div>
                        <form method="POST" action="{{ route('sales.units.obligations.payments.store', [$due['unit'], $due['obligation']]) }}">@csrf<input type="hidden" name="installment_month" value="{{ $due['month']->format('Y-m') }}"><input type="hidden" name="amount" value="{{ number_format($due['amount'], 2, '.', '') }}"><input type="hidden" name="notes" value="Recorded from the payables due list.">@if($dueAccounts->isNotEmpty())<select name="financial_account_id" required aria-label="Account used for {{ $due['obligation']->name }}"><option value="">Paid from account</option>@foreach($dueAccounts as $financialAccount)<option value="{{ $financialAccount->id }}">{{ $financialAccount->displayLabel() }}</option>@endforeach</select><button class="button button-primary button-small" type="submit">Record payment</button>@else<a class="financial-account-required compact" href="{{ route('accounting.index').'#financial-accounts' }}">Add account first</a>@endif<a href="{{ route('sales.index', array_filter(['unit' => $due['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">Open unit</a></form>
                    </article>
                @endforeach
                @foreach($payableCostRows as $row)
                    @php
                        $costDate = $row['cost']->due_on ?: $row['cost']->incurred_on;
                        $overdue = $costDate->isBefore(today());
                        $costAccounts = $financialAccountsByHost->get($row['unit']->host_id, collect());
                    @endphp
                    <article>
                        <div><span class="sales-summary-icon attention"><x-fa-icon name="file-invoice-dollar" /></span><span><strong>{{ $row['cost']->categoryLabel() }}</strong><small>{{ $row['unit']->name }}{{ $row['cost']->vendor_name ? ' · '.$row['cost']->vendor_name : '' }}</small></span></div>
                        <div><span class="summary-status {{ $overdue ? 'is-overdue' : 'is-pending' }}">{{ $overdue ? 'Overdue' : 'Due '.$costDate->format('M j') }}</span><small>{{ $row['cost']->due_on ? 'Due '.$row['cost']->due_on->format('M j, Y') : 'Recorded '.$row['cost']->incurred_on->format('M j, Y') }}</small></div>
                        <div><strong>₱{{ number_format((float) $row['cost']->amount, 2) }}</strong><small>Unpaid unit cost</small></div>
                        <form method="POST" action="{{ route('sales.units.costs.paid', [$row['unit'], $row['cost']]) }}">@csrf @method('PATCH')@if($costAccounts->isNotEmpty())<select name="financial_account_id" required aria-label="Account used for {{ $row['cost']->categoryLabel() }}"><option value="">Paid from account</option>@foreach($costAccounts as $financialAccount)<option value="{{ $financialAccount->id }}">{{ $financialAccount->displayLabel() }}</option>@endforeach</select><button class="button button-primary button-small" type="submit">Mark paid</button>@else<a class="financial-account-required compact" href="{{ route('accounting.index').'#financial-accounts' }}">Add account first</a>@endif<a href="{{ route('sales.index', array_filter(['unit' => $row['unit']->id, 'month' => $reportMonth?->format('Y-m')])) }}">Open unit</a></form>
                    </article>
                @endforeach
                @if($payableInstallmentRows->isEmpty() && $payableCostRows->isEmpty())
                    <div class="sales-drilldown-empty"><strong>You are caught up.</strong><p>No unpaid costs or installments are due through {{ $asOfMonth->format('F Y') }}.</p></div>
                @endif
            </div>
        </section>

        <section class="sales-summary-panel" data-sales-summary-panel="collectibles" hidden>
            <div class="sales-drilldown-summary"><div><small>Customers with balances</small><strong>{{ $collectibleBookings->count() }}</strong></div><div><small>Customer collectibles</small><strong>₱{{ number_format($metrics['receivables'], 2) }}</strong></div></div>
            <div class="sales-summary-list sales-summary-action-list sales-collectible-list">
                @forelse($collectibleBookings as $booking)
                    @php
                        $overdue = $booking->end_at->isBefore(now());
                        $collectionAccounts = $financialAccountsByHost->get($booking->unit->host_id, collect());
                    @endphp
                    <article>
                        <div><span class="sales-summary-icon"><x-fa-icon name="hand-holding-dollar" /></span><span><a href="{{ route('bookings.show', $booking) }}">{{ $booking->customerDisplayName() }}</a><small>Booking #{{ $booking->id }} · {{ $booking->unit->name }}</small></span></div>
                        <div><span class="summary-status {{ $overdue ? 'is-overdue' : 'is-pending' }}">{{ $overdue ? 'Past stay · balance due' : $booking->paymentStatusLabel() }}</span><small>{{ $booking->start_at->format('M j') }}–{{ $booking->end_at->format('M j, Y') }} · collected ₱{{ number_format($booking->paymentTotal(), 2) }}</small></div>
                        <div><strong>₱{{ number_format($booking->outstandingBalance(), 2) }}</strong><small>Remaining balance</small></div>
                        <form method="POST" action="{{ route('bookings.financial-entries.store', $booking) }}">@csrf<input type="hidden" name="kind" value="payment"><input type="hidden" name="category" value="balance_payment"><label><span class="sr-only">Amount collected for booking #{{ $booking->id }}</span><span class="money-input"><span>₱</span><input name="amount" inputmode="decimal" value="{{ number_format($booking->outstandingBalance(), 2, '.', ',') }}" required data-accounting-input aria-label="Amount collected for booking #{{ $booking->id }}"></span></label>@if($collectionAccounts->isNotEmpty())<select name="financial_account_id" required aria-label="Account that received collection for booking #{{ $booking->id }}"><option value="">Received into account</option>@foreach($collectionAccounts as $financialAccount)<option value="{{ $financialAccount->id }}">{{ $financialAccount->displayLabel() }}</option>@endforeach</select><input type="hidden" name="notes" value="Recorded from the customer collectibles list."><button class="button button-primary button-small" type="submit">Record collected</button>@else<a class="financial-account-required compact" href="{{ route('accounting.index').'#financial-accounts' }}">Add account first</a>@endif<a href="{{ route('bookings.show', $booking) }}">Open ledger</a></form>
                    </article>
                @empty
                    <div class="sales-drilldown-empty"><strong>Nothing to collect.</strong><p>All confirmed outside bookings in this report are fully paid.</p></div>
                @endforelse
            </div>
        </section>

        <footer><span>Values follow the unit, category, and month filters currently applied.</span><button class="button button-ghost button-small" type="button" data-sales-summary-close>Close details</button></footer>
    </article>
</dialog>
