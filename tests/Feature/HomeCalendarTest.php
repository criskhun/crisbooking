<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_provides_the_installable_android_app_download(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Download Android app')
            ->assertSee(asset('downloads/DavaoRentZone-Android-v1.0.4.apk'))
            ->assertSee('download="DavaoRentZone-Android-v1.0.4.apk"', false);

        $this->assertFileExists(public_path('downloads/DavaoRentZone-Android-v1.0.4.apk'));
    }

    public function test_home_shows_the_highest_rated_available_listing_per_category_with_a_review_preview(): void
    {
        $host = User::factory()->host()->create();
        $reviewer = User::factory()->create();
        $start = now()->addDays(8)->startOfDay();
        $topCar = $this->unit($host, 'Top Rated Car', ['category' => 'car', 'kind' => 'unit']);
        $lowerCar = $this->unit($host, 'Lower Rated Car', ['category' => 'car', 'kind' => 'unit']);
        $condo = $this->unit($host, 'Available Condo', ['category' => 'condo', 'kind' => 'unit']);

        $this->review($topCar, $reviewer, 5, 'Excellent car and a very smooth rental experience.');
        $this->review($lowerCar, $reviewer, 3, 'The car was acceptable and the host was responsive.');

        $response = $this->get(route('home', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('Top available by category')
            ->assertSee('Top Rated Car')
            ->assertSee('Available Condo')
            ->assertDontSee('Lower Rated Car')
            ->assertSee('fa-star fa-rating', false)
            ->assertSee('5.0')
            ->assertSee('Excellent car and a very smooth rental experience.')
            ->assertSee('See all 3 available');
    }

    public function test_category_and_date_range_filters_only_show_matching_available_listings(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $start = now()->addDays(12)->startOfDay();
        $end = $start->copy()->addDays(2);
        $availableDriver = $this->unit($host, 'Available Professional Driver', ['category' => 'driving']);
        $busyDriver = $this->unit($host, 'Busy Professional Driver', ['category' => 'driving']);
        $car = $this->unit($host, 'Available Car', ['category' => 'car', 'kind' => 'unit']);

        $this->booking($busyDriver, $client, $start->copy()->addDay()->addHours(9), 'confirmed');

        $response = $this->get(route('home', [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'category' => 'driving',
        ]));

        $response->assertOk()
            ->assertSee('class="active"', false)
            ->assertSee('Available Professional Driver')
            ->assertDontSee('Busy Professional Driver')
            ->assertDontSee('Available Car')
            ->assertSee('value="'.$start->toDateString().'"', false)
            ->assertSee('value="'.$end->toDateString().'"', false)
            ->assertSee('in-range', false);

        $this->assertTrue($availableDriver->exists);
        $this->assertTrue($car->exists);
    }

    public function test_see_all_available_opens_a_filtered_results_page_with_every_matching_listing(): void
    {
        $host = User::factory()->host()->create();
        $date = now()->addDays(3)->startOfDay();
        $this->unit($host, 'Alpha Car', ['category' => 'car', 'kind' => 'unit']);
        $this->unit($host, 'Beta Car', ['category' => 'car', 'kind' => 'unit']);

        $filters = [
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'category' => 'car',
        ];

        $this->get(route('home', $filters))->assertOk()
            ->assertSee('Alpha Car')
            ->assertDontSee('Beta Car')
            ->assertSee('See all 2 available')
            ->assertSee(route('availability.index', $filters));

        $this->get(route('availability.index', $filters))->assertOk()
            ->assertSee('Change your dates or category')
            ->assertSee('2 listings found')
            ->assertSee('Alpha Car')
            ->assertSee('Beta Car')
            ->assertSee('Highest rated first')
            ->assertSee('availability-sticky-filter', false)
            ->assertSee('value="car" selected', false);
    }

    public function test_available_results_can_filter_country_or_city_from_one_location_box(): void
    {
        $davaoHost = User::factory()->host()->create(['city' => 'Davao City', 'country' => 'Philippines']);
        $tokyoHost = User::factory()->host()->create(['city' => 'Tokyo', 'country' => 'Japan']);
        $date = now()->addDays(5)->startOfDay();
        $this->unit($davaoHost, 'Davao City Car', ['category' => 'car', 'kind' => 'unit', 'location' => null]);
        $this->unit($tokyoHost, 'Tokyo City Car', ['category' => 'car', 'kind' => 'unit', 'location' => null]);
        $filters = [
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'category' => 'car',
        ];

        $this->get(route('availability.index', array_merge($filters, ['location' => 'Davao'])))
            ->assertOk()
            ->assertSee('Country or city')
            ->assertSee('name="location"', false)
            ->assertSee('value="Davao"', false)
            ->assertSee('Davao City Car')
            ->assertDontSee('Tokyo City Car');

        $this->get(route('availability.index', array_merge($filters, ['location' => 'Japan'])))
            ->assertOk()
            ->assertSee('Tokyo City Car')
            ->assertDontSee('Davao City Car');
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

    private function review(Unit $unit, User $reviewer, int $rating, string $comment): Review
    {
        $booking = $this->booking($unit, $reviewer, now()->subMonth()->startOfDay()->addHours(9), 'confirmed');

        return Review::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $unit->host_id,
            'reviewee_context' => 'host',
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }
}
