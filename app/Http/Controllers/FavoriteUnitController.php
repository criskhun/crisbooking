<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteUnitController extends Controller
{
    public function __invoke(Request $request, Unit $unit): JsonResponse|RedirectResponse
    {
        abort_unless($unit->is_active && $unit->host()->whereNotNull('profile_completed_at')->exists(), 404);
        abort_if($unit->host_id === $request->user()->id, 422, 'You cannot save your own listing as a favorite.');

        $favorites = $request->user()->favoriteUnits();
        $favorited = ! $favorites->whereKey($unit->id)->exists();

        if ($favorited) {
            $favorites->syncWithoutDetaching([$unit->id]);
        } else {
            $favorites->detach($unit->id);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'favorited' => $favorited,
                'message' => $favorited ? 'Saved to your favorites.' : 'Removed from your favorites.',
            ]);
        }

        return back()->with('status', $favorited ? 'Listing saved to your favorites.' : 'Listing removed from your favorites.');
    }
}
