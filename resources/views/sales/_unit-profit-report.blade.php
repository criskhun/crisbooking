<dialog class="unit-profit-report-dialog" data-unit-profit-report-dialog aria-labelledby="unit-profit-report-title">
    <article class="unit-profit-print-sheet">
        <header class="unit-profit-print-header">
            <div>
                <span class="eyebrow">Davao Rent Zone · Unit profitability report</span>
                <h2 id="unit-profit-report-title">{{ $selectedUnit->name }}</h2>
                <p>{{ $reportMonth ? $reportMonth->format('F Y') : 'All recorded activity' }} · {{ str($selectedUnit->category)->replace('_', ' ')->title() }}</p>
            </div>
            <div class="unit-profit-report-actions report-print-hidden">
                <button class="button button-ghost button-small" type="button" data-unit-profit-report-close>Close</button>
                <button class="button button-primary button-small" type="button" data-unit-profit-report-print>Print report</button>
            </div>
        </header>

        <section class="unit-profit-report-meta" aria-label="Report ownership information">
            <div><small>Management</small><strong>{{ $selectedUnitReport['profile']->management_type === 'managed_for_owner' ? 'Managed for owner' : 'Owner-managed' }}</strong></div>
            <div><small>Owner</small><strong>{{ $selectedUnitReport['profile']->owner_name ?: $selectedUnit->host?->name }}</strong></div>
            <div><small>Share basis</small><strong>{{ $selectedUnitReport['profile']->share_basis === 'gross_sales' ? 'Gross booking sales' : 'Operating profit' }}</strong></div>
            <div><small>Generated</small><strong>{{ now()->format('M j, Y · g:i A') }}</strong></div>
        </section>

        <section class="unit-profit-report-summary" aria-label="Unit profitability totals">
            <article><small>Gross booking sales</small><strong>₱{{ number_format($selectedUnitReport['gross_sales'], 2) }}</strong></article>
            <article><small>Booking expenses</small><strong>₱{{ number_format($selectedUnitReport['booking_expenses'], 2) }}</strong></article>
            <article><small>Unit overhead</small><strong>₱{{ number_format($selectedUnitReport['operating_costs'], 2) }}</strong></article>
            <article class="{{ $selectedUnitReport['operating_profit'] < 0 ? 'negative' : 'positive' }}"><small>Operating profit</small><strong>₱{{ number_format($selectedUnitReport['operating_profit'], 2) }}</strong><span>{{ number_format($selectedUnitReport['profit_margin'], 1) }}% margin</span></article>
            <article><small>Owner share ({{ number_format((float) $selectedUnitReport['profile']->owner_share_percentage, 2) }}%)</small><strong>₱{{ number_format($selectedUnitReport['owner_share'], 2) }}</strong></article>
            <article><small>Manager share ({{ number_format((float) $selectedUnitReport['profile']->manager_share_percentage, 2) }}%)</small><strong>₱{{ number_format($selectedUnitReport['manager_share'], 2) }}</strong></article>
            <article><small>Capital additions</small><strong>₱{{ number_format($selectedUnitReport['capital_improvements'], 2) }}</strong></article>
            <article><small>Financing paid</small><strong>₱{{ number_format($selectedUnitReport['financing_payments'], 2) }}</strong></article>
            <article><small>Payables due</small><strong>₱{{ number_format($selectedUnitReport['due_payables'], 2) }}</strong></article>
            <article><small>Available after due</small><strong>₱{{ number_format($selectedUnitReport['available_after_due'], 2) }}</strong></article>
        </section>

        <section class="unit-profit-booking-section">
            <div class="unit-profit-report-section-heading">
                <div><span class="eyebrow">Booking acquisition ledger</span><h3>All bookings in this report</h3></div>
                <strong>{{ $selectedUnitBookings->count() }} {{ Str::plural('booking', $selectedUnitBookings->count()) }}</strong>
            </div>
            <div class="unit-profit-report-table-wrap">
                <table class="unit-profit-report-table">
                    <thead><tr><th>Booking</th><th>Customer / source</th><th>Schedule</th><th>Status</th><th>Gross</th><th>Expenses</th><th>Net contribution</th></tr></thead>
                    <tbody>
                        @forelse($selectedUnitBookings as $reportBooking)
                            @php
                                $isRecognizedSale = $reportBooking->status === 'confirmed';
                                $bookingGross = $isRecognizedSale ? $reportBooking->revenueAmount() : 0;
                                $bookingExpenses = $isRecognizedSale ? $reportBooking->expenseTotal() : 0;
                            @endphp
                            <tr>
                                <td><a href="{{ route('bookings.show', $reportBooking) }}">#{{ $reportBooking->id }}</a><small>{{ $reportBooking->isManualBooking() ? 'Outside booking' : 'Platform booking' }}</small></td>
                                <td><strong>{{ $reportBooking->customerDisplayName() }}</strong><small>{{ $reportBooking->isManualBooking() ? $reportBooking->sourceDisplayLabel() : 'Davao Rent Zone platform' }}</small></td>
                                <td><strong>{{ $reportBooking->start_at->format('M j, Y · g:i A') }}</strong><small>to {{ $reportBooking->end_at->format('M j, Y · g:i A') }}</small></td>
                                <td><span class="booking-status status-{{ $reportBooking->status }}">{{ $reportBooking->statusLabel() }}</span></td>
                                <td>{{ $isRecognizedSale ? '₱'.number_format($bookingGross, 2) : '—' }}</td>
                                <td>{{ $isRecognizedSale ? '₱'.number_format($bookingExpenses, 2) : '—' }}</td>
                                <td class="{{ $isRecognizedSale && ($bookingGross - $bookingExpenses) < 0 ? 'negative' : '' }}">{{ $isRecognizedSale ? '₱'.number_format($bookingGross - $bookingExpenses, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="unit-profit-report-empty">No bookings match this unit and reporting period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><th colspan="4">Confirmed booking totals</th><th>₱{{ number_format($selectedUnitReport['gross_sales'], 2) }}</th><th>₱{{ number_format($selectedUnitReport['booking_expenses'], 2) }}</th><th>₱{{ number_format($selectedUnitReport['gross_sales'] - $selectedUnitReport['booking_expenses'], 2) }}</th></tr></tfoot>
                </table>
            </div>
            <p class="unit-profit-report-note">Pending and cancelled bookings remain in the acquisition ledger for context, but only confirmed bookings are included in gross sales and profit.</p>
        </section>
    </article>
</dialog>
