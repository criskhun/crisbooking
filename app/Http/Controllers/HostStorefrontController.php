<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class HostStorefrontController extends Controller
{
    public function __invoke(User $host): View
    {
        abort_unless($host->is_active && ($host->isHost() || $host->is_admin), 404);
        $host->load([
            'hostApplication',
            'reviewsReceived' => fn ($reviews) => $reviews->where('reviewee_context', 'host')->latest()->limit(12),
            'units' => fn ($units) => $units->with(['rates', 'images'])
                ->where('is_active', true)
                ->where(function ($bookable) {
                    $bookable->whereNotIn('category', ['car', 'condo'])->orWhereHas('rates');
                })
                ->orderBy('name'),
        ]);

        abort_if($host->units->isEmpty() && ! $host->hasCompleteProfile(), 404);

        return view('hosts.show', [
            'hostUser' => $host,
            'businessName' => $host->publicHostName(),
            'hostRating' => $host->reviewsReceived->isNotEmpty()
                ? round((float) $host->reviewsReceived->avg('rating'), 1)
                : null,
        ]);
    }
}
