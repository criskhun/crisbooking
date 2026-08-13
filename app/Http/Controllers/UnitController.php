<?php

namespace App\Http\Controllers;

use App\Models\InquiryMessage;
use App\Models\Unit;
use App\Models\UnitDraft;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $units = Unit::query()
            ->with(['rates', 'images', 'host:id,name'])
            ->withCount([
                'bookings',
                'inquiries',
                'bookings as active_bookings_count' => fn ($query) => $query->blocking()
                    ->where('start_at', '<', now())
                    ->where('end_at', '>', now()),
            ])
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

        $draft = null;
        $draftPhotoPaths = [];
        $draftPrimaryPhotoPath = null;

        if ($request->filled('draft')) {
            $draft = $request->user()->unitDrafts()->findOrFail($request->integer('draft'));
            $draftPayload = $this->readDraftPayload($draft);

            if ($draftPayload === null) {
                return redirect()->route('units.create')->withErrors([
                    'draft' => 'This draft cannot be opened because its encrypted data does not match the current application key. Restore the previous APP_KEY or delete this draft and create a new one.',
                ]);
            }

            $draftPhotoPaths = $this->draftPhotoPaths($draftPayload);
            $draftPrimaryPhotoPath = in_array($draftPayload['_draft_primary_photo_path'] ?? null, $draftPhotoPaths, true)
                ? $draftPayload['_draft_primary_photo_path']
                : ($draftPhotoPaths[0] ?? null);

            if (! $request->session()->hasOldInput()) {
                // Draft values belong to this response only. Flashing them leaves
                // the values for the next request and can render this form blank.
                $request->session()->now('_old_input', $draftPayload);
            }
        }

        $drafts = $request->user()->unitDrafts()->latest('updated_at')->get();

        return view('units.create', compact('draft', 'drafts', 'draftPhotoPaths', 'draftPrimaryPhotoPath'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return redirect()->route('profile.edit')->withErrors(['profile' => 'Complete your identity and contact profile before registering a listing.']);
        }

        $draft = $request->filled('draft_id')
            ? $request->user()->unitDrafts()->findOrFail($request->integer('draft_id'))
            : null;
        $draftPayload = $draft ? $this->readDraftPayload($draft) : [];

        if ($draft && $draftPayload === null) {
            return back()->withErrors(['photos' => 'The selected draft cannot be decrypted. Delete it and create a new draft.']);
        }

        $allDraftPhotoPaths = $this->draftPhotoPaths($draftPayload ?? []);
        $removedDraftIndexes = collect($request->input('remove_draft_photos', []))->map(fn ($index) => (int) $index)->unique();
        $keptDraftPhotoPaths = collect($allDraftPhotoPaths)->reject(fn ($path, $index) => $removedDraftIndexes->contains($index))->values()->all();
        $validated = $this->validated($request, null, $allDraftPhotoPaths);
        $newImagePaths = $this->storePhotos($request);
        $listingImagePaths = [...$keptDraftPhotoPaths, ...$newImagePaths];
        $primarySelection = $this->listingPrimarySelection(
            $validated['primary_image'] ?? null,
            $allDraftPhotoPaths,
            $keptDraftPhotoPaths,
            $draftPayload['_draft_primary_photo_path'] ?? null,
        );
        $wifiQrPath = $this->storeWifiQr($request, $validated);

        try {
            $unit = DB::transaction(function () use ($request, $validated, $listingImagePaths, $primarySelection, $wifiQrPath) {
                [$attributes, $rates] = $this->listingData($validated);
                $unit = $request->user()->units()->create([...$attributes, 'photo_path' => $listingImagePaths[0] ?? null, 'wifi_qr_path' => $wifiQrPath]);
                $this->syncRates($unit, $rates);
                $newImages = $this->createImages($unit, $listingImagePaths);
                $this->promotePrimaryImage($unit, $primarySelection, $newImages);

                return $unit;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newImagePaths);
            if ($wifiQrPath) {
                Storage::disk('local')->delete($wifiQrPath);
            }

            throw $exception;
        }

        Storage::disk('public')->delete(collect($allDraftPhotoPaths)->filter(fn ($path, $index) => $removedDraftIndexes->contains($index))->all());
        $draft?->delete();

        return redirect()->route('units.index')->with('status', "{$unit->name} is now registered and available for booking.");
    }

    public function saveDraft(Request $request): JsonResponse
    {
        if (! $request->user()->hasCompleteProfile()) {
            return response()->json(['message' => 'Complete your profile before saving listing drafts.'], 422);
        }

        $draft = $request->filled('draft_id')
            ? $request->user()->unitDrafts()->findOrFail($request->integer('draft_id'))
            : new UnitDraft(['host_id' => $request->user()->id]);

        $request->validate([
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_draft_photos' => ['nullable', 'array'],
            'remove_draft_photos.*' => ['integer', 'min:0'],
            'primary_image' => ['nullable', 'string', 'max:40', 'regex:/^(draft|new):\d+$/'],
        ]);

        $currentPayload = $draft->exists ? $this->readDraftPayload($draft) : [];
        if ($currentPayload === null) {
            return response()->json(['message' => 'This draft cannot be decrypted with the current application key.'], 422);
        }

        $currentPhotoPaths = $this->draftPhotoPaths($currentPayload);
        $removedIndexes = collect($request->input('remove_draft_photos', []))->map(fn ($index) => (int) $index)->unique();
        $removedPhotoPaths = collect($currentPhotoPaths)->filter(fn ($path, $index) => $removedIndexes->contains($index))->values()->all();
        $keptPhotoPaths = collect($currentPhotoPaths)->reject(fn ($path, $index) => $removedIndexes->contains($index))->values()->all();
        $uploadedPhotoPaths = collect($request->file('photos', []))
            ->map(fn ($photo) => $photo->store('listing-drafts/'.$request->user()->id, 'public'))
            ->all();
        $draftPhotoPaths = [...$keptPhotoPaths, ...$uploadedPhotoPaths];

        if (count($draftPhotoPaths) > 20) {
            Storage::disk('public')->delete($uploadedPhotoPaths);
            throw ValidationException::withMessages(['photos' => 'A listing draft can contain up to 20 images.']);
        }

        $payload = $this->sanitizeDraftPayload($request->except([
            '_token', '_method', 'draft_id', 'photos', 'primary_image', 'remove_images', 'remove_draft_photos', 'wifi_qr', 'remove_wifi_qr',
        ]));

        $primaryPhotoPath = $this->draftPrimaryPhotoPath(
            $request->input('primary_image'),
            $currentPhotoPaths,
            $uploadedPhotoPaths,
            $draftPhotoPaths,
            $currentPayload['_draft_primary_photo_path'] ?? null,
        );
        $payload['_draft_photo_paths'] = $draftPhotoPaths;
        $payload['_draft_primary_photo_path'] = $primaryPhotoPath;

        if (! $this->hasMeaningfulDraftData($payload) && $draftPhotoPaths === []) {
            Storage::disk('public')->delete([...$removedPhotoPaths, ...$uploadedPhotoPaths]);
            if ($draft->exists) {
                $draft->delete();
            }

            return response()->json([
                'id' => null,
                'title' => null,
                'empty' => true,
            ]);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $draftCategory = ($payload['category'] ?? null) === 'other' && filled($payload['custom_category'] ?? null)
            ? $payload['custom_category']
            : ($payload['category'] ?? 'listing');
        $category = Str::of((string) $draftCategory)->replace('_', ' ')->title();

        try {
            $draft->fill([
                'title' => $name !== '' ? Str::limit($name, 120, '') : "Untitled {$category} draft",
                'payload' => $payload,
            ])->save();
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($uploadedPhotoPaths);
            throw $exception;
        }

        Storage::disk('public')->delete($removedPhotoPaths);

        return response()->json([
            'id' => $draft->id,
            'title' => $draft->title,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'photos_changed' => $uploadedPhotoPaths !== [] || $removedPhotoPaths !== [],
            'photo_count' => count($draftPhotoPaths),
        ]);
    }

    public function destroyDraft(Request $request, UnitDraft $draft): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->is_admin || $draft->host_id === $request->user()->id, 403);

        $title = $draft->title ?: 'Listing draft';
        $payload = $this->readDraftPayload($draft);
        Storage::disk('public')->delete($this->draftPhotoPaths($payload ?? []));
        $draft->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('units.create')->with('status', "{$title} was deleted.");
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
            if ($newWifiQrPath) {
                Storage::disk('local')->delete($newWifiQrPath);
            }

            throw $exception;
        }

        Storage::disk('public')->delete($removedImagePaths->all());
        if ($oldWifiQrPath && $oldWifiQrPath !== $finalWifiQrPath) {
            Storage::disk('local')->delete($oldWifiQrPath);
        }

        return redirect()->route('units.index')->with('status', "{$unit->name} was updated.");
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeOwner($request, $unit);

        if ($unit->bookings()->exists() || $unit->inquiries()->exists()) {
            $unit->update(['is_active' => false]);

            return redirect()->route('units.index')->with(
                'status',
                "{$unit->name} was disabled instead of deleted because its booking or inquiry records must be retained."
            );
        }

        $name = $unit->name;
        $photoPaths = $unit->images()->pluck('path')->push($unit->photo_path)->filter()->unique();
        $wifiQrPath = $unit->wifi_qr_path;
        $inquiryAttachmentPaths = InquiryMessage::query()->whereNotNull('attachment_path')->whereHas('inquiry', fn ($query) => $query->where('unit_id', $unit->id))->pluck('attachment_path');
        $unit->delete();
        Storage::disk('public')->delete($photoPaths->all());
        if ($wifiQrPath) {
            Storage::disk('local')->delete($wifiQrPath);
        }
        Storage::disk('local')->delete($inquiryAttachmentPaths->all());

        return redirect()->route('units.index')->with('status', "{$name} was removed.");
    }

    public function updateAvailability(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeOwner($request, $unit);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $unit->update(['is_active' => (bool) $validated['is_active']]);

        return redirect()->route('units.index')->with(
            'status',
            $unit->is_active ? "{$unit->name} is available again." : "{$unit->name} was disabled and is hidden from public booking."
        );
    }

    public function wifiQr(Request $request, Unit $unit): StreamedResponse
    {
        $user = $request->user();
        $canManage = $user->is_admin || $unit->host_id === $user->id;
        $hasConfirmedBooking = $unit->bookings()
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

    private function validated(Request $request, ?Unit $unit = null, array $draftPhotoPaths = []): array
    {
        $kind = $request->input('kind');
        $selectedCategory = $request->input('category');
        $isRental = in_array($request->input('category'), ['car', 'condo'], true);
        $isCar = $request->input('category') === 'car';
        $isProperty = $request->input('category') === 'condo';
        $offeredRates = $request->input('offered_rates', []);
        $carRateAreas = $request->input('car_rate_areas', []);
        $carOfferedRates = $request->input('car_offered_rates', []);
        $hasGps = $isCar && in_array('gps', $request->input('car_accessories', []), true);
        $enabledCarCharges = collect(['car_wash', 'delivery', 'deposit'])
            ->filter(fn ($charge) => $isCar && $request->boolean("car_charges.{$charge}.enabled"));
        $propertyAmenities = $request->input('property_amenities', []);
        $hasWifi = $isProperty && in_array('wifi', $propertyAmenities, true);
        $hasParking = $isProperty && in_array('parking', $propertyAmenities, true);
        $hasPool = $isProperty && in_array('pool', $propertyAmenities, true);
        $paidParking = $hasParking && $request->input('parking.payment_type') === 'separate';
        $paidPool = $hasPool && $request->input('pool.payment_type') === 'separate';
        $removedDraftIndexes = collect($request->input('remove_draft_photos', []))->map(fn ($index) => (int) $index)->unique();
        $availableDraftPhotoCount = collect($draftPhotoPaths)->reject(fn ($path, $index) => $removedDraftIndexes->contains($index))->count();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['unit', 'service'])],
            'category' => ['required', Rule::in($kind === 'service'
                ? ['cleaning', 'driving', 'massage', 'consultancy', 'other']
                : ['car', 'condo'])],
            'custom_category' => [Rule::requiredIf($kind === 'service' && $selectedCategory === 'other'), 'nullable', 'string', 'max:30', 'regex:/[A-Za-z0-9]/'],
            'location' => ['nullable', 'string', 'max:180'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['required', 'string', 'max:5000'],
            'photos' => [Rule::requiredIf(! $unit && $availableDraftPhotoCount < 1), 'nullable', 'array', 'min:1'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'primary_image' => ['nullable', 'string', 'max:40', 'regex:/^(existing|draft|new):\d+$/'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', Rule::exists('unit_images', 'id')->where(fn ($query) => $query->where('unit_id', $unit?->id ?? 0))],
            'remove_draft_photos' => ['nullable', 'array'],
            'remove_draft_photos.*' => ['integer', Rule::in(array_keys($draftPhotoPaths))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'price' => [Rule::requiredIf(! $isRental), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pricing_unit' => [Rule::requiredIf(! $isRental), 'nullable', Rule::in(['hour', 'day', 'session'])],
            'offered_rates' => [$isProperty ? 'required' : 'nullable', 'array', 'min:1'],
            'offered_rates.*' => [Rule::in(['12_hours', 'day', 'week', 'month'])],
            'rates' => ['nullable', 'array'],
            'rates.12_hours' => [Rule::requiredIf($isProperty && in_array('12_hours', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.day' => [Rule::requiredIf($isProperty && in_array('day', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.week' => [Rule::requiredIf($isProperty && in_array('week', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rates.month' => [Rule::requiredIf($isProperty && in_array('month', $offeredRates, true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rate_areas' => [$isCar ? 'required' : 'nullable', 'array', 'min:1'],
            'car_rate_areas.*' => [Rule::in(['within_city', 'out_of_town'])],
            'car_offered_rates' => ['nullable', 'array'],
            'car_offered_rates.within_city' => [Rule::requiredIf($isCar && in_array('within_city', $carRateAreas, true)), 'nullable', 'array', 'min:1'],
            'car_offered_rates.out_of_town' => [Rule::requiredIf($isCar && in_array('out_of_town', $carRateAreas, true)), 'nullable', 'array', 'min:1'],
            'car_offered_rates.*.*' => [Rule::in(['12_hours', 'day', 'week', 'month'])],
            'car_rates' => ['nullable', 'array'],
            'car_rates.within_city.12_hours' => [Rule::requiredIf($isCar && in_array('within_city', $carRateAreas, true) && in_array('12_hours', $carOfferedRates['within_city'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.within_city.day' => [Rule::requiredIf($isCar && in_array('within_city', $carRateAreas, true) && in_array('day', $carOfferedRates['within_city'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.within_city.week' => [Rule::requiredIf($isCar && in_array('within_city', $carRateAreas, true) && in_array('week', $carOfferedRates['within_city'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.within_city.month' => [Rule::requiredIf($isCar && in_array('within_city', $carRateAreas, true) && in_array('month', $carOfferedRates['within_city'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.out_of_town.12_hours' => [Rule::requiredIf($isCar && in_array('out_of_town', $carRateAreas, true) && in_array('12_hours', $carOfferedRates['out_of_town'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.out_of_town.day' => [Rule::requiredIf($isCar && in_array('out_of_town', $carRateAreas, true) && in_array('day', $carOfferedRates['out_of_town'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.out_of_town.week' => [Rule::requiredIf($isCar && in_array('out_of_town', $carRateAreas, true) && in_array('week', $carOfferedRates['out_of_town'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_rates.out_of_town.month' => [Rule::requiredIf($isCar && in_array('out_of_town', $carRateAreas, true) && in_array('month', $carOfferedRates['out_of_town'] ?? [], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car.make' => [Rule::requiredIf($isCar), 'nullable', 'string', 'max:80'],
            'car.model' => [Rule::requiredIf($isCar), 'nullable', 'string', 'max:80'],
            'car.year' => [Rule::requiredIf($isCar), 'nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'car.transmission' => [Rule::requiredIf($isCar), 'nullable', Rule::in(['automatic', 'manual'])],
            'car.fuel_type' => [Rule::requiredIf($isCar), 'nullable', Rule::in(['gasoline', 'diesel', 'hybrid', 'electric'])],
            'car.color' => [Rule::requiredIf($isCar), 'nullable', 'string', 'max:50'],
            'car_accessories' => ['nullable', 'array'],
            'car_accessories.*' => [Rule::in(['air_conditioning', 'bluetooth', 'usb_charger', 'dashcam', 'gps', 'child_seat', 'roof_rack', 'reverse_camera', 'toll_tag', 'phone_holder'])],
            'custom_accessories' => ['nullable', 'array', 'max:20'],
            'custom_accessories.*' => ['nullable', 'string', 'max:80'],
            'car_charges' => ['nullable', 'array'],
            'car_charges.car_wash.enabled' => ['nullable', 'boolean'],
            'car_charges.car_wash.amount' => [Rule::requiredIf($enabledCarCharges->contains('car_wash')), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_charges.delivery.enabled' => ['nullable', 'boolean'],
            'car_charges.delivery.amount' => [Rule::requiredIf($enabledCarCharges->contains('delivery')), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'car_charges.deposit.enabled' => ['nullable', 'boolean'],
            'car_charges.deposit.amount' => [Rule::requiredIf($enabledCarCharges->contains('deposit')), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
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

        if (($validated['kind'] ?? null) === 'service' && ($validated['category'] ?? null) === 'other') {
            $customCategory = trim(Str::substr(Str::slug($validated['custom_category'], '_'), 0, 30), '_');

            if (in_array($customCategory, ['car', 'condo'], true)) {
                throw ValidationException::withMessages(['custom_category' => 'Choose a service category other than Car or Condo.']);
            }

            $validated['category'] = $customCategory;
        }
        unset($validated['custom_category']);

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
            $validSelection = match ($source) {
                'new' => $identifier >= 0 && $identifier < count($request->file('photos', [])),
                'draft' => array_key_exists($identifier, $draftPhotoPaths) && ! $removedDraftIndexes->contains($identifier),
                'existing' => $unit && $unit->images->contains('id', $identifier) && ! $removedIds->contains($identifier),
                default => false,
            };

            if (! $validSelection) {
                throw ValidationException::withMessages(['primary_image' => 'Choose a primary image that will remain in this gallery.']);
            }
        }

        return $validated;
    }

    private function listingData(array $validated): array
    {
        $rates = [];
        if (($validated['category'] ?? null) === 'car') {
            foreach ($validated['car_rate_areas'] ?? [] as $coverage) {
                foreach ($validated['car_offered_rates'][$coverage] ?? [] as $period) {
                    $rates[] = ['coverage' => $coverage, 'period' => $period, 'price' => $validated['car_rates'][$coverage][$period]];
                }
            }
        } elseif (($validated['category'] ?? null) === 'condo') {
            foreach ($validated['offered_rates'] ?? [] as $period) {
                $rates[] = ['coverage' => 'standard', 'period' => $period, 'price' => $validated['rates'][$period]];
            }
        }
        $carDetails = $validated['car'] ?? [];
        $carDetails['accessories'] = $validated['car_accessories'] ?? [];
        $carDetails['custom_accessories'] = collect($validated['custom_accessories'] ?? [])
            ->map(fn ($accessory) => trim((string) $accessory))
            ->filter()
            ->unique(fn ($accessory) => Str::lower($accessory))
            ->values()
            ->all();
        $chargeLabels = ['car_wash' => 'Car wash', 'delivery' => 'Delivery', 'deposit' => 'Refundable deposit'];
        $carDetails['charges'] = collect($validated['car_charges'] ?? [])
            ->filter(fn ($charge) => filter_var($charge['enabled'] ?? false, FILTER_VALIDATE_BOOL))
            ->map(fn ($charge, $key) => [
                'key' => $key,
                'label' => $chargeLabels[$key] ?? Str::of($key)->replace('_', ' ')->title(),
                'amount' => round((float) ($charge['amount'] ?? 0), 2),
                'refundable' => $key === 'deposit',
            ])
            ->keyBy('key')
            ->map(fn ($charge) => collect($charge)->except('key')->all())
            ->all();
        $gpsDetails = $validated['gps'] ?? [];
        $wifiDetails = $validated['wifi'] ?? [];
        $propertyDetails = $validated['property'] ?? [];
        $propertyDetails['amenities'] = $validated['property_amenities'] ?? [];
        $propertyDetails['parking'] = in_array('parking', $propertyDetails['amenities'], true) ? ($validated['parking'] ?? []) : null;
        $propertyDetails['pool'] = in_array('pool', $propertyDetails['amenities'], true) ? ($validated['pool'] ?? []) : null;
        unset($validated['photos'], $validated['primary_image'], $validated['remove_images'], $validated['remove_draft_photos'], $validated['offered_rates'], $validated['rates'], $validated['car_rate_areas'], $validated['car_offered_rates'], $validated['car_rates'], $validated['car'], $validated['car_accessories'], $validated['custom_accessories'], $validated['car_charges'], $validated['gps'], $validated['wifi'], $validated['wifi_qr'], $validated['remove_wifi_qr'], $validated['parking'], $validated['pool'], $validated['property'], $validated['property_amenities']);

        $validated['car_details'] = $validated['category'] === 'car' ? $carDetails : null;
        $validated['gps_details'] = $validated['category'] === 'car' && in_array('gps', $carDetails['accessories'], true) ? $gpsDetails : null;
        $validated['wifi_details'] = $validated['category'] === 'condo' && in_array('wifi', $propertyDetails['amenities'], true) ? $wifiDetails : null;
        $validated['property_details'] = $validated['category'] === 'condo' ? $propertyDetails : null;

        if (in_array($validated['category'], ['car', 'condo'], true)) {
            $validated['price'] = $rates[0]['price'];
            $validated['pricing_unit'] = $rates[0]['period'];
        }

        return [$validated, $rates];
    }

    private function syncRates(Unit $unit, array $rates): void
    {
        if (! $unit->isPackageRental()) {
            $unit->rates()->delete();

            return;
        }

        $keptIds = [];
        foreach ($rates as $rate) {
            $storedRate = $unit->rates()
                ->where('coverage', $rate['coverage'])
                ->where('period', $rate['period'])
                ->first();

            if (! $storedRate && $unit->category === 'car' && $rate['coverage'] === 'within_city') {
                $storedRate = $unit->rates()
                    ->where('coverage', 'standard')
                    ->where('period', $rate['period'])
                    ->first();
            }

            if ($storedRate) {
                $storedRate->update([
                    'coverage' => $rate['coverage'],
                    'price' => $rate['price'],
                ]);
            } else {
                $storedRate = $unit->rates()->create($rate);
            }

            $keptIds[] = $storedRate->id;
        }

        $unit->rates()->whereNotIn('id', $keptIds)->delete();
    }

    private function storePhotos(Request $request): array
    {
        return collect($request->file('photos', []))
            ->map(fn ($photo) => $photo->store('listings', 'public'))
            ->all();
    }

    private function draftPhotoPaths(array $payload): array
    {
        return collect($payload['_draft_photo_paths'] ?? [])
            ->filter(fn ($path) => is_string($path) && Str::startsWith($path, 'listing-drafts/'))
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function draftPrimaryPhotoPath(?string $selection, array $currentPaths, array $uploadedPaths, array $finalPaths, ?string $currentPrimary): ?string
    {
        $selectedPath = null;

        if ($selection && preg_match('/^(draft|new):(\d+)$/', $selection, $matches)) {
            $selectedPath = $matches[1] === 'draft'
                ? ($currentPaths[(int) $matches[2]] ?? null)
                : ($uploadedPaths[(int) $matches[2]] ?? null);
        }

        if (! in_array($selectedPath, $finalPaths, true)) {
            $selectedPath = in_array($currentPrimary, $finalPaths, true) ? $currentPrimary : ($finalPaths[0] ?? null);
        }

        return $selectedPath;
    }

    private function listingPrimarySelection(?string $selection, array $allDraftPaths, array $keptDraftPaths, ?string $draftPrimary): ?string
    {
        if ($selection && preg_match('/^new:(\d+)$/', $selection, $matches)) {
            return 'new:'.(count($keptDraftPaths) + (int) $matches[1]);
        }

        $selectedDraftPath = null;
        if ($selection && preg_match('/^draft:(\d+)$/', $selection, $matches)) {
            $selectedDraftPath = $allDraftPaths[(int) $matches[1]] ?? null;
        }

        if (! in_array($selectedDraftPath, $keptDraftPaths, true)) {
            $selectedDraftPath = in_array($draftPrimary, $keptDraftPaths, true) ? $draftPrimary : null;
        }

        $index = $selectedDraftPath !== null ? array_search($selectedDraftPath, $keptDraftPaths, true) : false;

        return $index === false ? null : 'new:'.$index;
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

    private function sanitizeDraftPayload(array $payload, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        return collect($payload)
            ->take(150)
            ->map(function ($value) use ($depth) {
                if (is_array($value)) {
                    return $this->sanitizeDraftPayload($value, $depth + 1);
                }

                if (is_string($value)) {
                    return Str::limit($value, 5000, '');
                }

                return is_scalar($value) || $value === null ? $value : null;
            })
            ->all();
    }

    private function readDraftPayload(UnitDraft $draft): ?array
    {
        try {
            $payload = $draft->payload;

            return is_array($payload) ? $payload : [];
        } catch (DecryptException $exception) {
            // Early draft versions may have stored JSON before the encrypted
            // model cast was introduced. Recover and immediately encrypt those.
            $legacyPayload = json_decode((string) $draft->getRawOriginal('payload'), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($legacyPayload)) {
                DB::table('unit_drafts')->where('id', $draft->id)->update([
                    'payload' => Crypt::encrypt(json_encode($legacyPayload, JSON_THROW_ON_ERROR), false),
                    'updated_at' => now(),
                ]);

                return $legacyPayload;
            }

            Log::warning('A listing draft could not be decrypted.', [
                'draft_id' => $draft->id,
                'host_id' => $draft->host_id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function hasMeaningfulDraftData(array $payload): bool
    {
        $filled = static function (mixed $value): bool {
            if (is_array($value)) {
                return collect($value)->contains(fn ($item) => is_array($item)
                    ? collect($item)->flatten()->contains(fn ($nested) => trim((string) $nested) !== '')
                    : trim((string) $item) !== '');
            }

            return $value !== null && trim((string) $value) !== '';
        };

        foreach (['name', 'location', 'description', 'rules', 'capacity', 'price', 'latitude', 'longitude'] as $field) {
            if ($filled(data_get($payload, $field))) {
                return true;
            }
        }

        if (($payload['kind'] ?? 'unit') !== 'unit' || ($payload['category'] ?? 'car') !== 'car') {
            return true;
        }

        foreach (['rates', 'car_rates', 'car_accessories', 'custom_accessories', 'gps', 'wifi', 'property_amenities'] as $field) {
            if ($filled($payload[$field] ?? null)) {
                return true;
            }
        }

        foreach (['make', 'model', 'year', 'color'] as $field) {
            if ($filled(data_get($payload, "car.{$field}"))) {
                return true;
            }
        }

        foreach (['bedrooms', 'bathrooms', 'beds', 'floor_area_sqm'] as $field) {
            if ($filled(data_get($payload, "property.{$field}"))) {
                return true;
            }
        }

        foreach (['car_wash', 'delivery', 'deposit'] as $charge) {
            if (filter_var(data_get($payload, "car_charges.{$charge}.enabled"), FILTER_VALIDATE_BOOL)
                || $filled(data_get($payload, "car_charges.{$charge}.amount"))) {
                return true;
            }
        }

        foreach (['parking', 'pool'] as $amenity) {
            if (data_get($payload, "{$amenity}.payment_type") === 'separate'
                || $filled(data_get($payload, "{$amenity}.rate"))) {
                return true;
            }
        }

        return false;
    }
}
