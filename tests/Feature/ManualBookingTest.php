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

    public function test_outside_booking_customer_field_suggests_repeat_customers_from_accessible_listings(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $unit = $this->unit($host, 'Repeat Customer Condo', 'condo');
        $otherUnit = $this->unit($otherHost, 'Private Customer Condo', 'condo');
        $start = today()->subMonths(3);

        foreach (['Returning Company', 'Returning Company', 'Single Visit Guest'] as $index => $customerName) {
            Booking::create([
                'unit_id' => $unit->id,
                'client_id' => $host->id,
                'booked_by_user_id' => $host->id,
                'booking_origin' => 'manual',
                'external_customer_name' => $customerName,
                'start_at' => $start->copy()->addDays($index * 2)->setTime(14, 0),
                'end_at' => $start->copy()->addDays(($index * 2) + 1)->startOfDay(),
                'status' => 'confirmed',
                'total_amount' => 2500,
                'party_size' => 1,
            ]);
        }
        Booking::create([
            'unit_id' => $otherUnit->id,
            'client_id' => $otherHost->id,
            'booked_by_user_id' => $otherHost->id,
            'booking_origin' => 'manual',
            'external_customer_name' => 'Other Host Private Customer',
            'start_at' => $start->copy()->setTime(14, 0),
            'end_at' => $start->copy()->addDay()->startOfDay(),
            'status' => 'confirmed',
            'total_amount' => 2500,
            'party_size' => 1,
        ]);

        $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage']))
            ->assertOk()
            ->assertSee('list="manual_customer_suggestions"', false)
            ->assertSee('value="Returning Company" label="2 previous bookings"', false)
            ->assertSee('value="Single Visit Guest" label="1 previous booking"', false)
            ->assertDontSee('Other Host Private Customer');
    }

    public function test_host_can_record_a_past_outside_booking_in_the_calendar_and_sales(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Historical Direct Rental', 'condo');
        $start = today()->subMonths(2)->startOfMonth()->addDays(5);

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]))->assertOk()
            ->assertSee('value="'.$start->format('Y-m-d').'"', false)
            ->assertDontSee('name="start_date" type="date" min=', false)
            ->assertSee('Past dates are available only for recording outside bookings that already happened.');

        $response = $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            'unit_id' => $unit->id,
            'start_date' => $start->toDateString(),
            'start_time' => '09:00',
            'number_of_days' => 2,
            'source_channel' => 'direct',
            'external_customer_name' => 'Previous Walk-in Customer',
            'total_amount' => 7500,
            'party_size' => 2,
        ]);

        $response->assertRedirect(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]));
        $this->assertDatabaseHas('bookings', [
            'unit_id' => $unit->id,
            'booking_origin' => 'manual',
            'status' => 'confirmed',
            'total_amount' => 7500,
        ]);

        $this->actingAs($host)->from(route('calendar.index', ['mode' => 'manage']))
            ->post(route('calendar.manual-bookings.store'), [
                'unit_id' => $unit->id,
                'start_date' => $start->copy()->addDay()->toDateString(),
                'start_time' => '09:00',
                'number_of_days' => 1,
                'source_channel' => 'other',
                'total_amount' => 1000,
                'party_size' => 1,
            ])->assertRedirect(route('calendar.index', ['mode' => 'manage']))
            ->assertSessionHasErrors([
                'start_date' => 'The selected listing already has a booking during part of that date and time range.',
            ]);
        $this->assertDatabaseCount('bookings', 1);

        $this->actingAs($host)->get(route('sales.index'))
            ->assertOk()
            ->assertSee('₱7,500.00')
            ->assertSee('Previous Walk-in Customer');
    }

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
            'start_time' => '09:00',
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
            ->assertSee('<strong>External Airbnb Guest · Airbnb</strong>', false)
            ->assertSee('AIR-48291')
            ->assertSee('data-calendar-booking-open', false);

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'calendar_view' => 'month',
            'month' => $start->format('Y-m'),
        ]))->assertOk()
            ->assertSee('External Airbnb Guest · Airbnb Harbor Condo · Airbnb');

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
            ->assertSee('Time blocked')
            ->assertSee('Airbnb · AIR-48291')
            ->assertSee('Offline Affiliate')
            ->assertSee('Cancel outside booking & release dates', false);
    }

    public function test_one_day_outside_condo_and_car_bookings_use_real_times_and_span_two_calendar_dates(): void
    {
        $host = User::factory()->host()->create();
        $condo = $this->unit($host, 'Timed Harbor Condo', 'condo');
        $condo->update(['property_details' => [
            'type' => 'condo',
            'check_in_time' => '14:00',
            'check_out_time' => '00:00',
        ]]);
        $car = $this->unit($host, 'Timed Touring Car', 'car');
        $month = today()->addMonth()->startOfMonth();
        $condoDate = $month->copy()->addDays(9);
        $carDate = $month->copy()->addDays(12);
        $payload = [
            'number_of_days' => 1,
            'source_channel' => 'direct',
            'total_amount' => 2500,
            'party_size' => 1,
        ];

        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            ...$payload,
            'unit_id' => $condo->id,
            'start_date' => $condoDate->toDateString(),
            'start_time' => '09:15',
        ])->assertRedirect();
        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            ...$payload,
            'unit_id' => $car->id,
            'start_date' => $carDate->toDateString(),
            'start_time' => '14:30',
        ])->assertRedirect();

        $condoBooking = Booking::where('unit_id', $condo->id)->sole();
        $carBooking = Booking::where('unit_id', $car->id)->sole();
        $this->assertSame($condoDate->format('Y-m-d').' 14:00', $condoBooking->start_at->format('Y-m-d H:i'));
        $this->assertSame($condoDate->copy()->addDay()->format('Y-m-d').' 00:00', $condoBooking->end_at->format('Y-m-d H:i'));
        $this->assertSame($carDate->format('Y-m-d').' 14:30', $carBooking->start_at->format('Y-m-d H:i'));
        $this->assertSame($carDate->copy()->addDay()->format('Y-m-d').' 14:30', $carBooking->end_at->format('Y-m-d H:i'));

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $month->format('Y-m'),
        ]))->assertOk()
            ->assertSee('data-booking-id="'.$condoBooking->id.'"', false)
            ->assertSee('data-segment-start="'.$condoDate->format('Y-m-d').'"', false)
            ->assertSee('data-segment-end="'.$condoDate->copy()->addDay()->format('Y-m-d').'"', false)
            ->assertSee('data-booking-id="'.$carBooking->id.'"', false)
            ->assertSee('data-segment-start="'.$carDate->format('Y-m-d').'"', false)
            ->assertSee('data-segment-end="'.$carDate->copy()->addDay()->format('Y-m-d').'"', false);

        $this->actingAs($host)->from(route('calendar.index', ['mode' => 'manage']))
            ->post(route('calendar.manual-bookings.store'), [
                ...$payload,
                'unit_id' => $car->id,
                'start_date' => $carDate->copy()->addDay()->toDateString(),
                'start_time' => '13:00',
            ])->assertRedirect(route('calendar.index', ['mode' => 'manage']))
            ->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_host_can_record_an_hourly_outside_service_with_an_exact_end_time(): void
    {
        $host = User::factory()->host()->create();
        $service = $this->unit($host, 'Three Hour Driving Service', 'driving');
        $startDate = today()->addDays(11);

        $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage']))
            ->assertOk()
            ->assertSee('name="duration_unit"', false)
            ->assertSee('value="hour"', false)
            ->assertSee('Number of days');

        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            'unit_id' => $service->id,
            'start_date' => $startDate->toDateString(),
            'start_time' => '08:30',
            'duration_unit' => 'hour',
            'duration_quantity' => 3,
            'source_channel' => 'direct',
            'external_customer_name' => 'Hourly Service Customer',
            'total_amount' => 1800,
            'party_size' => 1,
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame($startDate->format('Y-m-d').' 08:30', $booking->start_at->format('Y-m-d H:i'));
        $this->assertSame($startDate->format('Y-m-d').' 11:30', $booking->end_at->format('Y-m-d H:i'));
        $this->assertSame('hour', $booking->rate_period);
        $this->assertSame(3, $booking->rate_quantity);
        $this->assertSame('3 hours', $booking->durationDisplayLabel());

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Time blocked')
            ->assertSee('3 hours')
            ->assertSee('8:30 AM')
            ->assertSee('11:30 AM');
    }

    public function test_twelve_hour_car_rental_spans_calendar_dates_and_blocks_only_its_exact_hours(): void
    {
        $host = User::factory()->host()->create();
        $car = $this->unit($host, 'Twelve Hour Rental Car', 'car');
        $startDate = today()->addMonth()->startOfMonth()->addDays(4);
        $payload = [
            'unit_id' => $car->id,
            'start_date' => $startDate->toDateString(),
            'start_time' => '14:00',
            'duration_unit' => 'hour',
            'duration_quantity' => 12,
            'source_channel' => 'direct',
            'total_amount' => 2500,
            'party_size' => 2,
        ];

        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), $payload)->assertRedirect();
        $booking = Booking::query()->sole();
        $this->assertSame($startDate->format('Y-m-d').' 14:00', $booking->start_at->format('Y-m-d H:i'));
        $this->assertSame($startDate->copy()->addDay()->format('Y-m-d').' 02:00', $booking->end_at->format('Y-m-d H:i'));
        $this->assertSame('12 hours', $booking->durationDisplayLabel());

        $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $startDate->format('Y-m'),
        ]))->assertOk()
            ->assertSee('data-booking-id="'.$booking->id.'"', false)
            ->assertSee('data-segment-start="'.$startDate->format('Y-m-d').'"', false)
            ->assertSee('data-segment-end="'.$startDate->copy()->addDay()->format('Y-m-d').'"', false);

        $this->actingAs($host)->from(route('calendar.index', ['mode' => 'manage']))
            ->post(route('calendar.manual-bookings.store'), [
                ...$payload,
                'start_date' => $startDate->copy()->addDay()->toDateString(),
                'start_time' => '01:00',
                'duration_quantity' => 2,
            ])->assertRedirect(route('calendar.index', ['mode' => 'manage']))
            ->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_legacy_midnight_outside_condo_booking_times_are_repaired_from_the_listing(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $condo = $this->unit($host, 'Legacy Timed Condo', 'condo');
        $condo->update(['property_details' => [
            'check_in_time' => '14:00',
            'check_out_time' => '00:00',
        ]]);
        $start = today()->subMonth()->startOfMonth()->addDays(4);
        $legacy = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'start_at' => $start->copy()->startOfDay(),
            'end_at' => $start->copy()->addDay()->startOfDay(),
            'status' => 'confirmed',
            'total_amount' => 3000,
            'party_size' => 1,
        ]);
        $alreadyTimed = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'start_at' => $start->copy()->addDays(3)->setTime(9, 30),
            'end_at' => $start->copy()->addDays(4)->setTime(10, 30),
            'status' => 'confirmed',
            'total_amount' => 3000,
            'party_size' => 1,
        ]);
        $platformBooking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $client->id,
            'booking_origin' => 'platform',
            'start_at' => $start->copy()->addDays(6)->startOfDay(),
            'end_at' => $start->copy()->addDays(7)->startOfDay(),
            'status' => 'confirmed',
            'total_amount' => 3000,
            'party_size' => 1,
        ]);

        $migration = require database_path('migrations/2026_08_30_010000_repair_legacy_manual_condo_booking_times.php');
        $migration->up();

        $this->assertSame('14:00', $legacy->fresh()->start_at->format('H:i'));
        $this->assertSame('00:00', $legacy->fresh()->end_at->format('H:i'));
        $this->assertSame('09:30', $alreadyTimed->fresh()->start_at->format('H:i'));
        $this->assertSame('10:30', $alreadyTimed->fresh()->end_at->format('H:i'));
        $this->assertSame('00:00', $platformBooking->fresh()->start_at->format('H:i'));
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
            'start_time' => '14:30',
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
            'start_time' => '08:00',
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

    public function test_host_can_correct_outside_booking_details_with_an_immutable_audit_trail(): void
    {
        $host = User::factory()->host()->create(['name' => 'Reservation Host']);
        $affiliate = User::factory()->create(['name' => 'Correct Affiliate']);
        $unit = $this->unit($host, 'Correction Condo', 'condo');
        $unit->update(['property_details' => [
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ]]);
        $partnership = $this->partnership($host, $affiliate, $unit, 12.5);
        $originalStart = today()->addDays(10);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booked_by_user_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'external_customer_name' => 'Typo Customer',
            'start_at' => $originalStart->copy()->setTime(14, 0),
            'end_at' => $originalStart->copy()->addDay()->setTime(12, 0),
            'status' => 'confirmed',
            'rate_period' => 'day',
            'rate_quantity' => 1,
            'total_amount' => 8000,
            'party_size' => 1,
        ]);
        $correctedStart = today()->addDays(20);

        $response = $this->actingAs($host)->patch(route('bookings.manual-details.update', $booking), [
            'start_at' => $correctedStart->copy()->setTime(8, 15)->format('Y-m-d\TH:i'),
            'end_at' => $correctedStart->copy()->addDays(7)->setTime(23, 30)->format('Y-m-d\TH:i'),
            'party_size' => 4,
            'source_channel' => 'airbnb',
            'source_details' => 'AIR-CORRECT-18',
            'external_customer_name' => 'Correct Customer Company',
            'affiliate_partnership_id' => $partnership->id,
            'package_period' => 'week',
            'package_quantity' => 1,
            'correction_reason' => 'Corrected the dates and customer details from the handwritten reservation.',
        ]);

        $response->assertRedirect()->assertSessionHas('status');
        $booking->refresh();
        $this->assertSame($correctedStart->format('Y-m-d').' 14:00', $booking->start_at->format('Y-m-d H:i'));
        $this->assertSame($correctedStart->copy()->addDays(7)->format('Y-m-d').' 12:00', $booking->end_at->format('Y-m-d H:i'));
        $this->assertSame(4, $booking->party_size);
        $this->assertSame('airbnb', $booking->source_channel);
        $this->assertSame('AIR-CORRECT-18', $booking->source_details);
        $this->assertSame('Correct Customer Company', $booking->external_customer_name);
        $this->assertSame($partnership->id, $booking->affiliate_partnership_id);
        $this->assertSame('1000.00', $booking->affiliate_commission_amount);
        $this->assertSame('week', $booking->rate_period);
        $this->assertSame(1, $booking->rate_quantity);
        $this->assertSame(1, $booking->package_breakdown['week']['quantity']);
        $this->assertSame('8000.00', $booking->total_amount);
        $this->assertDatabaseHas('booking_detail_revisions', [
            'booking_id' => $booking->id,
            'edited_by_user_id' => $host->id,
            'reason' => 'Corrected the dates and customer details from the handwritten reservation.',
        ]);

        $revision = $booking->detailRevisions()->sole();
        $this->assertSame('1 person', $revision->before_values['guests_pax']);
        $this->assertSame('4 people', $revision->after_values['guests_pax']);
        $this->assertSame('1 × 1 week', $revision->after_values['package']);

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Edit reservation details')
            ->assertSee('Reservation audit trail')
            ->assertSee('Corrected the dates and customer details')
            ->assertSee('Correct Customer Company')
            ->assertSee('Correct Affiliate');
    }

    public function test_outside_booking_correction_rejects_a_conflicting_schedule_without_writing_history(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Conflict Condo', 'condo');
        $start = today()->addDays(10);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'start_at' => $start->copy()->setTime(14, 0),
            'end_at' => $start->copy()->addDay()->setTime(12, 0),
            'status' => 'confirmed',
            'rate_period' => 'day',
            'rate_quantity' => 1,
            'total_amount' => 3000,
            'party_size' => 1,
        ]);
        $blockedStart = today()->addDays(30);
        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'start_at' => $blockedStart->copy()->setTime(14, 0),
            'end_at' => $blockedStart->copy()->addDays(2)->setTime(12, 0),
            'status' => 'confirmed',
            'total_amount' => 6000,
            'party_size' => 2,
        ]);

        $this->actingAs($host)->from(route('bookings.show', $booking))
            ->patch(route('bookings.manual-details.update', $booking), [
                'start_at' => $blockedStart->copy()->addDay()->format('Y-m-d\TH:i'),
                'end_at' => $blockedStart->copy()->addDays(3)->format('Y-m-d\TH:i'),
                'party_size' => 2,
                'source_channel' => 'direct',
                'package_period' => 'day',
                'package_quantity' => 2,
                'correction_reason' => 'Trying to correct the reservation dates.',
            ])->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('start_at');

        $this->assertTrue($booking->fresh()->start_at->equalTo($start->copy()->setTime(14, 0)));
        $this->assertDatabaseCount('booking_detail_revisions', 0);
    }

    public function test_only_the_listing_host_or_admin_can_correct_an_outside_booking(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $unit = $this->unit($host, 'Protected Outside Booking', 'car');
        $start = today()->addDays(12)->setTime(9, 0);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'start_at' => $start,
            'end_at' => $start->copy()->addDay(),
            'status' => 'confirmed',
            'rate_period' => 'day',
            'rate_quantity' => 1,
            'total_amount' => 3000,
            'party_size' => 1,
        ]);
        $payload = [
            'start_at' => $start->format('Y-m-d\TH:i'),
            'end_at' => $start->copy()->addDays(2)->format('Y-m-d\TH:i'),
            'party_size' => 1,
            'source_channel' => 'direct',
            'package_period' => 'day',
            'package_quantity' => 2,
            'correction_reason' => 'Correcting the rental duration.',
        ];

        $this->actingAs($otherHost)->patch(route('bookings.manual-details.update', $booking), $payload)->assertForbidden();

        $platformBooking = $booking->replicate()->fill([
            'booking_origin' => 'platform',
            'start_at' => $start->copy()->addDays(5),
            'end_at' => $start->copy()->addDays(6),
        ]);
        $platformBooking->save();
        $this->actingAs($host)->patch(route('bookings.manual-details.update', $platformBooking), $payload)->assertStatus(422);
        $this->assertDatabaseCount('booking_detail_revisions', 0);
    }

    public function test_identical_outside_booking_details_do_not_create_an_empty_audit_entry(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Unchanged Condo Booking', 'condo');
        $start = today()->addDays(15)->setTime(14, 0);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'start_at' => $start,
            'end_at' => $start->copy()->addDay()->setTime(12, 0),
            'status' => 'confirmed',
            'rate_period' => 'day',
            'rate_quantity' => 1,
            'total_amount' => 3000,
            'party_size' => 1,
        ]);

        $this->actingAs($host)->from(route('bookings.show', $booking))
            ->patch(route('bookings.manual-details.update', $booking), [
                'start_at' => $booking->start_at->format('Y-m-d\TH:i'),
                'end_at' => $booking->end_at->format('Y-m-d\TH:i'),
                'party_size' => 1,
                'source_channel' => 'direct',
                'package_period' => 'day',
                'package_quantity' => 1,
                'correction_reason' => 'No actual change was made.',
            ])->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('correction_reason');

        $this->assertDatabaseCount('booking_detail_revisions', 0);
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
