<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'search_start' => ['nullable', 'date', 'after:now'],
            'search_end' => ['nullable', 'date', 'after:search_start'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'location' => ['nullable', 'string', 'max:180'],
            'search_latitude' => ['nullable', 'required_with:search_longitude', 'numeric', 'between:-90,90'],
            'search_longitude' => ['nullable', 'required_with:search_latitude', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'amenity' => ['nullable', Rule::in(['wifi', 'air_conditioning', 'kitchen', 'parking', 'pool', 'balcony', 'pet_friendly', 'furnished'])],
            'sort' => ['nullable', Rule::in(['recommended', 'price_low', 'capacity_high'])],
            'search' => ['nullable', 'boolean'],
            'selected_unit' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['book', 'manage'])],
            'schedule_category' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'schedule_unit' => ['nullable', 'integer'],
        ]);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('!Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
        $selectedDate = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m-d', $request->string('date'))
            : ($month->isSameMonth(now()) ? today() : $month->copy());
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $user = $request->user();
        $canManageListings = $user->isHost() || $user->is_admin;
        $bookingMode = ! $canManageListings || ($validated['mode'] ?? null) === 'book';
        $scheduleCategory = $bookingMode ? null : ($validated['schedule_category'] ?? null);
        $scheduleUnitId = $bookingMode ? null : (int) ($validated['schedule_unit'] ?? 0);
        $scheduleUnits = $bookingMode
            ? collect()
            : Unit::query()
                ->select(['id', 'host_id', 'name', 'category'])
                ->when(! $user->is_admin, fn ($query) => $query->where('host_id', $user->id))
                ->orderBy('name')
                ->get();
        $scheduleCategories = $scheduleUnits->pluck('category')->filter()->unique()->sort()->values();

        $units = Unit::query()
            ->with(['host.hostApplication', 'rates', 'images'])
            ->when(
                $bookingMode,
                fn ($query) => $query->where('host_id', '!=', $user->id)
                    ->where('is_active', true)
                    ->whereHas('host', fn ($hosts) => $hosts->whereNotNull('profile_completed_at'))
                    ->where(function ($bookable) {
                        $bookable->whereNotIn('category', ['car', 'condo'])
                            ->orWhereHas('rates');
                    }),
                fn ($query) => $query->when(! $user->is_admin, fn ($owned) => $owned->where('host_id', $user->id))
            )
            ->when(! $bookingMode && $scheduleCategory, fn ($query) => $query->where('category', $scheduleCategory))
            ->when(! $bookingMode && $scheduleUnitId, fn ($query) => $query->whereKey($scheduleUnitId))
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->with(['unit:id,host_id,name,kind,category,property_details,wifi_details,wifi_qr_path', 'unit.host:id,name', 'client:id,name', 'inquiry:id'])
            ->where('start_at', '<', $gridEnd->copy()->addDay())
            ->where('end_at', '>', $gridStart)
            ->when(
                $bookingMode,
                fn ($query) => $query->where('client_id', $user->id),
                fn ($query) => $query->whereIn('unit_id', $units->pluck('id'))
            )
            ->orderBy('start_at')
            ->get();

        $selectedStart = $selectedDate->copy()->startOfDay();
        $selectedEnd = $selectedDate->copy()->addDay()->startOfDay();
        $bookedUnitIds = Booking::query()->blocking()
            ->whereIn('unit_id', $units->pluck('id'))
            ->where('start_at', '<', $selectedEnd)
            ->where('end_at', '>', $selectedStart)
            ->pluck('unit_id')
            ->unique();

        $category = $validated['category'] ?? null;
        $discoverableServiceCategories = collect(['cleaning', 'driving', 'massage', 'consultancy'])
            ->merge(Unit::query()->where('kind', 'service')->where('is_active', true)->distinct()->pluck('category'))
            ->when($category && ! in_array($category, ['car', 'condo'], true), fn ($categories) => $categories->push($category))
            ->filter()
            ->unique()
            ->values();
        $searchStart = ! empty($validated['search_start']) ? Carbon::parse($validated['search_start']) : null;
        $searchEnd = ! empty($validated['search_end']) ? Carbon::parse($validated['search_end']) : null;
        $partySize = (int) ($validated['party_size'] ?? 1);
        $searchLatitude = isset($validated['search_latitude']) ? (float) $validated['search_latitude'] : null;
        $searchLongitude = isset($validated['search_longitude']) ? (float) $validated['search_longitude'] : null;
        $radiusKm = (float) ($validated['radius_km'] ?? 500);
        $hasRadiusSearch = $searchLatitude !== null && $searchLongitude !== null;
        $searchSubmitted = $bookingMode && (bool) ($validated['search'] ?? false);
        $matchingUnits = collect();

        if ($searchSubmitted && $category && $searchStart && $searchEnd) {
            $matchingUnitsQuery = Unit::query()
                ->with(['host.hostApplication', 'rates', 'images'])
                ->where('is_active', true)
                ->where('host_id', '!=', $user->id)
                ->whereHas('host', fn ($hosts) => $hosts->whereNotNull('profile_completed_at'))
                ->where('category', $category)
                ->where(function ($query) use ($partySize) {
                    $query->whereNull('capacity')->orWhere('capacity', '>=', $partySize);
                })
                ->when($category !== 'condo', fn ($query) => $query->availableBetween($searchStart, $searchEnd))
                ->when(($validated['location'] ?? null) && ! $hasRadiusSearch, fn ($query) => $query->where('location', 'like', '%'.$validated['location'].'%'))
                ->when($validated['amenity'] ?? null, fn ($query, $amenity) => $query->whereJsonContains('property_details->amenities', $amenity));

            match ($validated['sort'] ?? 'recommended') {
                'price_low' => $matchingUnitsQuery->orderBy('price'),
                'capacity_high' => $matchingUnitsQuery->orderByDesc('capacity'),
                default => $matchingUnitsQuery->orderByRaw('CASE WHEN photo_path IS NULL THEN 1 ELSE 0 END')->orderBy('name'),
            };

            $matchingUnits = $matchingUnitsQuery->get();

            if ($category === 'condo') {
                $matchingUnits = $matchingUnits->filter(function (Unit $unit) use ($searchStart, $searchEnd) {
                    [$arrival, $departure] = $unit->standardizeBookingPeriod($searchStart, $searchEnd);

                    return $departure->gt($arrival)
                        && $unit->bookings()->blocking()
                            ->where('start_at', '<', $departure)
                            ->where('end_at', '>', $arrival)
                            ->doesntExist();
                })->values();
            }

            if ($hasRadiusSearch) {
                $matchingUnits = $matchingUnits
                    ->filter(fn ($unit) => $unit->latitude !== null && $unit->longitude !== null)
                    ->each(function ($unit) use ($searchLatitude, $searchLongitude) {
                        $unit->setAttribute('distance_km', $this->distanceInKilometres(
                            $searchLatitude,
                            $searchLongitude,
                            (float) $unit->latitude,
                            (float) $unit->longitude,
                        ));
                    })
                    ->filter(fn ($unit) => $unit->distance_km <= $radiusKm)
                    ->when(($validated['sort'] ?? 'recommended') === 'recommended', fn ($units) => $units->sortBy('distance_km'))
                    ->values();
            }
        }

        $selectedUnit = $matchingUnits->firstWhere('id', (int) ($validated['selected_unit'] ?? 0));
        [$selectedBookingStart, $selectedBookingEnd] = $selectedUnit && $searchStart && $searchEnd
            ? $selectedUnit->standardizeBookingPeriod($searchStart, $searchEnd)
            : [$searchStart, $searchEnd];
        $selectedInquiry = $selectedUnit && $bookingMode
            ? $user->clientInquiries()->where('unit_id', $selectedUnit->id)->whereDoesntHave('booking')->where('status', 'open')->latest()->first()
            : null;
        $clientBookings = $bookingMode
            ? Booking::query()
                ->with(['unit:id,host_id,name,category,property_details,wifi_details,wifi_qr_path', 'unit.host:id,name'])
                ->where('client_id', $user->id)
                ->where('end_at', '>', now())
                ->orderBy('start_at')
                ->get()
            : collect();
        $locations = $bookingMode
            ? Unit::query()->where('is_active', true)
                ->where('host_id', '!=', $user->id)
                ->when($category, fn ($query) => $query->where('category', $category))
                ->whereNotNull('location')->where('location', '!=', '')
                ->distinct()->orderBy('location')->pluck('location')
            : collect();
        $mapSourceUnits = $searchSubmitted
            ? $matchingUnits
            : $units->when($category, fn ($availableUnits) => $availableUnits->where('category', $category))->values();
        $matchingMapUnits = $mapSourceUnits
            ->filter(fn (Unit $unit) => $unit->latitude !== null && $unit->longitude !== null)
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'latitude' => (float) $unit->latitude,
                'longitude' => (float) $unit->longitude,
                'location' => $unit->location,
                'category' => $unit->category,
                'capacity' => $unit->capacity,
                'bedrooms' => $unit->property_details['bedrooms'] ?? null,
                'starting_price' => (float) ($unit->isPackageRental() ? $unit->rates->min('price') : $unit->price),
                'host_name' => $unit->host->name,
                'business_name' => $unit->host->publicHostName(),
                'host_avatar_url' => $unit->host->avatarUrl(),
                'marker_image_url' => $unit->host->avatarUrl() ?: ($unit->primaryImagePath() ? Storage::disk('public')->url($unit->primaryImagePath()) : null),
                'image_url' => $unit->primaryImagePath() ? Storage::disk('public')->url($unit->primaryImagePath()) : null,
                'url' => route('listings.show', $unit),
                'inquiry_url' => route('listings.inquire', $unit),
                'host_url' => route('hosts.show', $unit->host),
                'navigation_url' => 'https://www.google.com/maps/dir/?api=1&destination='.$unit->latitude.','.$unit->longitude,
                'distance_km' => isset($unit->distance_km) ? round((float) $unit->distance_km, 1) : null,
            ])
            ->values();
        [$calendarSegments, $calendarWeekCount, $calendarLaneCount] = $this->buildCalendarSegments($bookings, $gridStart, $gridEnd);

        return view('calendar.index', [
            'month' => $month,
            'selectedDate' => $selectedDate,
            'days' => $gridStart->daysUntil($gridEnd),
            'units' => $units,
            'bookings' => $bookings,
            'bookedUnitIds' => $bookedUnitIds,
            'category' => $category,
            'discoverableServiceCategories' => $discoverableServiceCategories,
            'searchStart' => $searchStart,
            'searchEnd' => $searchEnd,
            'partySize' => $partySize,
            'searchLatitude' => $searchLatitude,
            'searchLongitude' => $searchLongitude,
            'radiusKm' => $radiusKm,
            'searchSubmitted' => $searchSubmitted,
            'matchingUnits' => $matchingUnits,
            'matchingMapUnits' => $matchingMapUnits,
            'selectedUnit' => $selectedUnit,
            'selectedBookingStart' => $selectedBookingStart,
            'selectedBookingEnd' => $selectedBookingEnd,
            'selectedInquiry' => $selectedInquiry,
            'clientBookings' => $clientBookings,
            'locations' => $locations,
            'calendarSegments' => $calendarSegments,
            'calendarWeekCount' => $calendarWeekCount,
            'calendarLaneCount' => $calendarLaneCount,
            'bookingMode' => $bookingMode,
            'canManageListings' => $canManageListings,
            'scheduleUnits' => $scheduleUnits,
            'scheduleCategories' => $scheduleCategories,
            'scheduleCategory' => $scheduleCategory,
            'scheduleUnitId' => $scheduleUnitId,
        ]);
    }

    private function distanceInKilometres(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return round($earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function buildCalendarSegments(mixed $bookings, Carbon $gridStart, Carbon $gridEnd): array
    {
        $calendarStart = $gridStart->copy()->startOfDay();
        $calendarEnd = $gridEnd->copy()->addDay()->startOfDay();
        $weekCount = (int) ceil($calendarStart->diffInDays($calendarEnd) / 7);
        $segments = [];
        $occupied = [];
        $laneCount = 1;

        foreach ($bookings->whereIn('status', ['pending', 'pre_approved', 'payment_submitted', 'confirmed'])->sortBy('start_at') as $booking) {
            $firstDay = $booking->start_at->copy()->startOfDay()->max($calendarStart);
            $lastDay = $booking->end_at->copy()->subSecond()->startOfDay()->min($gridEnd->copy()->startOfDay());

            if ($lastDay->lt($firstDay)) {
                continue;
            }

            $segmentStart = $firstDay->copy();

            while ($segmentStart->lte($lastDay)) {
                $weekIndex = intdiv((int) $calendarStart->diffInDays($segmentStart), 7);
                $segmentEnd = $segmentStart->copy()->endOfWeek(Carbon::SATURDAY)->startOfDay()->min($lastDay);
                $startColumn = $segmentStart->dayOfWeek + 1;
                $endColumn = $segmentEnd->dayOfWeek + 2;
                $lane = 0;

                while (collect($occupied[$weekIndex][$lane] ?? [])->contains(
                    fn ($range) => $startColumn < $range[1] && $endColumn > $range[0]
                )) {
                    $lane++;
                }

                $occupied[$weekIndex][$lane][] = [$startColumn, $endColumn];
                $laneCount = max($laneCount, $lane + 1);
                $startsBooking = $segmentStart->isSameDay($booking->start_at);
                $endsBooking = $segmentEnd->isSameDay($booking->end_at);
                $segments[] = [
                    'booking' => $booking,
                    'week' => $weekIndex + 1,
                    'lane' => $lane,
                    'start_column' => $startColumn,
                    'end_column' => $endColumn,
                    'start_date' => $segmentStart->format('Y-m-d'),
                    'end_date' => $segmentEnd->format('Y-m-d'),
                    'starts_booking' => $startsBooking,
                    'ends_booking' => $endsBooking,
                    'continues_before' => $segmentStart->gt($firstDay),
                    'continues_after' => $segmentEnd->lt($lastDay),
                ];
                $segmentStart = $segmentEnd->copy()->addDay();
            }
        }

        return [$segments, $weekCount, $laneCount];
    }
}
