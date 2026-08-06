<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\InquiryMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $units = Unit::query()
            ->with(['rates', 'images'])
            ->withCount(['bookings as active_bookings_count' => fn ($query) => $query->blocking()
                ->where('start_at', '<', now())
                ->where('end_at', '>', now())])
            ->when(! $request->user()->is_admin, fn ($query) => $query->where('host_id', $request->user()->id))
            ->latest()
            ->get();

        return view('units.index', compact('units'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors(['profile' => 'Complete your identity and contact profile before registering a listing.']);
        }

        return view('units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors(['profile' => 'Complete your identity and contact profile before registering a listing.']);
        }

        $validated = $this->validated($request);
        $newImagePaths = $this->storePhotos($request);
        $wifiQrPath = $this->storeWifiQr($request, $validated);

        try {
            $unit = DB::transaction(function () use ($request, $validated, $newImagePaths, $wifiQrPath) {
                [$attributes, $rates] = $this->listingData($validated);
                $unit = $request->user()->units()->create([...$attributes, 'photo_path' => $newImagePaths[0] ?? null, 'wifi_qr_path' => $wifiQrPath]);
                $this->syncRates($unit, $rates);
                $newImages = $this->createImages($unit, $newImagePaths);
                $this->promotePrimaryImage($unit, $validated['primary_image'] ?? null, $newImages);

                return $unit;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newImagePaths);
            if ($wifiQrPath) Storage::disk('local')->delete($wifiQrPath);

            throw $exception;
        }

        return redirect()->route('units.index')->with('status', "{$unit->name} is now registered and available for booking.");
    }

    public function edit(Request $request, Unit $unit): View
    {
        $this->authorizeOwner($request, $unit);

        $unit->load(['rates', 'images']);

        return view('units.edit', compact('unit'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeOwner($request, $unit);
        $unit->load(['rates', 'images']);
        $validated = $this->validated($request, $unit);
        $newImagePaths = $this->storePhotos($request);
        $newWifiQrPath = $this->storeWifiQr($request, $validated);
        $oldWifiQrPath = $unit->wifi_qr_path;
        $keepExistingWifiQr = $this->hasWifi($validated) && ! ($validated['remove_wifi_qr'] ?? false);
        $finalWifiQrPath = $newWifiQrPath ?: ($keepExistingWifiQr ? $oldWifiQrPath : null);
        $removeImageIds = collect($validated['remove_images'] ?? [])->map(fn ($id) => (int) $id);
        $removedImagePaths = $unit->images->whereIn('id', $removeImageIds)->pluck('path');

        try {
            DB::transaction(function () use ($unit, $validated, $newImagePaths, $removeImageIds, $finalWifiQrPath) {
                [$attributes, $rates] = $this->listingData($validated);
                $attributes['wifi_qr_path'] = $finalWifiQrPath;
                $unit->update($attributes);
                $this->syncRates($unit, $rates);
                $unit->images()->whereIn('id', $removeImageIds)->delete();
                $newImages = $this->createImages($unit, $newImagePaths);
                $this->promotePrimaryImage($unit, $validated['primary_image'] ?? null, $newImages);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newImagePaths);
            if ($newWifiQrPath) Storage::disk('local')->delete($newWifiQrPath);

            throw $exception;
        }

        Storage::disk('public')->delete($removedImagePaths->all());
        if ($oldWifiQrPath && $oldWifiQrPath !== $finalWifiQrPath) Storage::disk('local')->delete($oldWifiQrPath);

        return redirect()->route('units.index')->with('status', "{$unit->name} was updated.");
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeOwner($request, $unit);

        if ($unit->bookings()->whereIn('status', ['pending', 'confirmed'])->where('end_at', '>', now())->exists()) {
            return back()->withErrors(['unit' => 'This listing has an active or upcoming booking. Mark it unavailable instead.']);
        }

        $name = $unit->name;
        $photoPaths = $unit->images()->pluck('path')->push($unit->photo_path)->filter()->unique();
        $wifiQrPath = $unit->wifi_qr_path;
        $inquiryAttachmentPaths = InquiryMessage::query()->whereNotNull('attachment_path')->whereHas('inquiry', fn ($query) => $query->where('unit_id', $unit->id))->pluck('attachment_path');
        $unit->delete();
        Storage::disk('public')->delete($photoPaths->all());
        if ($wifiQrPath) Storage::disk('local')->delete($wifiQrPath);
        Storage::disk('local')->delete($inquiryAttachmentPaths->all());

        return redirect()->route('units.index')->with('status', "{$name} was removed.");
    }

    public function wifiQr(Request $request, Unit $unit): StreamedResponse
    {
        $user = $request->user();
        $canManage = $user->is_admin || $unit->host_id === $user->id;
        $hasConfirmedBooking = $user->isClient() && $unit->bookings()
            ->where('client_id', $user->id)
            ->where('status', 'confirmed')
            ->where('end_at', '>', now())
            ->exists();

        abort_unless($canManage || $hasConfirmedBooking, 403);
        abort_unless($unit->wifi_qr_path && Storage::disk('local')->exists($unit->wifi_qr_path), 404);

        return Storage::disk('local')->response($unit->wifi_qr_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        $isRental = in_array($request->input('category'), ['car', 'condo'], true);
        $isCar = $request->input('category') === 'car';
        $isProperty = $request->input('category') === 'condo';
        $offeredRates = $request->input('offered_rates', []);
        $hasGps = $isCar && in_array('gps', $request->input('car_accessories', []), true);
        $propertyAmenities = $request->input('property_amenities', []);
        $hasWifi = $isProperty && in_array('wifi', $propertyAmenities, true);
        $hasParking = $isProperty && in_array('parking', $propertyAmenities, true);
        $hasPool = $isProperty && in_array('pool', $propertyAmenities, true);
        $paidParking = $hasParking && $request->input('parking.payment_type') === 'separate';
        $paidPool = $hasPool && $request->input('pool.payment_type') === 'separate';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['unit', 'service'])],
            'category' => ['required', Rule::in(['car', 'condo', 'driving', 'pet_transport', 'other'])],
            'location' => ['nullable', 'string', 'max:180'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['required', 'string', 'max:5000'],
            'photos' => [Rule::requiredIf(! $unit), 'nullable', 'array', 'min:1'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'primary_image' => ['nullable', 'string', 'max:40', 'regex:/^(existing|new):\d+$/'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', Rule::exists('unit_images', 'id')->where(fn ($query) => $query->where('unit_id', $unit?->id ?? 0))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'price' => [Rule::requiredIf(! $isRental), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pricing_unit' => [Rule::requiredIf(! $isRental), 'nullable', Rule::in(['hour', 'day', 'session'])],
            'offered_rates' => [$isRental ? 'required' : 'nullable', 'array', 'min:1'],
            'offered_rates.*' => [Rule::in(['12_hours', 'day', 'week', 'month'])],
            'rates' => ['nullable', 'array'],
            'rates.12_hours' => [Rule::requiredIf($isRental && in_array('12_hours', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.day' => [Rule::requiredIf($isRental && in_array('day', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.week' => [Rule::requiredIf($isRental && in_array('week', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.month' => [Rule::requiredIf($isRental && in_array('month', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car.make' => [Rule::requiredIf($isCar), 'nullable', 'string', 'max:80'],
            'car.model' => [Rule::requiredIf($isCar), 'nullable', 'string', 'max:80'],
            'car.year' => [Rule::requiredIf($isCar), 'nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'car.transmission' => [Rule::requiredIf($isCar), 'nullable', Rule::in(['automatic', 'manual'])],
            'car.fuel_type' => [Rule::requiredIf($isCar), 'nullable', Rule::in(['gasoline', 'diesel', 'hybrid', 'electric'])],
            'car_accessories' => ['nullable', 'array'],
            'car_accessories.*' => [Rule::in(['air_conditioning', 'bluetooth', 'usb_charger', 'dashcam', 'gps', 'child_seat', 'roof_rack', 'reverse_camera', 'toll_tag', 'phone_holder'])],
            'gps.device_name' => [Rule::requiredIf($hasGps), 'nullable', 'string', 'max:120'],
            'gps.login_url' => ['nullable', 'url:http,https', 'max:500'],
            'gps.username' => [Rule::requiredIf($hasGps), 'nullable', 'string', 'max:190'],
            'gps.password' => [Rule::requiredIf($hasGps), 'nullable', 'string', 'max:500'],
            'gps.notes' => ['nullable', 'string', 'max:1000'],
            'property.type' => [Rule::requiredIf($isProperty), 'nullable', Rule::in(['condo', 'apartment', 'house', 'villa', 'room'])],
            'property.bedrooms' => [Rule::requiredIf($isProperty), 'nullable', 'integer', 'min:0', 'max:100'],
            'property.bathrooms' => [Rule::requiredIf($isProperty), 'nullable', 'integer', 'min:1', 'max:100'],
            'property.beds' => ['nullable', 'integer', 'min:0', 'max:200'],
            'property.floor_area_sqm' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'property_amenities' => ['nullable', 'array'],
            'property_amenities.*' => [Rule::in(['wifi', 'air_conditioning', 'kitchen', 'parking', 'pool', 'balcony', 'pet_friendly', 'furnished'])],
            'wifi.ssid' => [Rule::requiredIf($hasWifi), 'nullable', 'string', 'max:120'],
            'wifi.password' => [Rule::requiredIf($hasWifi), 'nullable', 'string', 'max:500'],
            'wifi.notes' => ['nullable', 'string', 'max:1000'],
            'wifi_qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_wifi_qr' => ['nullable', 'boolean'],
            'parking.payment_type' => [Rule::requiredIf($hasParking), 'nullable', Rule::in(['included', 'separate'])],
            'parking.rate' => [Rule::requiredIf($paidParking), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'parking.rate_unit' => [Rule::requiredIf($paidParking), 'nullable', Rule::in(['hour', 'day', 'booking', 'month'])],
            'pool.payment_type' => [Rule::requiredIf($hasPool), 'nullable', Rule::in(['included', 'separate'])],
            'pool.rate' => [Rule::requiredIf($paidPool), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pool.rate_unit' => [Rule::requiredIf($paidPool), 'nullable', Rule::in(['hour', 'day', 'booking', 'person'])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($unit) {
            $removedCount = collect($validated['remove_images'] ?? [])->unique()->count();
            $newCount = count($request->file('photos', []));
            $existingCount = $unit->images->count() ?: ($unit->photo_path ? 1 : 0);

            if ($existingCount - $removedCount + $newCount < 1) {
                throw ValidationException::withMessages(['photos' => 'Keep or upload at least one listing image.']);
            }
        }

        if (! empty($validated['primary_image'])) {
            [$source, $identifier] = explode(':', $validated['primary_image'], 2);
            $identifier = (int) $identifier;
            $removedIds = collect($validated['remove_images'] ?? [])->map(fn ($id) => (int) $id);
            $validSelection = $source === 'new'
                ? $identifier >= 0 && $identifier < count($request->file('photos', []))
                : $unit && $unit->images->contains('id', $identifier) && ! $removedIds->contains($identifier);

            if (! $validSelection) {
                throw ValidationException::withMessages(['primary_image' => 'Choose a primary image that will remain in this gallery.']);
            }
        }

        return $validated;
    }

    private function listingData(array $validated): array
    {
        $offeredRates = $validated['offered_rates'] ?? [];
        $rates = collect($validated['rates'] ?? [])->only($offeredRates)->all();
        $carDetails = $validated['car'] ?? [];
        $carDetails['accessories'] = $validated['car_accessories'] ?? [];
        $gpsDetails = $validated['gps'] ?? [];
        $wifiDetails = $validated['wifi'] ?? [];
        $propertyDetails = $validated['property'] ?? [];
        $propertyDetails['amenities'] = $validated['property_amenities'] ?? [];
        $propertyDetails['parking'] = in_array('parking', $propertyDetails['amenities'], true) ? ($validated['parking'] ?? []) : null;
        $propertyDetails['pool'] = in_array('pool', $propertyDetails['amenities'], true) ? ($validated['pool'] ?? []) : null;
        unset($validated['photos'], $validated['primary_image'], $validated['remove_images'], $validated['offered_rates'], $validated['rates'], $validated['car'], $validated['car_accessories'], $validated['gps'], $validated['wifi'], $validated['wifi_qr'], $validated['remove_wifi_qr'], $validated['parking'], $validated['pool'], $validated['property'], $validated['property_amenities']);

        $validated['car_details'] = $validated['category'] === 'car' ? $carDetails : null;
        $validated['gps_details'] = $validated['category'] === 'car' && in_array('gps', $carDetails['accessories'], true) ? $gpsDetails : null;
        $validated['wifi_details'] = $validated['category'] === 'condo' && in_array('wifi', $propertyDetails['amenities'], true) ? $wifiDetails : null;
        $validated['property_details'] = $validated['category'] === 'condo' ? $propertyDetails : null;

        if (in_array($validated['category'], ['car', 'condo'], true)) {
            $firstPeriod = collect(['12_hours', 'day', 'week', 'month'])->first(fn ($period) => array_key_exists($period, $rates));
            $validated['price'] = $rates[$firstPeriod];
            $validated['pricing_unit'] = $firstPeriod;
        }

        return [$validated, $rates];
    }

    private function syncRates(Unit $unit, array $rates): void
    {
        if (! $unit->isPackageRental()) {
            $unit->rates()->delete();

            return;
        }

        foreach ($rates as $period => $price) {
            $unit->rates()->updateOrCreate(['period' => $period], ['price' => $price]);
        }

        $unit->rates()->whereNotIn('period', array_keys($rates))->delete();
    }

    private function storePhotos(Request $request): array
    {
        return collect($request->file('photos', []))
            ->map(fn ($photo) => $photo->store('listings', 'public'))
            ->all();
    }

    private function storeWifiQr(Request $request, array $validated): ?string
    {
        if (! $this->hasWifi($validated) || ! $request->hasFile('wifi_qr')) {
            return null;
        }

        return $request->file('wifi_qr')->store('wifi-qr', 'local');
    }

    private function hasWifi(array $validated): bool
    {
        return ($validated['category'] ?? null) === 'condo'
            && in_array('wifi', $validated['property_amenities'] ?? [], true);
    }

    private function createImages(Unit $unit, array $paths): array
    {
        $nextOrder = (int) $unit->images()->max('sort_order') + 1;
        $images = [];

        foreach ($paths as $index => $path) {
            $images[$index] = $unit->images()->create(['path' => $path, 'sort_order' => $nextOrder + $index]);
        }

        return $images;
    }

    private function promotePrimaryImage(Unit $unit, ?string $selection, array $newImages): void
    {
        $images = $unit->images()->orderBy('sort_order')->orderBy('id')->get();

        if ($images->isEmpty()) {
            $unit->update(['photo_path' => null]);

            return;
        }

        $primaryId = null;

        if ($selection) {
            [$source, $identifier] = explode(':', $selection, 2);
            $primaryId = $source === 'new'
                ? ($newImages[(int) $identifier]->id ?? null)
                : (int) $identifier;
        }

        $primary = $images->firstWhere('id', $primaryId) ?? $images->first();
        $ordered = $images->sortBy(fn ($image) => $image->id === $primary->id ? 0 : $image->sort_order + 1)->values();

        foreach ($ordered as $index => $image) {
            $image->update(['sort_order' => $index]);
        }

        $unit->update(['photo_path' => $primary->path]);
    }

    private function authorizeOwner(Request $request, Unit $unit): void
    {
        abort_unless($request->user()->is_admin || $unit->host_id === $request->user()->id, 403);
    }
}
