<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSyncSessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
            'user_id' => $request->user()->id,
        ]);
    }
}
