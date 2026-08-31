<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_outside_booking_tracks_downpayment_deposit_damage_and_final_collection(): void
    {
        $host = User::factory()->host()->create();
        $other = User::factory()->host()->create();
        $car = $this->unit($host, 'Ledger Rental Car', 'car');
        $start = today()->addDays(3);

        $this->actingAs($host)->post(route('calendar.manual-bookings.store'), [
            'unit_id' => $car->id,
            'start_date' => $start->toDateString(),
            'start_time' => '14:00',
            'number_of_days' => 1,
            'source_channel' => 'direct',
            'external_customer_name' => 'Deposit Customer',
            'total_amount' => '5,000.00',
            'payment_option' => 'downpayment',
            'initial_payment_amount' => '2,000.00',
            'security_deposit_amount' => '1,500.00',
            'security_deposit_collected' => 1,
            'party_size' => 1,
        ])->assertRedirect();

        $booking = Booking::with('financialEntries')->sole();
        $this->assertSame(3000.0, $booking->outstandingBalance());
        $this->assertSame(1500.0, $booking->securityDepositHeld());
        $this->assertSame('Partially paid', $booking->paymentStatusLabel());
        $this->assertDatabaseHas('booking_financial_entries', ['booking_id' => $booking->id, 'kind' => 'payment', 'category' => 'downpayment', 'amount' => 2000]);
        $this->assertDatabaseHas('booking_financial_entries', ['booking_id' => $booking->id, 'kind' => 'deposit', 'amount' => 1500]);

        $downpayment = $booking->financialEntries->firstWhere('category', 'downpayment');
        $this->actingAs($other)->patch(route('bookings.financial-entries.update', [$booking, $downpayment]), [
            'category' => 'downpayment', 'amount' => '2,100.00', 'occurred_at' => now()->format('Y-m-d H:i:s'),
            'correction_reason' => 'Correcting a typing error.',
        ])->assertForbidden();
        $this->actingAs($host)->from(route('bookings.show', $booking))->patch(route('bookings.financial-entries.update', [$booking, $downpayment]), [
            'category' => 'downpayment', 'amount' => '2,100.00', 'occurred_at' => $downpayment->occurred_at->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('bookings.show', $booking))->assertSessionHasErrors('correction_reason');
        $this->actingAs($host)->patch(route('bookings.financial-entries.update', [$booking, $downpayment]), [
            'category' => 'downpayment', 'amount' => '2,100.00', 'occurred_at' => $downpayment->occurred_at->format('Y-m-d H:i:s'),
            'notes' => 'Corrected cash downpayment.', 'correction_reason' => 'The original amount was entered with a typing error.',
        ])->assertRedirect();
        $this->assertDatabaseHas('booking_financial_entries', ['id' => $downpayment->id, 'amount' => 2100, 'notes' => 'Corrected cash downpayment.']);
        $this->assertDatabaseHas('booking_financial_entry_revisions', [
            'booking_financial_entry_id' => $downpayment->id,
            'edited_by_user_id' => $host->id,
            'reason' => 'The original amount was entered with a typing error.',
        ]);
        $revision = $downpayment->revisions()->firstOrFail();
        $this->assertSame('2000.00', $revision->before_values['amount']);
        $this->assertSame('2100.00', $revision->after_values['amount']);

        $this->actingAs($other)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'charge', 'category' => 'damage', 'amount' => 2000,
        ])->assertForbidden();
        $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'charge', 'category' => 'damage', 'amount' => 2000, 'notes' => 'Damaged side mirror.',
        ])->assertRedirect();
        $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'deposit_application', 'amount' => 1500, 'notes' => 'Applied held deposit to damage.',
        ])->assertRedirect();
        $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'payment', 'category' => 'balance_payment', 'amount' => '3,400.00',
        ])->assertRedirect();

        $booking->refresh()->load('financialEntries');
        $this->assertSame(7000.0, $booking->revenueAmount());
        $this->assertSame(0.0, $booking->outstandingBalance());
        $this->assertSame(0.0, $booking->securityDepositHeld());
        $this->assertSame('Fully paid', $booking->paymentStatusLabel());

        $this->actingAs($host)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Booking financial ledger')
            ->assertSee('Damage fee')
            ->assertSee('Damaged side mirror.')
            ->assertSee('Correction history')
            ->assertSee('The original amount was entered with a typing error.')
            ->assertSee('₱2,000.00 → ₱2,100.00')
            ->assertSee('Fully paid');
        $this->actingAs($host)->get(route('sales.index'))->assertOk()->assertSee('₱7,000.00');
    }

    public function test_residence_penalties_are_available_in_the_financial_ledger(): void
    {
        $host = User::factory()->host()->create();
        $condo = $this->unit($host, 'Penalty Residence', 'condo');
        $booking = Booking::create([
            'unit_id' => $condo->id, 'client_id' => $host->id, 'booking_origin' => 'manual',
            'start_at' => now()->subDay(), 'end_at' => now(), 'status' => 'confirmed',
            'total_amount' => 4000, 'party_size' => 1,
        ]);

        $this->actingAs($host)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Late check-out')
            ->assertSee('Smoking penalty')
            ->assertSee('Garbage / excessive cleaning');
    }

    public function test_host_can_extend_an_outside_booking_and_choose_paid_or_collectible_earnings(): void
    {
        $host = User::factory()->host()->create(['name' => 'Extension Host']);
        $condo = $this->unit($host, 'Extension Residence', 'condo');
        $start = today()->addDays(5)->setTime(14, 0);
        $booking = Booking::create([
            'unit_id' => $condo->id, 'client_id' => $host->id, 'booked_by_user_id' => $host->id,
            'booking_origin' => 'manual', 'source_channel' => 'direct', 'start_at' => $start,
            'end_at' => $start->copy()->addDay()->setTime(12, 0), 'status' => 'confirmed',
            'rate_period' => 'day', 'rate_quantity' => 1, 'total_amount' => 5000, 'party_size' => 1,
        ]);
        $booking->financialEntries()->create([
            'recorded_by_user_id' => $host->id, 'kind' => 'payment', 'category' => 'full_payment',
            'amount' => 5000, 'notes' => 'Original booking paid.', 'occurred_at' => now(),
        ]);
        $originalEnd = $booking->end_at->copy();

        $this->actingAs($host)->post(route('bookings.extensions.store', $booking), [
            'duration_unit' => 'day', 'duration_quantity' => 2, 'additional_amount' => '1,500.00',
            'payment_status' => 'collectible', 'notes' => 'Guest requested two more nights.',
        ])->assertRedirect()->assertSessionHas('status');

        $booking->refresh()->load(['extensions', 'financialEntries']);
        $this->assertSame($originalEnd->copy()->addDays(2)->format('Y-m-d H:i'), $booking->end_at->format('Y-m-d H:i'));
        $this->assertSame(3, $booking->rate_quantity);
        $this->assertSame(6500.0, $booking->revenueAmount());
        $this->assertSame(1500.0, $booking->outstandingBalance());
        $this->assertDatabaseHas('booking_extensions', [
            'booking_id' => $booking->id, 'duration_unit' => 'day', 'duration_quantity' => 2,
            'additional_amount' => 1500, 'payment_status' => 'collectible',
        ]);
        $this->assertDatabaseHas('booking_financial_entries', [
            'booking_id' => $booking->id, 'kind' => 'charge', 'category' => 'extension', 'amount' => 1500,
        ]);

        $this->actingAs($host)->post(route('bookings.extensions.store', $booking), [
            'duration_unit' => 'hour', 'duration_quantity' => 3, 'additional_amount' => 300,
            'payment_status' => 'paid',
        ])->assertRedirect()->assertSessionHas('status');

        $booking->refresh()->load(['extensions.createdBy', 'extensions.chargeEntry', 'extensions.paymentEntry', 'financialEntries']);
        $this->assertSame($originalEnd->copy()->addDays(2)->addHours(3)->format('Y-m-d H:i'), $booking->end_at->format('Y-m-d H:i'));
        $this->assertSame(6800.0, $booking->revenueAmount());
        $this->assertSame(1500.0, $booking->outstandingBalance());
        $this->assertNotNull($booking->extensions->firstWhere('payment_status', 'paid')->payment_entry_id);

        $this->actingAs($host)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Booking extensions')
            ->assertSee('2 days extension')
            ->assertSee('3 hours extension')
            ->assertSee('Added to collectibles')
            ->assertSee('Booking extension')
            ->assertSee('₱1,500.00');
    }

    public function test_extension_rejects_a_schedule_that_overlaps_another_booking(): void
    {
        $host = User::factory()->host()->create();
        $condo = $this->unit($host, 'Conflict Residence', 'condo');
        $start = today()->addDays(10)->setTime(14, 0);
        $booking = Booking::create([
            'unit_id' => $condo->id, 'client_id' => $host->id, 'booked_by_user_id' => $host->id,
            'booking_origin' => 'manual', 'start_at' => $start, 'end_at' => $start->copy()->addDay()->setTime(12, 0),
            'status' => 'confirmed', 'total_amount' => 2500, 'party_size' => 1,
        ]);
        Booking::create([
            'unit_id' => $condo->id, 'client_id' => $host->id, 'booked_by_user_id' => $host->id,
            'booking_origin' => 'manual', 'start_at' => $booking->end_at->copy()->addHours(6),
            'end_at' => $booking->end_at->copy()->addDay(), 'status' => 'confirmed', 'total_amount' => 2500, 'party_size' => 1,
        ]);

        $this->actingAs($host)->from(route('bookings.show', $booking))->post(route('bookings.extensions.store', $booking), [
            'duration_unit' => 'day', 'duration_quantity' => 1, 'additional_amount' => 1000,
            'payment_status' => 'collectible',
        ])->assertRedirect(route('bookings.show', $booking))->assertSessionHasErrors('duration_quantity');

        $this->assertSame($start->copy()->addDay()->setTime(12, 0)->format('Y-m-d H:i'), $booking->fresh()->end_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('booking_extensions', 0);
        $this->assertDatabaseCount('booking_financial_entries', 0);
    }

    private function unit(User $host, string $name, string $category): Unit
    {
        return Unit::create([
            'host_id' => $host->id, 'name' => $name, 'kind' => 'unit', 'category' => $category,
            'location' => 'Davao City', 'rules' => 'Return in good condition.', 'capacity' => 4,
            'price' => 2500, 'pricing_unit' => 'day', 'is_active' => true,
            'property_details' => $category === 'condo' ? ['check_in_time' => '14:00', 'check_out_time' => '12:00'] : null,
        ]);
    }
}
