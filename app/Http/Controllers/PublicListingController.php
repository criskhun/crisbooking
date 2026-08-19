<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicListingController extends Controller
{
    public function show(Request $request, Unit $unit): View
    {
        abort_unless($unit->is_active && $unit->host()->whereNotNull('profile_completed_at')->exists(), 404);
        $unit->load([
            'host.hostApplication',
            'rates',
            'images',
            'listingReviews' => fn ($reviews) => $reviews->with('reviewer')->latest(),
        ])->loadAvg('listingReviews', 'rating')->loadCount('listingReviews');
        if ($request->user()) {
            $unit->loadExists(['favoritedBy as is_favorited' => fn ($favorites) => $favorites->where('users.id', $request->user()->id)]);
        }
        $affiliate = $this->affiliateFor($request->query('ref'), $unit);

        return view('listings.show', compact('unit', 'affiliate'));
    }

    public function inquire(Request $request, Unit $unit): RedirectResponse
    {
        abort_unless($unit->is_active, 404);
        $parameters = ['unit' => $unit];
        if ($this->affiliateFor($request->query('ref'), $unit)) {
            $parameters['ref'] = $request->query('ref');
        }

        return redirect()->to(route('listings.show', $parameters).'#listing-inquiry');
    }

    private function affiliateFor(mixed $code, Unit $unit): ?AffiliatePartnership
    {
        if (! is_string($code) || $code === '') {
            return null;
        }

        return AffiliatePartnership::query()
            ->where('referral_code', $code)
            ->where('host_id', $unit->host_id)
            ->where('status', 'accepted')
            ->whereHas('units', fn ($units) => $units->whereKey($unit->id))
            ->first();
    }
}
