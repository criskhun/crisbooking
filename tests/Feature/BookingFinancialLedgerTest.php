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
            'total_amount' => 5000,
            'payment_option' => 'downpayment',
            'initial_payment_amount' => 2000,
            'security_deposit_amount' => 1500,
            'security_deposit_collected' => 1,
            'party_size' => 1,
        ])->assertRedirect();

        $booking = Booking::with('financialEntries')->sole();
        $this->assertSame(3000.0, $booking->outstandingBalance());
        $this->assertSame(1500.0, $booking->securityDepositHeld());
        $this->assertSame('Partially paid', $booking->paymentStatusLabel());
        $this->assertDatabaseHas('booking_financial_entries', ['booking_id' => $booking->id, 'kind' => 'payment', 'category' => 'downpayment', 'amount' => 2000]);
        $this->assertDatabaseHas('booking_financial_entries', ['booking_id' => $booking->id, 'kind' => 'deposit', 'amount' => 1500]);

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
            'kind' => 'payment', 'category' => 'balance_payment', 'amount' => 3500,
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
