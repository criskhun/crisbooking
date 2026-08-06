<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->whereNotNull('rate_period')
            ->where('rate_quantity', 1)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $quantity = $this->packageQuantity(
                        Carbon::parse($booking->start_at),
                        Carbon::parse($booking->end_at),
                        $booking->rate_period,
                    );

                    if ($quantity <= 1) {
                        continue;
                    }

                    DB::table('bookings')->where('id', $booking->id)->update([
                        'rate_quantity' => $quantity,
                        'total_amount' => round((float) $booking->total_amount * $quantity, 2),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Package quantities cannot be safely reduced without losing approved pricing data.
    }

    private function packageQuantity(Carbon $start, Carbon $end, string $period): int
    {
        if ($period === 'month') {
            $quantity = 1;

            while ($start->copy()->addMonthsNoOverflow($quantity)->lt($end)) {
                $quantity++;
            }

            return $quantity;
        }

        $minutesPerPackage = match ($period) {
            '12_hours' => 720,
            'day' => 1440,
            'week' => 10080,
            default => max(1, (int) $start->diffInMinutes($end)),
        };

        return max(1, (int) ceil($start->diffInMinutes($end) / $minutesPerPackage));
    }
};
