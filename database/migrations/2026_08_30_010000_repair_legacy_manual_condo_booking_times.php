<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->join('units', 'units.id', '=', 'bookings.unit_id')
            ->where('bookings.booking_origin', 'manual')
            ->where('units.category', 'condo')
            ->select([
                'bookings.id as booking_id',
                'bookings.start_at',
                'bookings.end_at',
                'units.property_details',
            ])
            ->orderBy('bookings.id')
            ->get()
            ->each(function (object $booking): void {
                $start = Carbon::parse($booking->start_at);

                // The previous outside-booking form always saved midnight. Do not
                // alter records that already carry a deliberate non-midnight time.
                if ($start->format('H:i:s') !== '00:00:00') {
                    return;
                }

                $property = json_decode((string) $booking->property_details, true) ?: [];
                $checkIn = $this->validTime($property['check_in_time'] ?? null, '14:00');
                $checkOut = $this->validTime($property['check_out_time'] ?? null, '12:00');
                [$checkInHour, $checkInMinute] = array_map('intval', explode(':', $checkIn));
                [$checkOutHour, $checkOutMinute] = array_map('intval', explode(':', $checkOut));

                DB::table('bookings')->where('id', $booking->booking_id)->update([
                    'start_at' => $start->setTime($checkInHour, $checkInMinute),
                    'end_at' => Carbon::parse($booking->end_at)->setTime($checkOutHour, $checkOutMinute),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // The original midnight values cannot be distinguished safely after repair.
    }

    private function validTime(mixed $value, string $fallback): string
    {
        $time = (string) $value;

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : $fallback;
    }
};
