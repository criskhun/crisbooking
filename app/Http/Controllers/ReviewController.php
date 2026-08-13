<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Review;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function booking(Request $request, Booking $booking): RedirectResponse
    {
        $booking->loadMissing(['unit.host', 'client']);
        abort_unless($booking->status === 'confirmed' && $booking->end_at->isPast(), 422, 'Reviews unlock after a confirmed booking has ended.');

        if ($request->user()->id === $booking->client_id) {
            $reviewee = $booking->unit->host;
            $context = 'host';
        } elseif ($request->user()->id === $booking->unit->host_id) {
            $reviewee = $booking->client;
            $context = 'client';
        } else {
            abort(403);
        }

        $validated = $this->validatedReview($request);
        if (Review::query()->where('booking_id', $booking->id)->where('reviewer_id', $request->user()->id)->where('reviewee_id', $reviewee->id)->exists()) {
            throw ValidationException::withMessages(['rating' => 'You already reviewed this booking partner.']);
        }

        Review::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $reviewee->id,
            'reviewee_context' => $context,
            ...$validated,
        ]);
        $this->notify($reviewee, $request->user()->name, $context);

        return back()->with('status', 'Your review is now visible on '.$reviewee->name.'’s profile.');
    }

    public function affiliate(Request $request, AffiliatePartnership $affiliate): RedirectResponse
    {
        abort_unless(in_array($request->user()->id, [$affiliate->marketer_id, $affiliate->host_id], true) && $affiliate->isAccepted(), 403);
        $reviewee = $request->user()->id === $affiliate->host_id ? $affiliate->marketer : $affiliate->host;
        $context = $request->user()->id === $affiliate->host_id ? 'affiliate' : 'host';
        $validated = $this->validatedReview($request);

        if (Review::query()->where('affiliate_partnership_id', $affiliate->id)->where('reviewer_id', $request->user()->id)->where('reviewee_id', $reviewee->id)->exists()) {
            throw ValidationException::withMessages(['rating' => 'You already reviewed this affiliate partnership.']);
        }

        Review::create([
            'affiliate_partnership_id' => $affiliate->id,
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $reviewee->id,
            'reviewee_context' => $context,
            ...$validated,
        ]);
        $this->notify($reviewee, $request->user()->name, $context);

        return back()->with('status', 'Your partnership review is now visible on '.$reviewee->name.'’s profile.');
    }

    private function validatedReview(Request $request): array
    {
        return $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:1500'],
        ]);
    }

    private function notify(mixed $reviewee, string $reviewerName, string $context): void
    {
        app(AppNotificationService::class)->send(
            $reviewee,
            'review',
            'New profile review',
            $reviewerName.' left a review on your '.$context.' profile.',
            route('profiles.show', $reviewee),
        );
    }
}
