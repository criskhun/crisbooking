<details class="manual-booking-card" data-manual-booking-card @if($errors->hasAny(['unit_id', 'start_date', 'number_of_days', 'source_channel', 'source_details', 'external_customer_name', 'total_amount', 'party_size', 'affiliate_partnership_id', 'notes'])) open @endif>
    <summary>
        <span aria-hidden="true">＋</span>
        <div>
            <strong>Add an outside booking</strong>
            <small>Block an owned or assigned listing and include the sale in your records.</small>
        </div>
        <b>Add booking</b>
    </summary>
    <form method="POST" action="{{ route('calendar.manual-bookings.store') }}" class="manual-booking-form" data-manual-booking-form data-loading-label="Adding outside booking…">
        @csrf
        <div class="manual-booking-intro">
            <span>{{ $isAffiliateCalendar ? 'Affiliate entry' : 'Host entry' }}</span>
            <p>Use this for Airbnb, Booking.com, Agoda, direct, vehicle, service, or offline affiliate sales. Current and previous dates are allowed here, and the amount is included in your sales dashboard.</p>
        </div>
        <div class="field-group manual-booking-wide">
            <label for="manual_unit_id">Listing, vehicle, or service</label>
            <select id="manual_unit_id" name="unit_id" required data-manual-booking-unit>
                <option value="">Choose a listing</option>
                @foreach($scheduleUnits as $manualUnit)
                    <option value="{{ $manualUnit->id }}" data-category="{{ str($manualUnit->category)->replace('_', ' ')->title() }}" @selected((int) old('unit_id') === $manualUnit->id)>{{ $manualUnit->name }} · {{ str($manualUnit->category)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            @error('unit_id')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_start_date">First occupied date</label>
            <input id="manual_start_date" name="start_date" type="date" value="{{ old('start_date', $selectedDate->toDateString()) }}" required data-manual-booking-start>
            <small class="field-help">Past dates are available only for recording outside bookings that already happened.</small>
            @error('start_date')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_number_of_days">Number of days</label>
            <input id="manual_number_of_days" name="number_of_days" type="number" min="1" max="365" value="{{ old('number_of_days', 1) }}" required data-manual-booking-days>
            @error('number_of_days')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="manual-booking-duration" aria-live="polite" data-manual-booking-duration>1 day will be blocked.</div>
        <div class="field-group">
            <label for="manual_source_channel">Sales source</label>
            <select id="manual_source_channel" name="source_channel" required>
                @foreach($manualBookingSourceOptions as $sourceValue => $sourceLabel)
                    <option value="{{ $sourceValue }}" @selected(old('source_channel', $isAffiliateCalendar ? 'affiliate' : 'direct') === $sourceValue)>{{ $sourceLabel }}</option>
                @endforeach
            </select>
            @error('source_channel')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_source_details">Platform, agent, or reference <span class="optional-label">Optional</span></label>
            <input id="manual_source_details" name="source_details" type="text" maxlength="160" value="{{ old('source_details') }}" placeholder="Confirmation number or source name">
            @error('source_details')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        @if(!$isAffiliateCalendar && $manualBookingPartnerships->isNotEmpty())
            <div class="field-group manual-booking-wide">
                <label for="manual_affiliate_partnership_id">Affiliate credit <span class="optional-label">Optional</span></label>
                <select id="manual_affiliate_partnership_id" name="affiliate_partnership_id" data-manual-booking-affiliate>
                    <option value="">No affiliate</option>
                    @foreach($manualBookingPartnerships as $manualPartnership)
                        <option value="{{ $manualPartnership->id }}" data-unit-ids="{{ $manualPartnership->units->pluck('id')->join(',') }}" @selected((int) old('affiliate_partnership_id') === $manualPartnership->id)>{{ $manualPartnership->marketer->name }} · {{ number_format((float) $manualPartnership->commission_percentage, 2) }}% commission</option>
                    @endforeach
                </select>
                <small class="field-help">Only affiliates assigned to the selected listing can be credited.</small>
                @error('affiliate_partnership_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="field-group">
            <label for="manual_external_customer_name">Customer or company <span class="optional-label">Optional</span></label>
            <input id="manual_external_customer_name" name="external_customer_name" type="text" maxlength="120" value="{{ old('external_customer_name') }}" placeholder="Name used in the external booking">
            @error('external_customer_name')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_total_amount">Recorded sale amount</label>
            <div class="money-input"><span>₱</span><input id="manual_total_amount" name="total_amount" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('total_amount', 0) }}" required></div>
            @error('total_amount')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_party_size">Guests, passengers, or quantity</label>
            <input id="manual_party_size" name="party_size" type="number" min="1" max="10000" value="{{ old('party_size', 1) }}">
            @error('party_size')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group manual-booking-wide">
            <label for="manual_notes">Internal notes <span class="optional-label">Optional</span></label>
            <textarea id="manual_notes" name="notes" rows="3" maxlength="1000" placeholder="Pickup, check-in, customer contact reference, or other internal notes">{{ old('notes') }}</textarea>
            @error('notes')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="manual-booking-submit">
            <small>Saved as confirmed so these dates become unavailable immediately.</small>
            <button class="button button-primary" type="submit">Add & block dates</button>
        </div>
    </form>
</details>
