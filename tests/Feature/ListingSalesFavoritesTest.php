<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Review;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingSalesFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_add_and_remove_an_active_listing_from_favorites(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host);

        $this->actingAs($client)->postJson(route('listings.favorite', $unit))
            ->assertOk()
            ->assertJson(['favorited' => true]);
        $this->assertDatabaseHas('favorite_units', ['user_id' => $client->id, 'unit_id' => $unit->id]);

        $this->actingAs($client)->postJson(route('listings.favorite', $unit))
            ->assertOk()
            ->assertJson(['favorited' => false]);
        $this->assertDatabaseMissing('favorite_units', ['user_id' => $client->id, 'unit_id' => $unit->id]);
    }

    public function test_guest_heart_returns_to_the_listing_and_saves_it_after_login(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create(['email' => 'favorite-client@example.test']);
        $unit = $this->unit($host);
        $afterLoginUrl = route('listings.favorite.after-login', $unit);

        $this->get($afterLoginUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $afterLoginUrl);

        $this->post(route('login'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect($afterLoginUrl);

        $this->get($afterLoginUrl)
            ->assertRedirect(route('listings.show', $unit))
            ->assertSessionHas('status', 'Listing saved to your favorites.');
        $this->assertDatabaseHas('favorite_units', ['user_id' => $client->id, 'unit_id' => $unit->id]);
    }

    public function test_guest_inquiry_returns_to_the_same_listing_after_login(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create(['email' => 'inquiry-client@example.test']);
        $unit = $this->unit($host);
        $inquiryUrl = route('listings.inquire', $unit);

        $this->get($inquiryUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $inquiryUrl);

        $this->post(route('login'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect($inquiryUrl);

        $this->get($inquiryUrl)->assertRedirect(route('listings.show', $unit).'#listing-inquiry');
    }

    public function test_client_has_a_favorites_page_for_saved_active_listings(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $saved = $this->unit($host, ['name' => 'Saved Davao Listing', 'sale_percentage' => 10]);
        $inactive = $this->unit($host, ['name' => 'Unavailable Saved Listing', 'is_active' => false]);
        $client->favoriteUnits()->attach([$saved->id, $inactive->id]);

        $this->actingAs($client)->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('Favorites')
            ->assertSee('1 favorite listing')
            ->assertSee('Saved Davao Listing')
            ->assertSee('10% off')
            ->assertSee('aria-pressed="true"', false)
            ->assertDontSee('Unavailable Saved Listing');
    }

    public function test_host_can_set_a_sale_percentage_but_not_more_than_ninety_percent(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, ['photo_path' => 'listings/existing.jpg']);
        $payload = [
            'name' => $unit->name,
            'kind' => 'service',
            'category' => 'cleaning',
            'location' => 'Davao City',
            'rules' => 'Follow the host instructions.',
            'price' => 1000,
            'pricing_unit' => 'session',
            'sale_percentage' => 15,
            'is_active' => 1,
        ];

        $this->actingAs($host)->put(route('units.update', $unit), $payload)->assertRedirect(route('units.index'));
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'sale_percentage' => 15]);

        $this->actingAs($host)->from(route('units.edit', $unit))->put(route('units.update', $unit), [
            ...$payload,
            'sale_percentage' => 91,
        ])->assertRedirect(route('units.edit', $unit))->assertSessionHasErrors('sale_percentage');
    }

    public function test_available_listing_card_shows_favorite_rating_and_discounted_price(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, ['sale_percentage' => 20, 'latitude' => 7.0731, 'longitude' => 125.6128]);
        $client->favoriteUnits()->attach($unit);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => now()->subMonth(),
            'end_at' => now()->subMonth()->addHour(),
            'status' => 'confirmed',
            'total_amount' => 800,
            'party_size' => 1,
        ]);
        Review::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $client->id,
            'reviewee_id' => $host->id,
            'reviewee_context' => 'host',
            'rating' => 5,
            'comment' => 'Excellent service and a very easy booking.',
        ]);

        $date = now()->addDays(3)->toDateString();
        $this->actingAs($client)->get(route('availability.index', [
            'start_date' => $date,
            'end_date' => $date,
            'category' => 'other',
        ]))->assertOk()
            ->assertSee('20% off')
            ->assertSee('★ 5.0')
            ->assertSee('₱1,000.00')
            ->assertSee('₱800.00')
            ->assertSee('data-listing-view-select="grid"', false)
            ->assertSee('data-listing-view-select="map"', false)
            ->assertSee('data-default-radius-km="100"', false)
            ->assertSee('data-nearby-radius-km="50"', false)
            ->assertSee('Compare hosts by lowest price')
            ->assertSee('"starting_price":800', false)
            ->assertSee('"original_price":1000', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Excellent service and a very easy booking.');
    }

    public function test_sale_is_applied_to_booking_price_without_changing_an_agreed_price(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host, ['sale_percentage' => 20]);
        $start = now()->addDays(4)->startOfHour();
        $inquiry = $this->inquiry($unit, $client, $start);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addHour()->toDateTimeString(),
            'party_size' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('bookings', ['inquiry_id' => $inquiry->id, 'total_amount' => 800]);

        $secondClient = User::factory()->create();
        $secondInquiry = $this->inquiry($unit, $secondClient, $start->copy()->addDay(), 700);
        $this->actingAs($secondClient)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $secondInquiry->id,
            'start_at' => $start->copy()->addDay()->toDateTimeString(),
            'end_at' => $start->copy()->addDay()->addHour()->toDateTimeString(),
            'party_size' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('bookings', ['inquiry_id' => $secondInquiry->id, 'total_amount' => 700]);
    }

    private function unit(User $host, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'host_id' => $host->id,
            'name' => 'Premium Cleaning Service',
            'kind' => 'service',
            'category' => 'cleaning',
            'location' => 'Davao City',
            'rules' => 'Follow the host instructions.',
            'capacity' => 4,
            'price' => 1000,
            'pricing_unit' => 'hour',
            'is_active' => true,
        ], $attributes));
    }

    private function inquiry(Unit $unit, User $client, mixed $start, ?float $agreedPrice = null): Inquiry
    {
        return Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $unit->host_id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHour(),
            'party_size' => 1,
            'status' => 'open',
            'agreed_price' => $agreedPrice,
        ]);
    }
}
