<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCardCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_cards_render_all_photos_with_carousel_controls(): void
    {
        $unit = $this->createListing('Carousel Driving Service');
        $unit->images()->createMany([
            ['path' => 'listings/carousel-one.jpg', 'sort_order' => 0],
            ['path' => 'listings/carousel-two.jpg', 'sort_order' => 1],
            ['path' => 'listings/carousel-three.jpg', 'sort_order' => 2],
        ]);

        $response = $this->get(route('availability.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'category' => 'driving',
        ]));

        $response->assertOk()
            ->assertSee('data-listing-carousel', false)
            ->assertSee('data-listing-carousel-previous', false)
            ->assertSee('data-listing-carousel-next', false)
            ->assertSee('Previous photo of Carousel Driving Service')
            ->assertSee('Next photo of Carousel Driving Service')
            ->assertSee('data-listing-carousel-dot', false)
            ->assertSee('Show photo 2 of 3 for Carousel Driving Service')
            ->assertSee('Photo 1 of 3')
            ->assertSeeInOrder([
                'carousel-one.jpg',
                'carousel-two.jpg',
                'carousel-three.jpg',
            ]);
    }

    public function test_single_photo_listing_card_does_not_render_unnecessary_controls(): void
    {
        $unit = $this->createListing('Single Photo Service');
        $unit->images()->create(['path' => 'listings/only-photo.jpg', 'sort_order' => 0]);

        $this->get(route('availability.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'category' => 'driving',
        ]))
            ->assertOk()
            ->assertSee('data-listing-carousel', false)
            ->assertSee('only-photo.jpg')
            ->assertDontSee('data-listing-carousel-previous', false)
            ->assertDontSee('data-listing-carousel-next', false);
    }

    private function createListing(string $name): Unit
    {
        $host = User::factory()->host()->create();

        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Davao City',
            'capacity' => 4,
            'price' => 850,
            'pricing_unit' => 'session',
            'is_active' => true,
        ]);
    }
}
