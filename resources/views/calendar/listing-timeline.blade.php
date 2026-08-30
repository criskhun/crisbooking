<section class="listing-timeline-card" aria-labelledby="listing-timeline-title">
    <div class="listing-timeline-heading">
        <div>
            <span class="eyebrow">Portfolio schedule</span>
            <h2 id="listing-timeline-title">Listing timeline</h2>
            <p>Compare every {{ $isAffiliateCalendar ? 'assigned' : 'owned' }} listing and its occupied dates in one view.</p>
        </div>
        <span>{{ $units->count() }} {{ Str::plural('listing', $units->count()) }}</span>
    </div>

    @if ($units->isEmpty())
        <div class="listing-timeline-empty"><span>☷</span><strong>No listings match these filters.</strong><p>Clear the filters or choose another category.</p></div>
    @else
        <div class="listing-timeline-scroll" tabindex="0" aria-label="Horizontally scrollable listing timeline for {{ $month->format('F Y') }}">
            <div class="listing-timeline-grid" style="--timeline-days: {{ $timelineDays->count() }}; --timeline-rows: {{ $units->count() }};">
                <div class="listing-timeline-corner"><strong>Listings</strong><small>{{ $month->format('F Y') }}</small></div>
                @foreach ($timelineDays as $timelineDay)
                    <a @class(['listing-timeline-date', 'is-weekend' => $timelineDay->isWeekend(), 'is-today' => $timelineDay->isToday(), 'selected' => $timelineDay->isSameDay($selectedDate)])
                       style="grid-column: {{ $loop->index + 2 }}; grid-row: 1;"
                       href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => $month->format('Y-m'), 'date' => $timelineDay->format('Y-m-d')])) }}">
                        <small>{{ $timelineDay->format('D') }}</small><strong>{{ $timelineDay->format('d') }}</strong>
                    </a>
                @endforeach

                @foreach ($units as $unit)
                    @php
                        $timelineRow = $loop->index + 2;
                        $unitMeta = $calendarCategoryMeta[$unit->category] ?? ['theme' => 'other', 'icon' => '✦', 'label' => str($unit->category)->replace('_', ' ')->title()];
                        $unitBookings = $bookings->where('unit_id', $unit->id)->whereIn('status', ['pending', 'pre_approved', 'payment_submitted', 'confirmed']);
                        $unitStyle = $calendarUnitStyles[$unit->id] ?? null;
                    @endphp
                    <div class="listing-timeline-unit" style="grid-column: 1; grid-row: {{ $timelineRow }};">
                        @if ($unit->primaryImagePath())
                            <img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="">
                        @else
                            <span class="listing-timeline-placeholder" aria-hidden="true">{{ $unitMeta['icon'] }}</span>
                        @endif
                        <div><strong>{{ $unit->name }}</strong><small>{{ $unitMeta['label'] }}{{ $unit->location ? ' · '.$unit->location : '' }}</small></div>
                        <i @class(['active' => $unit->is_active]) title="{{ $unit->is_active ? 'Active listing' : 'Inactive listing' }}"></i>
                    </div>

                    @foreach ($timelineDays as $timelineDay)
                        <a @class(['listing-timeline-cell', 'is-weekend' => $timelineDay->isWeekend(), 'is-today' => $timelineDay->isToday(), 'selected' => $timelineDay->isSameDay($selectedDate)])
                           style="grid-column: {{ $loop->index + 2 }}; grid-row: {{ $timelineRow }};"
                           aria-label="{{ $unit->name }} on {{ $timelineDay->format('F j, Y') }}"
                           href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => $month->format('Y-m'), 'date' => $timelineDay->format('Y-m-d')])) }}"></a>
                    @endforeach

                    @foreach ($unitBookings as $timelineBooking)
                        @php
                            $timelineStart = $timelineBooking->start_at->copy()->startOfDay()->max($month->copy()->startOfMonth());
                            $timelineEnd = $timelineBooking->end_at->copy()->startOfDay()->min($month->copy()->endOfMonth());
                        @endphp
                        @continue($timelineEnd->lt($timelineStart))
                        @php
                            $timelineStartColumn = (int) $month->copy()->startOfMonth()->diffInDays($timelineStart) + 2;
                            $timelineEndColumn = (int) $month->copy()->startOfMonth()->diffInDays($timelineEnd) + 3;
                            $timelineCanOpenBooking = $viewerCanManageBookings || ($timelineBooking->isManualBooking() && $timelineBooking->affiliatePartnership?->marketer_id === auth()->id());
                            $timelineLabel = $timelineCanOpenBooking ? ($timelineBooking->isManualBooking() ? $timelineBooking->sourceLabel() : $timelineBooking->customerDisplayName()) : 'Reserved';
                        @endphp
                        <a @class(['listing-timeline-booking', 'category-'.$unitMeta['theme'], 'status-'.($viewerCanManageBookings ? $timelineBooking->status : 'reserved')])
                           style="grid-column: {{ $timelineStartColumn }} / {{ $timelineEndColumn }}; grid-row: {{ $timelineRow }}; --unit-accent: {{ $unitStyle['accent'] ?? '#64748b' }}; --unit-soft: {{ $unitStyle['soft'] ?? '#f1f5f9' }}; --unit-fill: {{ $unitStyle['fill'] ?? '#f1f5f9' }}; --unit-ink: {{ $unitStyle['ink'] ?? '#334155' }};"
                           data-booking-id="{{ $timelineBooking->id }}"
                           @if($timelineCanOpenBooking)
                               data-calendar-booking-open data-unit="{{ $unit->name }}" data-category="{{ $unitMeta['label'] }}" data-category-icon="{{ $unitMeta['icon'] }}"
                               data-client="{{ $timelineBooking->customerDisplayName() }}" data-status="{{ $timelineBooking->statusLabel() }}" data-status-key="{{ $timelineBooking->status }}"
                               data-start="{{ $timelineBooking->start_at->format('M j, Y · g:i A') }}" data-end="{{ $timelineBooking->end_at->format('M j, Y · g:i A') }}"
                               data-party-size="{{ number_format($timelineBooking->party_size) }}" data-total="₱{{ number_format($timelineBooking->total_amount, 2) }}"
                               data-source="{{ $timelineBooking->isManualBooking() ? $timelineBooking->sourceDisplayLabel() : '' }}"
                               data-notes="{{ $timelineBooking->notes }}" data-booking-url="{{ route('bookings.show', $timelineBooking) }}"
                               href="{{ route('bookings.show', $timelineBooking) }}"
                           @else
                               aria-label="Reserved from {{ $timelineBooking->start_at->format('M j, g:i A') }} to {{ $timelineBooking->end_at->format('M j, g:i A') }}"
                           @endif
                           title="{{ $timelineCanOpenBooking ? $timelineBooking->statusLabel().': '.$timelineBooking->customerDisplayName() : 'Reserved' }} · {{ $timelineBooking->start_at->format('M j, g:i A') }} to {{ $timelineBooking->end_at->format('M j, g:i A') }}">
                            <span aria-hidden="true">{{ $timelineCanOpenBooking ? $unitMeta['icon'] : '●' }}</span><strong>{{ $timelineLabel }}</strong>
                            @if ($timelineCanOpenBooking)<small>{{ $timelineBooking->start_at->format('M j') }}–{{ $timelineBooking->end_at->format('M j') }}</small>@endif
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
        <p class="listing-timeline-help"><span aria-hidden="true">↔</span> Scroll sideways to see every date. Select an open date to review that day.</p>
    @endif
</section>
