<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProfileLocationController extends Controller
{
    private const NCR_CODE = '1300000000';

    public function provinces(): JsonResponse
    {
        $items = $this->get('/provinces');
        $items[] = ['code' => self::NCR_CODE, 'name' => 'Metro Manila'];

        return response()->json($this->sorted($items));
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate(['province_code' => ['required', 'digits:10']]);
        $code = $validated['province_code'];
        $path = $code === self::NCR_CODE
            ? "/regions/{$code}/cities-municipalities"
            : "/provinces/{$code}/cities-municipalities";

        $items = collect($this->get($path))
            ->reject(fn ($item) => ($item['type'] ?? null) === 'SubMun')
            ->all();

        return response()->json($this->sorted($items));
    }

    public function barangays(Request $request): JsonResponse
    {
        $validated = $request->validate(['city_code' => ['required', 'digits:10']]);
        $code = $validated['city_code'];
        $items = $this->get("/cities-municipalities/{$code}/barangays");

        if ($items === [] && $code === '1380600000') {
            $items = collect($this->get('/regions/'.self::NCR_CODE.'/cities-municipalities'))
                ->where('type', 'SubMun')
                ->values()
                ->all();
        }

        return response()->json($this->sorted($items));
    }

    private function get(string $path): array
    {
        return $this->getAbsolute('https://psgc.cloud/api/v2'.$path);
    }

    private function getAbsolute(string $url): array
    {
        return Cache::remember('psgc:'.sha1($url), now()->addDays(30), function () use ($url) {
            $response = Http::acceptJson()
                ->timeout(12)
                ->retry(2, 200)
                ->get($url)
                ->throw();

            return $response->json('data') ?? $response->json();
        });
    }

    private function sorted(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => isset($item['code'], $item['name']))
            ->map(function ($item) {
                $name = (string) $item['name'];
                if (str_contains($name, 'Ã')) $name = mb_convert_encoding($name, 'Windows-1252', 'UTF-8');

                return ['code' => (string) $item['code'], 'name' => $name];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
