<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_record_a_booking_expense_assign_a_provider_and_track_the_providers_earnings(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $provider = User::factory()->host()->create(['name' => 'Davao Cleaning Partner']);
        $condo = $this->unit($host, 'Expense Test Condo', 'condo');
        $cleaningService = $this->unit($provider, 'Turnover Cleaning', 'cleaning', 'service');
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $client->id,
            'booking_origin' => 'platform',
            'start_at' => now()->addDays(3)->setTime(14, 0),
            'end_at' => now()->addDays(4)->setTime(12, 0),
            'status' => 'confirmed',
            'total_amount' => 5000,
            'party_size' => 2,
        ]);

        $this->actingAs($host)->post(route('bookings.expenses.store', $booking), [
            'category' => 'cleaning',
            'amount' => '1,250.00',
            'service_unit_id' => $cleaningService->id,
            'scheduled_at' => now()->addDays(4)->setTime(12, 30)->format('Y-m-d H:i:s'),
            'notes' => 'Turnover cleaning after checkout.',
        ])->assertRedirect()->assertSessionHas('status');

        $expense = BookingExpense::query()->sole();
        $this->assertSame($provider->id, $expense->provider_user_id);
        $this->assertSame($cleaningService->id, $expense->service_unit_id);
        $this->assertSame('assigned', $expense->status);
        $this->assertSame('1250.00', $expense->amount);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $provider->id,
            'type' => 'service_work_assigned',
        ]);

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking expenses & assigned services', false)
            ->assertSee('Turnover Cleaning')
            ->assertSee('Net ₱3,750.00');
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Private operating costs')
            ->assertDontSee('Turnover Cleaning');

        $this->actingAs($provider)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Service work & earnings', false)
            ->assertSee('Expense Test Condo')
            ->assertSee('₱1,250.00');
        $this->actingAs($provider)->patch(route('service-work.complete', $expense))
            ->assertRedirect();
        $this->assertSame('completed', $expense->fresh()->status);

        $this->actingAs($host)->patch(route('bookings.expenses.status', [$booking, $expense]), [
            'status' => 'paid',
        ])->assertRedirect();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertNotNull($expense->fresh()->paid_at);
        $this->actingAs($provider)->get(route('service-work.index'))
            ->assertOk()
            ->assertSee('Paid earnings')
            ->assertSee('₱1,250.00');
    }

    public function test_only_the_booking_host_or_admin_can_record_expenses(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $client = User::factory()->create();
        $condo = $this->unit($host, 'Private Expense Condo', 'condo');
        $booking = Booking::create([
            'unit_id' => $condo->id,
            'client_id' => $client->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(3),
            'status' => 'confirmed',
            'total_amount' => 3500,
            'party_size' => 1,
        ]);

        $this->actingAs($otherHost)->post(route('bookings.expenses.store', $booking), [
            'category' => 'laundry',
            'amount' => 500,
        ])->assertForbidden();
        $this->assertDatabaseCount('booking_expenses', 0);
    }

    private function unit(User $host, string $name, string $category, string $kind = 'unit'): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => $kind,
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Follow the service instructions.',
            'capacity' => 6,
            'price' => 1500,
            'pricing_unit' => 'session',
            'is_active' => true,
        ]);
    }
}
