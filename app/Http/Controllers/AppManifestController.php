<?php

namespace App\Http\Controllers;

use App\Services\SystemBranding;
use Illuminate\Http\JsonResponse;

class AppManifestController extends Controller
{
    public function __invoke(SystemBranding $branding): JsonResponse
    {
        $settings = $branding->settings();
        $icons = $settings->favicon_path
            ? [['src' => $settings->favicon_url, 'sizes' => 'any', 'type' => mime_content_type(Storage_path('app/public/'.$settings->favicon_path)) ?: 'image/png']]
            : [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('icons/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        return response()->json([
            'name' => $settings->site_name,
            'short_name' => $settings->short_name,
            'description' => $settings->description,
            'start_url' => route('home'),
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#f6f3ea',
            'theme_color' => $settings->primary_color,
            'icons' => $icons,
        ])->header('Content-Type', 'application/manifest+json');
    }
}
