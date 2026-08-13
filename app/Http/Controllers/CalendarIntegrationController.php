<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CalendarIntegrationController extends Controller
{
    public function refresh(Request $request): RedirectResponse
    {
        $request->user()->update(['calendar_feed_token' => Str::random(48)]);

        return back()->with('status', 'Your private calendar subscription link is ready. Any previous link has been disabled.');
    }

    public function feed(User $user, string $token): Response
    {
        abort_unless($user->is_active && $user->calendar_feed_token && hash_equals($user->calendar_feed_token, $token), 404);

        $bookings = Booking::query()
            ->with(['unit.host:id,name', 'client:id,name'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($user) {
                $query->where('client_id', $user->id)
                    ->orWhereHas('unit', fn ($units) => $units->where('host_id', $user->id));
            })
            ->orderBy('start_at')
            ->get();

        return $this->calendarResponse($bookings, 'Davao Rent Zone bookings', 'davao-rent-zone-'.$user->id.'.ics');
    }

    public function booking(Request $request, Booking $booking): Response
    {
        abort_unless($this->canView($request->user(), $booking), 403);
        $booking->loadMissing(['unit.host:id,name', 'client:id,name']);

        return $this->calendarResponse(collect([$booking]), $booking->unit->name, 'booking-'.$booking->id.'.ics');
    }

    private function canView(User $user, Booking $booking): bool
    {
        return $user->is_admin
            || $booking->client_id === $user->id
            || $booking->unit()->where('host_id', $user->id)->exists();
    }

    private function calendarResponse(mixed $bookings, string $name, string $filename): Response
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Davao Rent Zone//Booking Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
            'X-WR-TIMEZONE:Asia/Manila',
        ];

        foreach ($bookings as $booking) {
            $summary = $booking->unit->name.' · '.ucfirst($booking->status);
            $description = "Booking #{$booking->id}\nClient: {$booking->client->name}\nHost: {$booking->unit->host->name}\nStatus: ".ucfirst($booking->status);
            $lines = [...$lines,
                'BEGIN:VEVENT',
                'UID:booking-'.$booking->id.'@davaorentzone.com',
                'DTSTAMP:'.$booking->updated_at->copy()->utc()->format('Ymd\THis\Z'),
                'DTSTART:'.$booking->start_at->copy()->utc()->format('Ymd\THis\Z'),
                'DTEND:'.$booking->end_at->copy()->utc()->format('Ymd\THis\Z'),
                'SUMMARY:'.$this->escape($summary),
                'DESCRIPTION:'.$this->escape($description),
                'LOCATION:'.$this->escape($booking->unit->location ?: 'Coordinate with booking partner'),
                'URL:'.route('bookings.show', $booking),
                'STATUS:'.($booking->status === 'confirmed' ? 'CONFIRMED' : 'TENTATIVE'),
                'END:VEVENT',
            ];
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function escape(mixed $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", '\\;', '\\,', '\\n', '\\n', '\\n'], (string) $value);
    }
}
