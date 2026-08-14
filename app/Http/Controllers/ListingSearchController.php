<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $terms = collect(preg_split('/\s+/', Str::lower(trim($validated['q']))))
            ->filter()
            ->map(fn ($term) => Str::ascii($term))
            ->values();

        $results = Unit::query()
            ->with(['host.hostApplication', 'images', 'rates'])
            ->where('is_active', true)
            ->whereHas('host', fn ($hosts) => $hosts->where('is_active', true)->whereNotNull('profile_completed_at'))
            ->where(function ($bookable) {
                $bookable->whereNotIn('category', ['car', 'condo'])->orWhereHas('rates');
            })
            ->latest('id')
            ->limit(250)
            ->get()
            ->map(function (Unit $unit) use ($terms) {
                $property = $unit->property_details ?? [];
                $car = $unit->car_details ?? [];
                $businessName = $unit->host->publicHostName();
                $categoryAliases = match ($unit->category) {
                    'car' => 'car vehicle auto rental seater seats',
                    'condo' => 'condo residence property apartment house room stay bedroom br comfort bathroom',
                    default => str($unit->category)->replace('_', ' ')->toString().' service',
                };
                $haystack = Str::ascii(Str::lower(implode(' ', array_filter([
                    $unit->name,
                    $unit->description,
                    $unit->location,
                    $unit->category,
                    $categoryAliases,
                    $unit->capacity ? "{$unit->capacity} seater {$unit->capacity} seats capacity {$unit->capacity}" : null,
                    isset($property['bedrooms']) ? "{$property['bedrooms']} br {$property['bedrooms']} bedroom bedrooms" : null,
                    isset($property['bathrooms']) ? "{$property['bathrooms']} bathroom comfort room" : null,
                    implode(' ', $property['amenities'] ?? []),
                    $car['make'] ?? null,
                    $car['model'] ?? null,
                    implode(' ', $car['accessories'] ?? []),
                    $unit->host->name,
                    $businessName,
                ]))));

                if (! $terms->every(fn ($term) => str_contains($haystack, $term))) {
                    return null;
                }

                $nameText = Str::ascii(Str::lower($unit->name.' '.$businessName.' '.$unit->host->name));
                $score = $terms->sum(fn ($term) => str_contains($nameText, $term) ? 3 : 1);

                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'category' => str($unit->category)->replace('_', ' ')->title()->toString(),
                    'location' => $unit->location ?: 'Location arranged with host',
                    'host_name' => $unit->host->name,
                    'business_name' => $businessName,
                    'price' => (float) ($unit->isPackageRental() ? $unit->rates->min('price') : $unit->price),
                    'image_url' => $unit->primaryImagePath() ? Storage::disk('public')->url($unit->primaryImagePath()) : null,
                    'url' => route('listings.show', $unit),
                    'host_url' => route('hosts.show', $unit->host),
                    '_score' => $score,
                ];
            })
            ->filter()
            ->sortByDesc('_score')
            ->take(10)
            ->map(fn ($result) => collect($result)->except('_score')->all())
            ->values();

        return response()->json(['results' => $results]);
    }
}
