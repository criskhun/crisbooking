<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteUnitController extends Controller
{
    public function index(Request $request): View
    {
        $favorites = $request->user()->favoriteUnits()
            ->with(['host.hostApplication', 'rates', 'images'])
            ->withAvg('listingReviews', 'rating')
            ->withCount('listingReviews')
            ->where('units.is_active', true)
            ->whereHas('host', fn ($host) => $host->whereNotNull('profile_completed_at'))
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->each(fn (Unit $unit) => $unit->setAttribute('is_favorited', true));

        return view('favorites.index', compact('favorites'));
    }

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

    public function afterLogin(Request $request, Unit $unit): RedirectResponse
    {
        abort_unless($unit->is_active && $unit->host()->whereNotNull('profile_completed_at')->exists(), 404);
        abort_if($unit->host_id === $request->user()->id, 422, 'You cannot save your own listing as a favorite.');

        if (! $request->user()->hasVerifiedEmail()) {
            $request->session()->put('url.intended', route('listings.favorite.after-login', $unit));

            return redirect()->route('verification.notice')->withErrors([
                'email' => 'Verify your email address to save this listing.',
            ]);
        }

        $request->user()->favoriteUnits()->syncWithoutDetaching([$unit->id]);

        return redirect()->route('listings.show', $unit)->with('status', 'Listing saved to your favorites.');
    }
}
