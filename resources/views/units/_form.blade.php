@php
    $editing = isset($unit);
    $rentalRates = $editing ? $unit->rates->where('coverage', 'standard')->keyBy('period') : collect();
    $defaultOfferedRates = $rentalRates->isNotEmpty() ? $rentalRates->keys()->all() : ['12_hours', 'day', 'week', 'month'];
    $offeredRates = old('offered_rates', $defaultOfferedRates);
    $carCoverageOptions = [
        'within_city' => ['Within-city use', 'Driving stays inside the pickup city.'],
        'out_of_town' => ['Out-of-town use', 'The vehicle may travel beyond city limits.'],
    ];
    $storedCarRates = collect();
    if ($editing) {
        $withinCityRates = $unit->rates->where('coverage', 'within_city');
        if ($withinCityRates->isEmpty()) {
            $withinCityRates = $unit->rates->where('coverage', 'standard');
        }
        if ($withinCityRates->isNotEmpty()) {
            $storedCarRates->put('within_city', $withinCityRates);
        }
        $outOfTownRates = $unit->rates->where('coverage', 'out_of_town');
        if ($outOfTownRates->isNotEmpty()) {
            $storedCarRates->put('out_of_town', $outOfTownRates);
        }
    }
    $defaultCarRateAreas = $storedCarRates->isNotEmpty() ? $storedCarRates->keys()->all() : ['within_city'];
    $carRateAreas = old('car_rate_areas', $defaultCarRateAreas);
    $defaultCarOfferedRates = collect(array_keys($carCoverageOptions))->mapWithKeys(function ($coverage) use ($storedCarRates) {
        $periods = $storedCarRates->get($coverage, collect())->pluck('period')->all();
        return [$coverage => count($periods) ? $periods : ['12_hours', 'day', 'week', 'month']];
    })->all();
    $carOfferedRates = old('car_offered_rates', $defaultCarOfferedRates);
    $carDetails = old('car', $unit->car_details ?? []);
    $carFulfillmentOptions = old('car_fulfillment_options', $unit->car_details['fulfillment_options'] ?? ['pickup']);
    $carAccessories = old('car_accessories', $unit->car_details['accessories'] ?? []);
    $customAccessories = old('custom_accessories', $unit->car_details['custom_accessories'] ?? []);
    $storedCarCharges = $unit->car_details['charges'] ?? [];
    $defaultCarCharges = collect(['car_wash', 'delivery', 'deposit'])->mapWithKeys(fn ($key) => [$key => ['enabled' => isset($storedCarCharges[$key]), 'amount' => $storedCarCharges[$key]['amount'] ?? '']])->all();
    $carCharges = old('car_charges', $defaultCarCharges);
    $gpsDetails = old('gps', $unit->gps_details ?? []);
    $propertyDetails = old('property', $unit->property_details ?? []);
    $propertyAmenities = old('property_amenities', $unit->property_details['amenities'] ?? []);
    $wifiDetails = old('wifi', $unit->wifi_details ?? []);
    $parkingDetails = old('parking', $unit->property_details['parking'] ?? ['payment_type' => 'included']);
    $poolDetails = old('pool', $unit->property_details['pool'] ?? ['payment_type' => 'included']);
    $latitudeValue = old('latitude', $unit->latitude ?? null);
    $longitudeValue = old('longitude', $unit->longitude ?? null);
    $draftPhotoPaths = $editing ? [] : ($draftPhotoPaths ?? []);
    $draftPrimaryIndex = array_search($draftPrimaryPhotoPath ?? null, $draftPhotoPaths, true);
    $draftPrimaryValue = $draftPrimaryIndex === false ? null : 'draft:'.$draftPrimaryIndex;
    $hasStoredPhotos = ($editing && $unit->images->isNotEmpty()) || count($draftPhotoPaths) > 0;
    $assetCategories = ['car' => 'Car rental', 'condo' => 'Condo rental'];
    $serviceCategories = ['cleaning' => 'Cleaning', 'laundry' => 'Laundry', 'delivery' => 'Vehicle delivery', 'car_wash' => 'Carwash', 'vehicle_maintenance' => 'Vehicle maintenance', 'driving' => 'Driving', 'massage' => 'Massage', 'consultancy' => 'Consultancy', 'other' => 'Other'];
    $selectedKind = old('kind', $unit->kind ?? 'unit') === 'service' ? 'service' : 'unit';
    $storedCategory = old('category', $unit->category ?? 'car');
    if ($selectedKind === 'unit') {
        $selectedCategory = array_key_exists($storedCategory, $assetCategories) ? $storedCategory : 'car';
        $customCategoryDefault = '';
    } elseif (array_key_exists($storedCategory, $serviceCategories)) {
        $selectedCategory = $storedCategory;
        $customCategoryDefault = '';
    } elseif (! array_key_exists($storedCategory, $assetCategories) && filled($storedCategory)) {
        $selectedCategory = 'other';
        $customCategoryDefault = str($storedCategory)->replace('_', ' ')->title();
    } else {
        $selectedCategory = 'cleaning';
        $customCategoryDefault = '';
    }
    $customCategory = old('custom_category', $customCategoryDefault);
    $customCalendarColor = (bool) old('calendar_color_enabled', filled($unit->calendar_color ?? null));
    $calendarColor = old('calendar_color', $unit->calendar_color ?? '#2563eb');
    $calendarUseGradient = (bool) old('calendar_use_gradient', $unit->calendar_use_gradient ?? false);
    $calendarSecondaryColor = old('calendar_secondary_color', $unit->calendar_secondary_color ?? '#7c3aed');
