<?php

namespace Tests\Feature;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_block_own_listing_and_record_external_sale_with_affiliate_credit(): void
    {
        $host = User::factory()->host()->create();
        $affiliate = User::factory()->create(['name' => 'Offline Affiliate']);
        $unit = $this->unit($host, 'Airbnb Harbor Condo', 'condo');
        $partnership = $this->partnership($host, $affiliate, $unit, 10);
        $start = today()->addDays(10);

        $response = $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            'unit_id' => $unit->id,
            'start_date' => $start->toDateString(),
            'number_of_days' => 3,
            'source_channel' => 'airbnb',
            'source_details' => 'AIR-48291',
            'external_customer_name' => 'External Airbnb Guest',
            'total_amount' => 12000,
            'party_size' => 3,
            'affiliate_partnership_id' => $partnership->id,
            'notes' => 'Guest will arrive after lunch.',
        ]);

        $booking = Booking::query()->sole();
        $response->assertRedirect(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]));
        $this->assertSame('manual', $booking->booking_origin);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('airbnb', $booking->source_channel);
        $this->assertSame('AIR-48291', $booking->source_details);
        $this->assertSame('External Airbnb Guest', $booking->external_customer_name);
        $this->assertSame($host->id, $booking->client_id);
        $this->assertSame($host->id, $booking->booked_by_user_id);
        $this->assertSame($partnership->id, $booking->affiliate_partnership_id);
        $this->assertSame('1200.00', $booking->affiliate_commission_amount);
        $this->assertSame(3, $booking->durationDays());
        $this->assertTrue($booking->end_at->isSameDay($start->copy()->addDays(3)));

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'calendar_view' => 'listings',
            'month' => $start->format('Y-m'),
        ]))->assertOk()
            ->assertSee('Add an outside booking')
            ->assertSee('Airbnb')
            ->assertSee('External Airbnb Guest')
            ->assertSee('AIR-48291')
            ->assertSee('data-calendar-booking-open', false);

        $this->actingAs($affiliate)->get(route('calendar.index', [
            'mode' => 'manage',
            'calendar_view' => 'listings',
            'month' => $start->format('Y-m'),
        ]))->assertOk()
            ->assertSee('Affiliate calendar')
            ->assertSee('Airbnb')
            ->assertSee('External Airbnb Guest')
            ->assertSee('data-booking-id="'.$booking->id.'"', false);

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Outside booking recorded')
            ->assertSee('Days blocked')
            ->assertSee('Airbnb · AIR-48291')
            ->assertSee('Offline Affiliate')
            ->assertSee('Cancel outside booking & release dates', false);
    }

    public function test_affiliate_can_add_outside_booking_only_to_an_assigned_listing(): void
    {
        $host = User::factory()->host()->create();
        $affiliate = User::factory()->create();
        $assigned = $this->unit($host, 'Assigned Touring Car', 'car');
        $unassigned = $this->unit($host, 'Private Touring Car', 'car');
        $partnership = $this->partnership($host, $affiliate, $assigned, 12.5);
        $start = today()->addDays(15);
        $payload = [
            'start_date' => $start->toDateString(),
            'number_of_days' => 2,
            'source_channel' => 'booking_com',
            'source_details' => 'BC-1009',
            'external_customer_name' => 'Car Rental Customer',
            'total_amount' => 8000,
            'party_size' => 4,
        ];

        $this->actingAs($affiliate)->post(route('calendar.manual-bookings.store'), [
            ...$payload,
            'unit_id' => $assigned->id,
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame($affiliate->id, $booking->booked_by_user_id);
        $this->assertSame($partnership->id, $booking->affiliate_partnership_id);
        $this->assertSame('1000.00', $booking->affiliate_commission_amount);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'manual_booking_created',
        ]);

        $this->actingAs($affiliate)->post(route('calendar.manual-bookings.store'), [
            ...$payload,
            'unit_id' => $unassigned->id,
            'start_date' => $start->copy()->addDays(5)->toDateString(),
        ])->assertForbidden();
        $this->assertDatabaseCount('bookings', 1);

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
        ]))->assertOk()
            ->assertSee('Booking.com')
            ->assertSee('Car Rental Customer');

        $this->actingAs($affiliate)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking.com · BC-1009');
        $this->actingAs($affiliate)->patch(route('bookings.cancel', $booking))
            ->assertRedirect()
            ->assertSessionHas('status', 'Outside booking cancelled. The dates are available again.');
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'manual_booking_cancelled',
        ]);
    }

    public function test_manual_booking_rejects_blocked_dates_and_closes_overlapping_pending_requests(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, 'Service Schedule', 'cleaning');
        $start = today()->addDays(20);
        $pending = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start->copy()->addHours(8),
            'end_at' => $start->copy()->addHours(12),
            'status' => 'pending',
            'total_amount' => 1500,
            'party_size' => 1,
        ]);
        $payload = [
            'unit_id' => $unit->id,
            'start_date' => $start->toDateString(),
            'number_of_days' => 1,
            'source_channel' => 'walk_in_phone',
            'external_customer_name' => 'Phone Customer',
            'total_amount' => 2000,
            'party_size' => 1,
        ];

        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), $payload)->assertRedirect();
        $this->assertSame('unavailable', $pending->fresh()->status);

        $this->actingAs($host)->from(route('calendar.index', ['mode' => 'manage']))
            ->post(route('calendar.manual-bookings.store'), [
                ...$payload,
                'source_channel' => 'direct',
            ])->assertRedirect(route('calendar.index', ['mode' => 'manage']))
            ->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('bookings', 2);
    }

    private function unit(User $host, string $name, string $category): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => in_array($category, ['condo', 'car'], true) ? 'unit' : 'service',
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Follow the host instructions.',
            'capacity' => 6,
            'price' => 2000,
            'pricing_unit' => 'day',
            'is_active' => true,
        ]);
    }

    private function partnership(User $host, User $affiliate, Unit $unit, float $commission): AffiliatePartnership
    {
        $partnership = AffiliatePartnership::create([
            'marketer_id' => $affiliate->id,
            'host_id' => $host->id,
            'status' => 'accepted',
            'commission_percentage' => $commission,
            'referral_code' => 'MANUAL'.str()->random(12),
            'application_message' => 'I will bring outside customers to this listing.',
            'reviewed_at' => now(),
        ]);
        $partnership->units()->attach($unit);

        return $partnership;
    }
}
