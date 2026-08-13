<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_calendar_uses_category_markers_and_exposes_a_quick_view_for_each_booking(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $start = now()->addMonth()->startOfMonth()->addDays(4)->setTime(9, 0);
        $condo = $this->createUnit($host, 'Harbor Condo', 'condo', 'unit');
        $car = $this->createUnit($host, 'Airport Car', 'car', 'unit');
        $condoBooking = $this->createBooking($condo, $client, $start, 'Please prepare one parking slot.');
        $carBooking = $this->createBooking($car, $client, $start->copy()->addDays(2), 'Airport pickup requested.');

        $response = $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertSee('Calendar category colors')
            ->assertSee('🏠')
            ->assertSee('🚗')
            ->assertSee('category-condo', false)
            ->assertSee('category-car', false)
            ->assertSee('data-calendar-booking-open', false)
            ->assertSee('data-booking-id="'.$condoBooking->id.'"', false)
            ->assertSee('data-booking-id="'.$carBooking->id.'"', false)
            ->assertSee('data-notes="Please prepare one parking slot."', false)
            ->assertSee('data-booking-url="'.route('bookings.show', $condoBooking).'"', false)
            ->assertSee('Open full booking');
    }

    public function test_host_can_filter_calendar_by_category_or_listing_without_seeing_another_hosts_schedule(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $client = User::factory()->create();
        $start = now()->addMonth()->startOfMonth()->addDays(7)->setTime(8, 0);
        $condo = $this->createUnit($host, 'Garden Condo', 'condo', 'unit');
        $service = $this->createUnit($host, 'Deep Cleaning Team', 'cleaning', 'service');
        $otherUnit = $this->createUnit($otherHost, 'Other Host Condo', 'condo', 'unit');
        $condoBooking = $this->createBooking($condo, $client, $start, 'Own condo booking.');
        $serviceBooking = $this->createBooking($service, $client, $start->copy()->addDay(), 'Own service booking.');
        $otherBooking = $this->createBooking($otherUnit, $client, $start->copy()->addDays(2), 'Private booking from another host.');

        $categoryResponse = $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'schedule_category' => 'condo',
        ]));

        $categoryResponse->assertOk()
            ->assertSee('data-booking-id="'.$condoBooking->id.'"', false)
            ->assertDontSee('data-booking-id="'.$serviceBooking->id.'"', false)
            ->assertDontSee('data-booking-id="'.$otherBooking->id.'"', false)
            ->assertDontSee('Other Host Condo');

        $unitResponse = $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
            'schedule_unit' => $service->id,
        ]));

        $unitResponse->assertOk()
            ->assertSee('data-booking-id="'.$serviceBooking->id.'"', false)
            ->assertDontSee('data-booking-id="'.$condoBooking->id.'"', false)
            ->assertDontSee('data-booking-id="'.$otherBooking->id.'"', false);
    }

    public function test_every_service_category_and_custom_other_service_appears_as_a_calendar_booking(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $start = now()->addMonth()->startOfMonth()->addDays(10)->setTime(9, 0);
        $serviceCategories = [
            'cleaning' => 'Home Cleaning',
            'driving' => 'Professional Driver',
            'massage' => 'Home Massage',
            'consultancy' => 'Business Consultancy',
            'event_planning' => 'Custom Event Planning',
        ];
        $bookings = collect();

        foreach ($serviceCategories as $index => $name) {
            $unit = $this->createUnit($host, $name, $index, 'service');
            $bookings->push($this->createBooking($unit, $client, $start->copy()->addDays($bookings->count()), $name.' booking.'));
        }

        $response = $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'month' => $start->format('Y-m'),
        ]));

        $response->assertOk()
            ->assertSee('category-cleaning', false)
            ->assertSee('category-driving', false)
            ->assertSee('category-massage', false)
            ->assertSee('category-consultancy', false)
            ->assertSee('category-other', false);

        foreach ($bookings as $booking) {
            $response->assertSee('data-booking-id="'.$booking->id.'"', false);
        }
    }

    private function createUnit(User $host, string $name, string $category, string $kind): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => $kind,
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Respect the listing rules.',
            'capacity' => 4,
            'price' => 1500,
            'pricing_unit' => $kind === 'service' ? 'session' : 'day',
            'is_active' => true,
        ]);
    }

    private function createBooking(Unit $unit, User $client, mixed $start, string $notes): Booking
    {
        return Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(4),
            'status' => 'pending',
            'total_amount' => 2400,
            'party_size' => 2,
            'notes' => $notes,
        ]);
    }
}