@endphp

<div class="listing-form-grid">
    <div class="field-group listing-photo-field">
        <label for="photos">Rental photo gallery</label>
        @if ($editing && $unit->images->isNotEmpty())
            <div class="existing-photo-grid">
                @foreach ($unit->images as $image)
                    @php($primaryValue = 'existing:'.$image->id)
                    <div class="existing-photo" data-photo-card>
                        <img src="{{ Storage::disk('public')->url($image->path) }}" alt="Photo {{ $loop->iteration }} of {{ $unit->name }}">
                        <div class="photo-controls">
                            <label class="photo-choice primary-choice"><input type="radio" name="primary_image" value="{{ $primaryValue }}" @checked(old('primary_image', 'existing:'.$unit->images->first()->id) === $primaryValue) data-primary-image> <span>Primary</span></label>
                            <label class="photo-choice remove-choice"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, old('remove_images', []))) data-remove-image> <span>Remove</span></label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @if (! $editing && count($draftPhotoPaths) > 0)
            <div class="existing-photo-grid" data-draft-photo-grid>
                @foreach ($draftPhotoPaths as $draftPhotoIndex => $draftPhotoPath)
                    @php($primaryValue = 'draft:'.$draftPhotoIndex)
                    <div class="existing-photo" data-photo-card data-draft-photo-card>
                        <img src="{{ Storage::disk('public')->url($draftPhotoPath) }}" alt="Saved draft photo {{ $loop->iteration }}">
                        <div class="photo-controls">
                            <label class="photo-choice primary-choice"><input type="radio" name="primary_image" value="{{ $primaryValue }}" @checked(old('primary_image', $draftPrimaryValue) === $primaryValue) data-primary-image> <span>Primary</span></label>
                            <label class="photo-choice remove-choice"><input type="checkbox" name="remove_draft_photos[]" value="{{ $draftPhotoIndex }}" @checked(in_array($draftPhotoIndex, old('remove_draft_photos', []))) data-remove-image> <span>Remove</span></label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="new-photo-grid" data-photo-preview-grid></div>
        <label class="photo-upload photo-upload-multiple" for="photos">
            <span><strong>Add photos</strong><small>Select multiple JPG, PNG, or WebP images · maximum 5 MB each</small></span>
        </label>
        <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple {{ $hasStoredPhotos ? '' : 'required' }} data-photo-input>
        @error('photos')<p class="error-text">{{ $message }}</p>@enderror
        @error('photos.*')<p class="error-text">{{ $message }}</p>@enderror
        @error('primary_image')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group listing-name-field">
        <label for="name">Listing name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $unit->name ?? '') }}" required maxlength="120" placeholder="e.g. Toyota Vios 2025">
        @error('name')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group">
        <label for="kind">Listing type</label>
        <select id="kind" name="kind" required>
            <option value="unit" @selected(old('kind', $unit->kind ?? 'unit') === 'unit')>Unit — a bookable asset</option>
            <option value="service" @selected(old('kind', $unit->kind ?? '') === 'service')>Service — bookable time</option>
        </select>
        @error('kind')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group">
        <label for="category">Category</label>
        <select id="category" name="category" required>
            <optgroup label="Rentals" data-category-group="unit" @if($selectedKind !== 'unit') disabled hidden @endif>
                @foreach ($assetCategories as $value => $label)
                    <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                @endforeach
            </optgroup>
            <optgroup label="Services" data-category-group="service" @if($selectedKind !== 'service') disabled hidden @endif>
                @foreach ($serviceCategories as $value => $label)
                    <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                @endforeach
            </optgroup>
        </select>
        @error('category')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group" data-custom-category-section @if(! ($selectedKind === 'service' && $selectedCategory === 'other')) hidden @endif>
        <label for="custom_category">Add service category</label>
        <input id="custom_category" name="custom_category" type="text" value="{{ $customCategory }}" maxlength="30" placeholder="e.g. Lawn care" @required($selectedKind === 'service' && $selectedCategory === 'other')>
        <small class="field-help">Enter the service category when it is not listed above.</small>
        @error('custom_category')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <section class="listing-calendar-color-field" data-calendar-color-picker>
        <div class="listing-calendar-color-heading">
            <div>
                <span class="eyebrow">Calendar appearance</span>
                <strong>Choose this listing's color</strong>
                <small>Use a solid shade or blend two shades with a gradient. Leave this off to keep the automatic category-based unit color.</small>
            </div>
            <div class="listing-calendar-color-preview" data-calendar-color-preview aria-label="Calendar color preview">
                <span>Booking preview</span>
            </div>
        </div>

        <input type="hidden" name="calendar_color_enabled" value="0">
        <label class="listing-calendar-color-toggle">
            <input type="checkbox" name="calendar_color_enabled" value="1" @checked($customCalendarColor) data-calendar-color-enabled>
            <span><strong>Use my selected color</strong><small>Override the automatic shade for this unit.</small></span>
        </label>

        <div class="listing-calendar-color-controls" data-calendar-color-controls>
            <label class="listing-calendar-color-choice" for="calendar_color">
                <span>Primary shade</span>
                <input id="calendar_color" name="calendar_color" type="color" value="{{ $calendarColor }}" data-calendar-color-primary>
                <output data-calendar-color-primary-value>{{ $calendarColor }}</output>
            </label>

            <input type="hidden" name="calendar_use_gradient" value="0">
            <label class="listing-calendar-color-toggle compact">
                <input type="checkbox" name="calendar_use_gradient" value="1" @checked($calendarUseGradient) data-calendar-gradient-enabled>
                <span><strong>Gradient color</strong><small>Blend a second shade into the booking bar.</small></span>
            </label>

            <label class="listing-calendar-color-choice" for="calendar_secondary_color" data-calendar-secondary-wrap>
                <span>Second shade</span>
                <input id="calendar_secondary_color" name="calendar_secondary_color" type="color" value="{{ $calendarSecondaryColor }}" data-calendar-color-secondary>
                <output data-calendar-color-secondary-value>{{ $calendarSecondaryColor }}</output>
            </label>
        </div>
        @error('calendar_color')<p class="error-text">{{ $message }}</p>@enderror
        @error('calendar_secondary_color')<p class="error-text">{{ $message }}</p>@enderror
    </section>

    <div class="field-group listing-location-field" data-listing-location-map data-map-id="{{ config('services.google.maps_map_id') }}">
        <div class="map-field-heading"><div><label for="location">Listing location <span class="optional-label">Optional</span></label><small>Search an address, use your position, or click the map to pin the unit.</small></div><span data-map-coordinate-label>{{ $latitudeValue !== null && $longitudeValue !== null ? number_format((float) $latitudeValue, 5).', '.number_format((float) $longitudeValue, 5) : 'No map pin yet' }}</span></div>
        <div class="map-address-row"><input id="location" name="location" type="text" value="{{ old('location', $unit->location ?? '') }}" maxlength="180" placeholder="City, pickup point, or exact address" data-map-address><button class="map-action-button" type="button" data-map-find-address>Find on map</button><button class="map-action-button" type="button" data-map-use-location>Use my location</button></div>
        <input name="latitude" type="hidden" value="{{ $latitudeValue }}" data-map-latitude>
        <input name="longitude" type="hidden" value="{{ $longitudeValue }}" data-map-longitude>
        <div class="google-map-canvas listing-map-canvas" data-map-canvas aria-label="Google map location picker"></div>
        @unless(config('services.google.maps_api_key'))<div class="map-setup-note"><strong>Map preview needs configuration</strong><span>Add <code>GOOGLE_MAPS_API_KEY</code> to the environment. The address can still be saved without a pin.</span></div>@endunless
        <small class="map-status" data-map-status aria-live="polite"></small>
        @error('location')<p class="error-text">{{ $message }}</p>@enderror
        @error('latitude')<p class="error-text">{{ $message }}</p>@enderror
        @error('longitude')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="standard-rate-fields" data-standard-rate-section>
        <div class="field-group">
            <label for="price">Price (PHP)</label>
            <input id="price" name="price" type="number" value="{{ old('price', $unit->price ?? '') }}" min="0" max="9999999999.99" step="0.01" placeholder="0.00">
            @error('price')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="field-group">
            <label for="pricing_unit">Charge per</label>
            <select id="pricing_unit" name="pricing_unit">
                @foreach (['hour' => 'Hour', 'day' => 'Day', 'session' => 'Session / booking'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('pricing_unit', $unit->pricing_unit ?? 'day') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('pricing_unit')<p class="error-text">{{ $message }}</p>@enderror
        </div>
    </div>

    <fieldset class="rental-rate-fields" data-package-rate-section>
        <legend><span class="eyebrow">Rental pricing</span><strong>Enable only the packages you offer</strong></legend>

        <div data-condo-rate-set>
            @error('offered_rates')<p class="error-text">{{ $message }}</p>@enderror
            @foreach (['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'] as $period => $label)
                <div class="field-group rental-rate-option" data-rate-option>
                    <label class="rate-offer-toggle"><input type="checkbox" name="offered_rates[]" value="{{ $period }}" @checked(in_array($period, $offeredRates)) data-rate-toggle><span>{{ $label }}</span></label>
                    <label class="sr-only" for="rate_{{ $period }}">{{ $label }} price</label>
                    <div class="currency-input"><span>₱</span><input id="rate_{{ $period }}" name="rates[{{ $period }}]" type="number" value="{{ old('rates.'.$period, $rentalRates->get($period)?->price) }}" min="0" max="9999999999.99" step="0.01" placeholder="0.00"></div>
                    @error('rates.'.$period)<p class="error-text">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div class="car-coverage-rates" data-car-rate-sets>
            <div class="detail-subheading"><strong>Rental coverage</strong><small>Set different package prices based on where the renter may drive.</small></div>
            @error('car_rate_areas')<p class="error-text">{{ $message }}</p>@enderror
            @foreach ($carCoverageOptions as $coverage => [$coverageLabel, $coverageHelp])
                @php($coverageRates = $storedCarRates->get($coverage, collect())->keyBy('period'))
                <section class="rental-coverage-card" data-car-rate-coverage>
                    <label class="rental-coverage-toggle">
                        <input type="checkbox" name="car_rate_areas[]" value="{{ $coverage }}" @checked(in_array($coverage, $carRateAreas)) data-car-rate-area-toggle>
                        <span><strong>{{ $coverageLabel }}</strong><small>{{ $coverageHelp }}</small></span>
                    </label>
                    <div class="rental-coverage-options" data-car-rate-options>
                        @foreach (['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'] as $period => $label)
                            <div class="field-group rental-rate-option" data-rate-option>
                                <label class="rate-offer-toggle"><input type="checkbox" name="car_offered_rates[{{ $coverage }}][]" value="{{ $period }}" @checked(in_array($period, $carOfferedRates[$coverage] ?? [])) data-rate-toggle><span>{{ $label }}</span></label>
                                <label class="sr-only" for="car_rate_{{ $coverage }}_{{ $period }}">{{ $coverageLabel }} {{ $label }} price</label>
                                <div class="currency-input"><span>₱</span><input id="car_rate_{{ $coverage }}_{{ $period }}" name="car_rates[{{ $coverage }}][{{ $period }}]" type="number" value="{{ old('car_rates.'.$coverage.'.'.$period, $coverageRates->get($period)?->price) }}" min="0" max="9999999999.99" step="0.01" placeholder="0.00"></div>
                                @error('car_rates.'.$coverage.'.'.$period)<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    @error('car_offered_rates.'.$coverage)<p class="error-text">{{ $message }}</p>@enderror
                </section>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="category-detail-fields car-detail-fields" data-car-details-section>
        <legend><span class="eyebrow">Vehicle details</span><strong>Car specifications and accessories</strong></legend>
        <div class="detail-field-grid">
            <div class="field-group"><label for="car_make">Make</label><input id="car_make" name="car[make]" value="{{ $carDetails['make'] ?? '' }}" maxlength="80" placeholder="Toyota">@error('car.make')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="car_model">Model</label><input id="car_model" name="car[model]" value="{{ $carDetails['model'] ?? '' }}" maxlength="80" placeholder="Vios">@error('car.model')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="car_year">Year</label><input id="car_year" name="car[year]" type="number" value="{{ $carDetails['year'] ?? '' }}" min="1900" max="{{ now()->year + 2 }}" placeholder="{{ now()->year }}">@error('car.year')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="transmission">Transmission</label><select id="transmission" name="car[transmission]"><option value="automatic" @selected(($carDetails['transmission'] ?? '') === 'automatic')>Automatic</option><option value="manual" @selected(($carDetails['transmission'] ?? '') === 'manual')>Manual</option></select>@error('car.transmission')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="fuel_type">Fuel type</label><select id="fuel_type" name="car[fuel_type]">@foreach(['gasoline' => 'Gasoline', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric'] as $value => $label)<option value="{{ $value }}" @selected(($carDetails['fuel_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>@error('car.fuel_type')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="car_color">Color</label><input id="car_color" name="car[color]" list="car-color-options" value="{{ $carDetails['color'] ?? '' }}" maxlength="50" placeholder="Select or type a color"><datalist id="car-color-options">@foreach(['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Brown', 'Beige', 'Green', 'Yellow', 'Orange', 'Gold'] as $color)<option value="{{ $color }}">@endforeach</datalist>@error('car.color')<p class="error-text">{{ $message }}</p>@enderror</div>
        </div>
        <div class="detail-subheading"><strong>Included accessories</strong><small>Select the common accessories that come with this car.</small></div>
        <div class="option-check-grid">
            @foreach (['air_conditioning' => 'Air conditioning', 'bluetooth' => 'Bluetooth', 'usb_charger' => 'USB charger', 'dashcam' => 'Dashcam', 'gps' => 'GPS', 'child_seat' => 'Child seat', 'roof_rack' => 'Roof rack', 'reverse_camera' => 'Reverse camera', 'toll_tag' => 'Toll tag', 'phone_holder' => 'Phone holder'] as $value => $label)
                <label><input type="checkbox" name="car_accessories[]" value="{{ $value }}" @checked(in_array($value, $carAccessories)) @if($value === 'gps') data-gps-accessory @endif><span>{{ $label }}</span></label>
            @endforeach
        </div>
        <div class="detail-subheading"><strong>Vehicle handover options</strong><small>Choose whether renters collect the car, request delivery, or can choose either method.</small></div>
        <div class="option-check-grid">
            <label><input type="checkbox" name="car_fulfillment_options[]" value="pickup" @checked(in_array('pickup', $carFulfillmentOptions))><span>Customer pickup</span></label>
            <label><input type="checkbox" name="car_fulfillment_options[]" value="delivery" @checked(in_array('delivery', $carFulfillmentOptions))><span>Deliver to customer</span></label>
        </div>
        @error('car_fulfillment_options')<p class="error-text">{{ $message }}</p>@enderror
        <div class="custom-accessory-panel" data-custom-accessories>
            <div class="private-detail-heading"><span><x-fa-icon name="plus" /></span><div><strong>Other included accessories</strong><small>Add equipment that is not in the standard checklist.</small></div><button class="map-action-button" type="button" data-add-accessory>Add accessory</button></div>
            <div class="custom-accessory-list" data-accessory-list>
                @foreach(count($customAccessories) ? $customAccessories : [''] as $accessory)
                    <div class="custom-accessory-row"><input name="custom_accessories[]" value="{{ $accessory }}" maxlength="80" placeholder="e.g. Portable tire inflator"><button class="icon-only-button" type="button" data-remove-accessory aria-label="Remove accessory"><x-fa-icon name="xmark" /></button></div>
                @endforeach
            </div>
            @error('custom_accessories.*')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="car-charge-panel" data-car-charges>
            <div class="private-detail-heading"><span>₱</span><div><strong>Required car charges</strong><small>The host decides which charges apply. Enabled charges are added automatically to every booking.</small></div></div>
            <div class="car-charge-grid">
                @foreach(['car_wash' => ['Car wash', 'Cleaning charge added per booking.'], 'delivery' => ['Delivery', 'Vehicle delivery or pickup charge per booking.'], 'deposit' => ['Refundable deposit', 'Security deposit collected with the booking.']] as $chargeKey => [$chargeLabel, $chargeHelp])
                    @php($charge = $carCharges[$chargeKey] ?? ['enabled' => false, 'amount' => ''])
                    <div class="car-charge-card" data-car-charge>
                        <label><input type="hidden" name="car_charges[{{ $chargeKey }}][enabled]" value="0"><input type="checkbox" name="car_charges[{{ $chargeKey }}][enabled]" value="1" @checked(filter_var($charge['enabled'] ?? false, FILTER_VALIDATE_BOOL)) data-car-charge-toggle><span><strong>{{ $chargeLabel }}</strong><small>{{ $chargeHelp }}</small></span></label>
                        <div class="car-charge-amount-field" data-car-charge-amount>
                            <label for="car_charge_{{ $chargeKey }}">Amount (PHP)</label>
                            <div class="currency-input"><span>₱</span><input id="car_charge_{{ $chargeKey }}" name="car_charges[{{ $chargeKey }}][amount]" type="number" value="{{ $charge['amount'] ?? '' }}" min="0" max="9999999999.99" step="0.01" placeholder="0.00"></div>
                        </div>
                        @error('car_charges.'.$chargeKey.'.amount')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </div>
        <div class="private-detail-panel" data-gps-details-section>
            <div class="private-detail-heading"><span><x-fa-icon name="lock" /></span><div><strong>Private GPS access</strong><small>Encrypted and visible only in the host’s listing manager.</small></div></div>
            <div class="detail-field-grid">
                <div class="field-group"><label for="gps_device_name">GPS device or app name</label><input id="gps_device_name" name="gps[device_name]" value="{{ $gpsDetails['device_name'] ?? '' }}" maxlength="120" placeholder="e.g. SinoTrack GPS">@error('gps.device_name')<p class="error-text">{{ $message }}</p>@enderror</div>
                <div class="field-group"><label for="gps_login_url">Login website <span class="optional-label">Optional</span></label><input id="gps_login_url" name="gps[login_url]" type="url" value="{{ $gpsDetails['login_url'] ?? '' }}" maxlength="500" placeholder="https://…">@error('gps.login_url')<p class="error-text">{{ $message }}</p>@enderror</div>
                <div class="field-group"><label for="gps_username">GPS username</label><input id="gps_username" name="gps[username]" value="{{ $gpsDetails['username'] ?? '' }}" maxlength="190" autocomplete="off">@error('gps.username')<p class="error-text">{{ $message }}</p>@enderror</div>
                <div class="field-group"><label for="gps_password">GPS password</label><input id="gps_password" name="gps[password]" type="password" value="{{ $gpsDetails['password'] ?? '' }}" maxlength="500" autocomplete="new-password"><button class="field-reveal-button" type="button" data-password-reveal>Show password</button>@error('gps.password')<p class="error-text">{{ $message }}</p>@enderror</div>
                <div class="field-group gps-notes-field"><label for="gps_notes">Private GPS notes <span class="optional-label">Optional</span></label><textarea id="gps_notes" name="gps[notes]" rows="3" maxlength="1000" placeholder="Tracker ID, setup notes, or recovery details…">{{ $gpsDetails['notes'] ?? '' }}</textarea>@error('gps.notes')<p class="error-text">{{ $message }}</p>@enderror</div>
            </div>
        </div>
    </fieldset>

    <fieldset class="category-detail-fields property-detail-fields" data-property-details-section>
        <legend><span class="eyebrow">Property details</span><strong>Rooms, comfort rooms, and amenities</strong></legend>
        <div class="detail-field-grid">
            <div class="field-group"><label for="property_type">Property type</label><select id="property_type" name="property[type]">@foreach(['condo' => 'Condominium', 'apartment' => 'Apartment', 'house' => 'House', 'villa' => 'Villa', 'room' => 'Private room'] as $value => $label)<option value="{{ $value }}" @selected(($propertyDetails['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>@error('property.type')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="bedrooms">Bedrooms / rooms</label><input id="bedrooms" name="property[bedrooms]" type="number" value="{{ $propertyDetails['bedrooms'] ?? '' }}" min="0" max="100" placeholder="1">@error('property.bedrooms')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="bathrooms">Comfort rooms</label><input id="bathrooms" name="property[bathrooms]" type="number" value="{{ $propertyDetails['bathrooms'] ?? '' }}" min="1" max="100" placeholder="1">@error('property.bathrooms')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="beds">Beds <span class="optional-label">Optional</span></label><input id="beds" name="property[beds]" type="number" value="{{ $propertyDetails['beds'] ?? '' }}" min="0" max="200" placeholder="1">@error('property.beds')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="floor_area_sqm">Floor area (m²) <span class="optional-label">Optional</span></label><input id="floor_area_sqm" name="property[floor_area_sqm]" type="number" value="{{ $propertyDetails['floor_area_sqm'] ?? '' }}" min="1" max="100000" step="0.01" placeholder="35">@error('property.floor_area_sqm')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="check_in_time">Standard check-in time</label><input id="check_in_time" name="property[check_in_time]" type="time" value="{{ $propertyDetails['check_in_time'] ?? '14:00' }}" required><small class="field-help">Every booking starts at this host-set time.</small>@error('property.check_in_time')<p class="error-text">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label for="check_out_time">Standard check-out time</label><input id="check_out_time" name="property[check_out_time]" type="time" value="{{ $propertyDetails['check_out_time'] ?? '12:00' }}" required><small class="field-help">Every booking ends at this host-set time.</small>@error('property.check_out_time')<p class="error-text">{{ $message }}</p>@enderror</div>
        </div>
        <div class="option-check-grid">
            @foreach (['wifi' => 'Wi-Fi', 'air_conditioning' => 'Air conditioning', 'kitchen' => 'Kitchen', 'parking' => 'Parking', 'pool' => 'Swimming pool', 'balcony' => 'Balcony', 'pet_friendly' => 'Pet friendly', 'furnished' => 'Furnished'] as $value => $label)
                <label><input type="checkbox" name="property_amenities[]" value="{{ $value }}" @checked(in_array($value, $propertyAmenities)) @if(in_array($value, ['wifi', 'parking', 'pool'])) data-property-amenity="{{ $value }}" @endif><span>{{ $label }}</span></label>
            @endforeach
        </div>
        <div class="amenity-config-grid">
            <div class="private-detail-panel amenity-config-panel" data-wifi-details-section>
                <div class="private-detail-heading"><span><x-fa-icon name="lock" /></span><div><strong>Private Wi-Fi access</strong><small>Clients receive this only after their booking is confirmed.</small></div></div>
                <div class="detail-field-grid">
                    <div class="field-group"><label for="wifi_ssid">Wi-Fi name (SSID)</label><input id="wifi_ssid" name="wifi[ssid]" value="{{ $wifiDetails['ssid'] ?? '' }}" maxlength="120" autocomplete="off">@error('wifi.ssid')<p class="error-text">{{ $message }}</p>@enderror</div>
                    <div class="field-group"><label for="wifi_password">Wi-Fi password</label><input id="wifi_password" name="wifi[password]" type="password" value="{{ $wifiDetails['password'] ?? '' }}" maxlength="500" autocomplete="new-password"><button class="field-reveal-button" type="button" data-password-reveal>Show password</button>@error('wifi.password')<p class="error-text">{{ $message }}</p>@enderror</div>
                    <div class="field-group"><label for="wifi_qr">Wi-Fi QR code <span class="optional-label">Optional</span></label><input id="wifi_qr" name="wifi_qr" type="file" accept="image/jpeg,image/png,image/webp"><small class="field-help">Upload the router’s Wi-Fi QR image, maximum 5 MB.</small>@error('wifi_qr')<p class="error-text">{{ $message }}</p>@enderror</div>
                    <div class="field-group gps-notes-field"><label for="wifi_notes">Private access notes <span class="optional-label">Optional</span></label><textarea id="wifi_notes" name="wifi[notes]" rows="3" maxlength="1000" placeholder="Router location or connection instructions…">{{ $wifiDetails['notes'] ?? '' }}</textarea>@error('wifi.notes')<p class="error-text">{{ $message }}</p>@enderror</div>
                </div>
                @if ($editing && $unit->wifi_qr_path)
                    <div class="existing-wifi-qr"><img src="{{ route('units.wifi-qr', $unit) }}" alt="Current Wi-Fi QR code"><label><input type="hidden" name="remove_wifi_qr" value="0"><input type="checkbox" name="remove_wifi_qr" value="1" @checked(old('remove_wifi_qr'))> Remove current QR code</label></div>
                @endif
            </div>

            @foreach (['parking' => ['Parking access', ['hour' => 'Hour', 'day' => 'Day', 'booking' => 'Booking', 'month' => 'Month']], 'pool' => ['Swimming pool access', ['hour' => 'Hour', 'day' => 'Day', 'booking' => 'Booking', 'person' => 'Person']]] as $amenity => [$label, $rateUnits])
                @php($details = $amenity === 'parking' ? $parkingDetails : $poolDetails)
                <div class="amenity-config-panel paid-amenity-panel" data-paid-amenity-section="{{ $amenity }}">
                    <div class="private-detail-heading"><span>{{ $amenity === 'parking' ? '🅿️' : '🏊' }}</span><div><strong>{{ $label }}</strong><small>Tell clients whether this amenity costs extra.</small></div></div>
                    <div class="detail-field-grid">
                        <div class="field-group"><label for="{{ $amenity }}_payment_type">Payment</label><select id="{{ $amenity }}_payment_type" name="{{ $amenity }}[payment_type]" data-amenity-payment><option value="included" @selected(($details['payment_type'] ?? 'included') === 'included')>Included in rental</option><option value="separate" @selected(($details['payment_type'] ?? '') === 'separate')>Separate payment</option></select>@error($amenity.'.payment_type')<p class="error-text">{{ $message }}</p>@enderror</div>
                        <div class="field-group" data-amenity-rate-field><label for="{{ $amenity }}_rate">Additional rate (PHP)</label><input id="{{ $amenity }}_rate" name="{{ $amenity }}[rate]" type="number" value="{{ $details['rate'] ?? '' }}" min="0" max="9999999999.99" step="0.01" placeholder="0.00">@error($amenity.'.rate')<p class="error-text">{{ $message }}</p>@enderror</div>
                        <div class="field-group" data-amenity-rate-field><label for="{{ $amenity }}_rate_unit">Charge per</label><select id="{{ $amenity }}_rate_unit" name="{{ $amenity }}[rate_unit]">@foreach($rateUnits as $value => $unitLabel)<option value="{{ $value }}" @selected(($details['rate_unit'] ?? 'day') === $value)>{{ $unitLabel }}</option>@endforeach</select>@error($amenity.'.rate_unit')<p class="error-text">{{ $message }}</p>@enderror</div>
                    </div>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="field-group listing-sale-field">
        <label for="sale_percentage">Limited-time sale <span class="optional-label">Optional</span></label>
        <div class="percentage-input"><input id="sale_percentage" name="sale_percentage" type="number" value="{{ old('sale_percentage', $unit->sale_percentage ?? '') }}" min="0" max="90" step="0.01" placeholder="0"><span>% off</span></div>
        <small class="field-help">Leave blank or enter 0 for no sale. The discount applies to the listing price; required fees stay unchanged.</small>
        @error('sale_percentage')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group">
        <label for="capacity">Capacity <span class="optional-label">Optional</span></label>
        <input id="capacity" name="capacity" type="number" value="{{ old('capacity', $unit->capacity ?? '') }}" min="1" max="10000" placeholder="Guests, seats, or quantity">
        @error('capacity')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group listing-description-field">
        <label for="description">Description <span class="optional-label">Optional</span></label>
        <textarea id="description" name="description" rows="4" maxlength="2000" placeholder="Describe what clients can expect…">{{ old('description', $unit->description ?? '') }}</textarea>
        @error('description')<p class="error-text">{{ $message }}</p>@enderror
    </div>

    <div class="field-group listing-rules-field">
        <label for="rules" data-rules-label>Rental rules</label>
        <textarea id="rules" name="rules" rows="6" maxlength="5000" required placeholder="Add each rule on a new line…">{{ old('rules', $unit->rules ?? '') }}</textarea>
        <small class="field-help" data-rules-help>Clients will see these rules before sending a booking request.</small>
        @error('rules')<p class="error-text">{{ $message }}</p>@enderror
    </div>
</div>

<label class="availability-toggle">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $unit->is_active ?? true))>
    <span><strong>Available for booking</strong><small>Clients can see this listing and request open dates.</small></span>
</label>

<div class="edit-form-actions">
    <a class="button button-ghost" href="{{ route('units.index') }}">Cancel</a>
    <button class="button button-primary" type="submit">{{ $editing ? 'Save changes' : 'Register listing' }}</button>
</div>
