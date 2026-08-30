<?php

namespace Tests\Feature;

use App\Models\AffiliatePartnership;
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

    public function test_host_can_switch_to_a_listing_timeline_with_one_row_per_listing(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create(['name' => 'Timeline Client']);
        $start = now()->addMonth()->startOfMonth()->addDays(3)->setTime(14, 0);
        $condo = $this->createUnit($host, 'Riverside Condo', 'condo', 'unit');
        $car = $this->createUnit($host, 'City Touring Car', 'car', 'unit');
        $booking = $this->createBooking($condo, $client, $start, 'Timeline booking details.');
        $booking->update(['end_at' => $start->copy()->addDays(3)->setTime(10, 0), 'status' => 'confirmed']);

        $response = $this->actingAs($host)->get(route('calendar.index', [
            'mode' => 'manage',
            'calendar_view' => 'listings',
            'month' => $start->format('Y-m'),
        ]));

        $response->assertOk()
            ->assertSee('Listing timeline')
            ->assertSee('Compare every owned listing')
            ->assertSee('Riverside Condo')
            ->assertSee('City Touring Car')
            ->assertSee('Timeline Client')
            ->assertSee('data-booking-id="'.$booking->id.'"', false)
            ->assertSee('grid-column: 5 / 9', false)
            ->assertSee('data-calendar-booking-open', false)
            ->assertSee(route('bookings.show', $booking), false);
    }

    public function test_accepted_affiliate_sees_only_assigned_listing_availability_without_private_booking_details(): void
    {
        $host = User::factory()->host()->create();
        $affiliateUser = User::factory()->create();
        $client = User::factory()->create(['name' => 'Private Booking Customer']);
        $start = now()->addMonth()->startOfMonth()->addDays(6)->setTime(9, 0);
        $assigned = $this->createUnit($host, 'Affiliate Assigned Condo', 'condo', 'unit');
        $unassigned = $this->createUnit($host, 'Host Private Car', 'car', 'unit');
        $partnership = AffiliatePartnership::create([
            'marketer_id' => $affiliateUser->id,
            'host_id' => $host->id,
            'status' => 'accepted',
            'commission_percentage' => 10,
            'referral_code' => 'AFFILIATECALENDAR',
            'application_message' => 'I will market these listings to qualified local renters.',
            'reviewed_at' => now(),
        ]);
        $partnership->units()->attach($assigned);
        $booking = $this->createBooking($assigned, $client, $start, 'Private notes must stay hidden.');

        $response = $this->actingAs($affiliateUser)->get(route('calendar.index', [
            'mode' => 'manage',
            'calendar_view' => 'listings',
            'month' => $start->format('Y-m'),
        ]));

        $response->assertOk()
            ->assertSee('Affiliate calendar')
            ->assertSee('Compare every assigned listing')
            ->assertSee('Affiliate Assigned Condo')
            ->assertDontSee('Host Private Car')
            ->assertSee('Reserved')
            ->assertSee('data-booking-id="'.$booking->id.'"', false)
            ->assertDontSee('Private Booking Customer')
            ->assertDontSee('Private notes must stay hidden.')
            ->assertDontSee('data-calendar-booking-open', false)
            ->assertDontSee(route('bookings.show', $booking), false);

        $this->actingAs($affiliateUser)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Book what you need')
            ->assertDontSee('Listing timeline');
    }

    public function test_same_category_listings_have_profiles_and_distinct_category_family_shades(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $first = $this->createUnit($host, 'Blue Condo One', 'condo', 'unit');
        $second = $this->createUnit($host, 'Blue Condo Two', 'condo', 'unit');
        $first->images()->create(['path' => 'units/blue-condo-one.jpg', 'sort_order' => 0]);
        $start = now()->addMonth()->startOfMonth()->addDays(4)->setTime(14, 0);
        $this->createBooking($first, $client, $start, 'First unit.');
        $this->createBooking($second, $client, $start->copy()->addDays(2), 'Second unit.');

        $response = $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage', 'month' => $start->format('Y-m')]));
        $response->assertOk()
            ->assertSee('Individual listing colors')
            ->assertSee('Blue Condo One')
            ->assertSee('Blue Condo Two')
            ->assertSee('units/blue-condo-one.jpg')
            ->assertSee('unique listing shade');

        preg_match_all('/--unit-accent:\s*(hsl\([^;]+\))/', $response->getContent(), $matches);
        $this->assertGreaterThanOrEqual(2, count(array_unique($matches[1])));
    }

    public function test_host_can_assign_a_solid_or_gradient_calendar_color_to_a_listing(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, 'Custom Color Cleaning', 'cleaning', 'service');
        $unit->update(['photo_path' => 'listings/custom-color.jpg']);

        $this->actingAs($host)->put(route('units.update', $unit), [
            'name' => $unit->name,
            'kind' => 'service',
            'category' => 'cleaning',
            'location' => 'Davao City',
            'rules' => 'Respect the listing rules.',
            'price' => 1500,
            'pricing_unit' => 'session',
            'calendar_color_enabled' => 1,
            'calendar_color' => '#1A2B3C',
            'calendar_use_gradient' => 1,
            'calendar_secondary_color' => '#D4E5F6',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $unit->refresh();
        $this->assertSame('#1a2b3c', $unit->calendar_color);
        $this->assertSame('#d4e5f6', $unit->calendar_secondary_color);
        $this->assertTrue($unit->calendar_use_gradient);
        $bookingStart = now()->startOfMonth()->addDays(2)->setTime(9, 0);
        $booking = $this->createBooking($unit, $client, $bookingStart, 'Custom color calendar booking.');

        $this->actingAs($host)->get(route('units.edit', $unit))
            ->assertOk()
            ->assertSee('name="calendar_color" type="color" value="#1a2b3c"', false)
            ->assertSee('name="calendar_secondary_color" type="color" value="#d4e5f6"', false);

        $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage', 'month' => $bookingStart->format('Y-m')]))
            ->assertOk()
            ->assertSee('class="category-cleaning"', false)
            ->assertSee('data-booking-id="'.$booking->id.'"', false)
            ->assertSee('--unit-fill: linear-gradient(135deg, #1a2b3c, #d4e5f6)', false);

        $calendarCss = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.calendar-booking-span.calendar-booking-span[style*="--unit-fill"]', $calendarCss);
        $this->assertStringContainsString('border-left:4px solid var(--calendar-event-border,var(--unit-accent))', $calendarCss);
        $this->assertStringContainsString('transform: scale(1.035)', $calendarCss);
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
