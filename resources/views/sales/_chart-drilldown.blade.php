<script type="application/json" data-sales-drilldown-data>{!! json_encode($chartDrilldowns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<dialog class="sales-drilldown-dialog" data-sales-drilldown-dialog aria-labelledby="sales-drilldown-title">
    <article>
        <header>
            <div><span class="eyebrow">Chart drill-down</span><h2 id="sales-drilldown-title" data-sales-drilldown-title>Booking details</h2><p data-sales-drilldown-subtitle></p></div>
            <button type="button" data-sales-drilldown-close aria-label="Close chart details">×</button>
        </header>
        <section class="sales-drilldown-summary" aria-label="Selected chart summary">
            <div><small>Bookings</small><strong data-sales-drilldown-count>0 bookings</strong></div>
            <div><small data-sales-drilldown-value-label>Gross sales</small><strong data-sales-drilldown-value>₱0.00</strong></div>
        </section>
        <div class="sales-drilldown-table-wrap">
            <table class="sales-drilldown-table">
                <thead><tr><th>Booking / unit</th><th>Customer / source</th><th>Schedule</th><th>Status</th><th>Value</th></tr></thead>
                <tbody data-sales-drilldown-rows></tbody>
            </table>
            <div class="sales-drilldown-empty" data-sales-drilldown-empty hidden><strong>No bookings in this segment.</strong><p>Try another month, category, source, or status.</p></div>
        </div>
        <footer><span>Select a booking number to open its complete record.</span><button class="button button-ghost button-small" type="button" data-sales-drilldown-close>Close details</button></footer>
    </article>
</dialog>
