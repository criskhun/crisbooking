<?php

namespace Tests\Feature;

use App\Models\HostApplication;
use App\Models\ProfileImage;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_reuse_and_delete_saved_profile_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('profile-images.store'), [
            'profile_image' => UploadedFile::fake()->image('first-profile.jpg', 600, 600),
        ])->assertRedirect();
        $first = ProfileImage::firstOrFail();
        $this->assertSame($first->path, $user->fresh()->profile_image_path);
        Storage::disk('public')->assertExists($first->path);

        $this->actingAs($user)->post(route('profile-images.store'), [
            'profile_image' => UploadedFile::fake()->image('second-profile.png', 600, 600),
        ])->assertRedirect();
        $second = ProfileImage::query()->latest('id')->firstOrFail();
        $this->assertDatabaseCount('profile_images', 2);
        $this->assertSame($second->path, $user->fresh()->profile_image_path);

        $this->actingAs($other)->patch(route('profile-images.select', $first))->assertForbidden();
        $this->actingAs($user)->patch(route('profile-images.select', $first))->assertRedirect();
        $this->assertSame($first->path, $user->fresh()->profile_image_path);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($first->path));

        $this->actingAs($user)->delete(route('profile-images.destroy', $first))->assertRedirect();
        $this->assertDatabaseMissing('profile_images', ['id' => $first->id]);
        $this->assertSame($second->path, $user->fresh()->profile_image_path);
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_global_search_finds_capacity_bedrooms_host_and_business_names(): void
    {
        $host = User::factory()->host()->create(['name' => 'Cris Rental Host']);
        $client = User::factory()->create();
        $this->approvedBusiness($host, 'Davao Wheels and Stays');
        $car = $this->unit($host, [
            'name' => 'Airport Family SUV',
            'category' => 'car',
            'capacity' => 5,
            'car_details' => ['make' => 'Toyota', 'model' => 'Fortuner', 'accessories' => ['gps', 'child_seat']],
        ]);
        $car->rates()->create(['coverage' => 'within_city', 'period' => 'day', 'price' => 3500]);
        $condo = $this->unit($host, [
            'name' => 'Downtown Family Residence',
            'category' => 'condo',
            'capacity' => 4,
            'property_details' => ['type' => 'condo', 'bedrooms' => 2, 'bathrooms' => 1],
        ]);
        $condo->rates()->create(['period' => 'day', 'price' => 2800]);

        $this->actingAs($client)->getJson(route('listing-search.index', ['q' => '5 seater car']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Airport Family SUV')
            ->assertJsonPath('results.0.business_name', 'Davao Wheels and Stays')
            ->assertJsonPath('results.0.url', route('listings.show', $car));

        $this->actingAs($client)->getJson(route('listing-search.index', ['q' => '2 br condo']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Downtown Family Residence');

        $this->actingAs($client)->getJson(route('listing-search.index', ['q' => 'Davao Wheels']))
            ->assertOk()
            ->assertJsonCount(2, 'results');
    }

    public function test_book_now_map_and_host_storefront_expose_rich_clickable_listing_details(): void
    {
        $host = User::factory()->host()->create([
            'name' => 'Maria Host',
            'bio' => 'Local rental host with carefully maintained vehicles and stays.',
            'profile_image_path' => 'profiles/maria-host.jpg',
        ]);
        $client = User::factory()->create();
        $this->approvedBusiness($host, 'Maria Davao Rentals');
        $active = $this->unit($host, [
            'name' => 'Five Seat City Car',
            'category' => 'car',
            'capacity' => 5,
            'sale_percentage' => 25,
            'latitude' => 7.0731,
            'longitude' => 125.6128,
            'photo_path' => 'listings/five-seat-city-car.jpg',
        ]);
        $active->rates()->create(['coverage' => 'within_city', 'period' => 'day', 'price' => 3200]);
        $inactive = $this->unit($host, ['name' => 'Hidden Old Listing', 'is_active' => false]);

        $this->actingAs($client)->get(route('calendar.index', ['mode' => 'book']))
            ->assertOk()
            ->assertSee('Browse listings by lowest price')
            ->assertSee('data-listing-view-select="grid"', false)
            ->assertSee('data-listing-view-select="map"', false)
            ->assertSee('data-listing-map-panel', false)
            ->assertSee('Maria Davao Rentals')
            ->assertSee('"starting_price":2400', false)
            ->assertSee('"original_price":3200', false)
            ->assertSee('"url":'.json_encode(route('listings.show', $active)), false)
            ->assertSee('"inquiry_url":'.json_encode(route('listings.inquire', $active)), false)
            ->assertSee('"marker_image_url":'.json_encode(Storage::disk('public')->url('profiles/maria-host.jpg')), false)
            ->assertSee('"navigation_url":"https:\/\/www.google.com\/maps\/dir\/?api=1\u0026destination=7.0731000,125.6128000"', false)
            ->assertSee('"host_url":'.json_encode(route('hosts.show', $host)), false)
            ->assertSee('class="catalog-photo"', false)
            ->assertSee(Storage::disk('public')->url('listings/five-seat-city-car.jpg'))
            ->assertSee('View details')
            ->assertSee('Inquire now')
            ->assertSee('Navigate ↗')
            ->assertSee('data-page-guide', false)
            ->assertSee('data-global-listing-search', false);

        $this->get(route('hosts.show', $host))
            ->assertOk()
            ->assertSee('Maria Davao Rentals')
            ->assertSee('Five Seat City Car')
            ->assertSee('View & book', false)
            ->assertDontSee($inactive->name);
    }

    private function unit(User $host, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'host_id' => $host->id,
            'name' => 'Bookable Listing',
            'kind' => 'unit',
            'category' => 'car',
            'location' => 'Davao City',
            'description' => 'A reliable rental available from this host.',
            'rules' => 'Follow the host instructions.',
            'capacity' => 4,
            'price' => 1000,
            'pricing_unit' => 'day',
            'is_active' => true,
        ], $attributes));
    }

    private function approvedBusiness(User $host, string $businessName): HostApplication
    {
        return HostApplication::create([
            'user_id' => $host->id,
            'status' => HostApplication::STATUS_APPROVED,
            'account_type' => 'business',
            'business_name' => $businessName,
            'business_registration_number' => 'REG-12345',
            'hosting_experience' => 'experienced',
            'motivation' => 'Provide dependable rentals for local clients.',
            'payout_method' => 'bank',
            'payout_provider' => 'Test Bank',
            'payout_account_name' => $host->name,
            'payout_account_number' => '1234567890',
            'authority_confirmed_at' => now(),
            'safety_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_consented_at' => now(),
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
    }
}
