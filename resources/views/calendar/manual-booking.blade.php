<details class="manual-booking-card" data-manual-booking-card @if($errors->hasAny(['unit_id', 'start_date', 'start_time', 'duration_unit', 'duration_quantity', 'source_channel', 'source_details', 'external_customer_name', 'fulfillment_method', 'delivery_address', 'total_amount', 'payment_option', 'initial_payment_amount', 'security_deposit_amount', 'party_size', 'affiliate_partnership_id', 'notes'])) open @endif>
    <summary>
        <span aria-hidden="true">＋</span>
        <div>
            <strong>Add an outside booking</strong>
            <small>Block an owned or assigned listing’s exact schedule and include the sale in your records.</small>
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
                    <option value="{{ $manualUnit->id }}" data-category="{{ str($manualUnit->category)->replace('_', ' ')->title() }}" data-unit-category="{{ $manualUnit->category }}" data-fulfillment-options="{{ collect($manualUnit->car_details['fulfillment_options'] ?? ['pickup'])->join(',') }}" data-start-time="{{ $manualUnit->category === 'condo' ? $manualUnit->condoCheckInTime() : '' }}" data-end-time="{{ $manualUnit->category === 'condo' ? $manualUnit->condoCheckOutTime() : '' }}" @selected((int) old('unit_id') === $manualUnit->id)>{{ $manualUnit->name }} · {{ str($manualUnit->category)->replace('_', ' ')->title() }}</option>
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
            <label for="manual_start_time">Pickup or start time</label>
            <input id="manual_start_time" name="start_time" type="time" value="{{ old('start_time', '12:00') }}" required data-manual-booking-time>
            <small class="field-help" data-manual-booking-time-help>Daily bookings end at this time on the final date; hourly bookings use the exact number of hours.</small>
            @error('start_time')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_duration_unit">Charge duration by</label>
            <select id="manual_duration_unit" name="duration_unit" required data-manual-booking-duration-unit><option value="day" @selected(old('duration_unit', 'day') === 'day')>Daily</option><option value="hour" @selected(old('duration_unit') === 'hour')>Hourly</option></select>
            @error('duration_unit')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_duration_quantity" data-manual-booking-quantity-label>Number of days</label>
            <input id="manual_duration_quantity" name="duration_quantity" type="number" min="1" max="365" value="{{ old('duration_quantity', old('number_of_days', 1)) }}" required data-manual-booking-quantity>
            <small class="field-help" data-manual-booking-quantity-help>Maximum 365 days.</small>
            @error('duration_quantity')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="manual-booking-duration" aria-live="polite" data-manual-booking-duration>1 day will be blocked.</div>
        <div class="field-group" data-manual-fulfillment hidden>
            <label for="manual_fulfillment_method">Vehicle handover</label>
            <select id="manual_fulfillment_method" name="fulfillment_method" data-fulfillment-method><option value="pickup" @selected(old('fulfillment_method') === 'pickup')>Customer pickup</option><option value="delivery" @selected(old('fulfillment_method') === 'delivery')>Deliver to customer</option></select>
            @error('fulfillment_method')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group" data-manual-delivery-address hidden>
            <label for="manual_delivery_address">Delivery address</label>
            <input id="manual_delivery_address" name="delivery_address" maxlength="500" value="{{ old('delivery_address') }}" placeholder="Complete delivery location">
            @error('delivery_address')<p class="error-text">{{ $message }}</p>@enderror
        </div>
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
            <input id="manual_external_customer_name" name="external_customer_name" type="text" maxlength="120" value="{{ old('external_customer_name') }}" placeholder="Start typing a previous customer or enter a new name" list="manual_customer_suggestions" autocomplete="off">
            <datalist id="manual_customer_suggestions">
                @foreach($manualBookingCustomerSuggestions as $customerSuggestion)
                    <option value="{{ $customerSuggestion['name'] }}" label="{{ $customerSuggestion['booking_count'] }} previous {{ str('booking')->plural($customerSuggestion['booking_count']) }}"></option>
                @endforeach
            </datalist>
            <small class="field-help">Start typing to select a customer or company from your previous outside bookings.</small>
            @error('external_customer_name')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_total_amount">Recorded sale amount</label>
            <div class="money-input"><span>₱</span><input id="manual_total_amount" name="total_amount" type="text" inputmode="decimal" value="{{ number_format((float) old('total_amount', 0), 2, '.', ',') }}" required data-accounting-input></div>
            @error('total_amount')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_payment_option">Booking payment</label>
            <select id="manual_payment_option" name="payment_option" required data-manual-payment-option>
                <option value="fully_paid" @selected(old('payment_option') === 'fully_paid')>Fully paid</option>
                <option value="downpayment" @selected(old('payment_option', 'downpayment') === 'downpayment')>Downpayment / reservation only</option>
                <option value="unpaid" @selected(old('payment_option') === 'unpaid')>Not yet paid</option>
            </select>
            @error('payment_option')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group" data-manual-downpayment>
            <label for="manual_initial_payment_amount">Downpayment collected</label>
            <div class="money-input"><span>₱</span><input id="manual_initial_payment_amount" name="initial_payment_amount" type="text" inputmode="decimal" value="{{ filled(old('initial_payment_amount')) ? number_format((float) old('initial_payment_amount'), 2, '.', ',') : '' }}" data-accounting-input></div>
            @error('initial_payment_amount')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="manual_security_deposit_amount">Refundable security deposit <span class="optional-label">Optional</span></label>
            <div class="money-input"><span>₱</span><input id="manual_security_deposit_amount" name="security_deposit_amount" type="text" inputmode="decimal" value="{{ number_format((float) old('security_deposit_amount', 0), 2, '.', ',') }}" data-accounting-input></div>
            @error('security_deposit_amount')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <label class="availability-toggle"><input type="hidden" name="security_deposit_collected" value="0"><input type="checkbox" name="security_deposit_collected" value="1" @checked(old('security_deposit_collected'))><span><strong>Security deposit already collected</strong><small>Keep this separate from sales until it is returned or applied to charges.</small></span></label>
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
            <small>Saved as confirmed so this exact date and time range becomes unavailable immediately.</small>
            <button class="button button-primary" type="submit">Add & block time</button>
        </div>
    </form>
</details>
