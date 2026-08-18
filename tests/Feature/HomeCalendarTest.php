<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_navigates_dates_and_prioritizes_listings_booked_on_selected_date(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $selectedDate = now()->addMonth()->startOfMonth()->addDays(8)->startOfDay();
        $selectedListing = $this->unit($host, 'Selected Date Cleaning');
        $popularListing = $this->unit($host, 'Historically Popular Driving');

        $this->booking($selectedListing, $client, $selectedDate->copy()->addHours(9), 'pre_approved');
        foreach (range(1, 3) as $offset) {
            $this->booking($popularListing, $client, $selectedDate->copy()->subMonths($offset)->addHours(9), 'confirmed');
        }

        $response = $this->get(route('home', [
            'month' => $selectedDate->format('Y-m'),
            'date' => $selectedDate->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertSee($selectedDate->format('F Y'))
            ->assertSee('aria-current="date"', false)
            ->assertSee('booked this date')
            ->assertSeeInOrder(['Selected Date Cleaning', 'Historically Popular Driving'])
            ->assertSee(route('home', [
                'month' => $selectedDate->copy()->addMonth()->format('Y-m'),
                'date' => $selectedDate->copy()->addMonth()->startOfMonth()->toDateString(),
            ]));
    }

    public function test_public_calendar_hides_inactive_or_unapproved_listings(): void
    {
        $approvedHost = User::factory()->host()->create();
        $incompleteHost = User::factory()->host()->incompleteProfile()->create();
        $this->unit($approvedHost, 'Approved Public Listing');
        $this->unit($incompleteHost, 'Unapproved Private Listing');
        $this->unit($approvedHost, 'Disabled Listing', ['is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Approved Public Listing')
            ->assertDontSee('Unapproved Private Listing')
            ->assertDontSee('Disabled Listing');
    }

    private function unit(User $host, string $name, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'service',
            'category' => 'cleaning',
            'location' => 'Davao City',
            'rules' => 'Follow the host instructions.',
            'capacity' => 4,
            'price' => 1000,
            'pricing_unit' => 'session',
            'is_active' => true,
        ], $attributes));
    }

    private function booking(Unit $unit, User $client, mixed $start, string $status): Booking
    {
        return Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(3),
            'status' => $status,
            'total_amount' => 1500,
            'party_size' => 1,
        ]);
    }
}
