<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\UnitDraft;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_autosave_reopen_and_delete_an_encrypted_listing_draft(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();

        $this->actingAs($host)->postJson(route('unit-drafts.store'), [
            'kind' => 'unit',
            'category' => 'car',
            'pricing_unit' => 'day',
            'offered_rates' => ['12_hours', 'day', 'week', 'month'],
            'car' => ['transmission' => 'automatic', 'fuel_type' => 'gasoline'],
            'custom_accessories' => [''],
            'car_charges' => [
                'car_wash' => ['enabled' => '0', 'amount' => ''],
                'delivery' => ['enabled' => '0', 'amount' => ''],
                'deposit' => ['enabled' => '0', 'amount' => ''],
            ],
            'is_active' => '1',
        ])->assertOk()->assertJson(['id' => null, 'empty' => true]);
        $this->assertDatabaseCount('unit_drafts', 0);

        $response = $this->actingAs($host)->postJson(route('unit-drafts.store'), [
            'name' => 'Draft Family Van',
            'kind' => 'unit',
            'category' => 'car',
            'location' => 'Davao City',
            'latitude' => '7.0731000',
            'longitude' => '125.6128000',
            'car' => ['make' => 'Toyota', 'color' => 'Pearl White'],
            'gps' => ['password' => 'private-draft-secret'],
            'custom_accessories' => ['Portable tire inflator'],
        ])->assertOk();

        $draft = UnitDraft::findOrFail($response->json('id'));
        $this->assertSame('Draft Family Van', $draft->title);
        $this->assertSame('Pearl White', $draft->payload['car']['color']);
        $this->assertStringNotContainsString('private-draft-secret', DB::table('unit_drafts')->where('id', $draft->id)->value('payload'));

        $this->actingAs($host)->get(route('units.create', ['draft' => $draft]))
            ->assertOk()
            ->assertSee('value="Draft Family Van"', false)
            ->assertSee('value="Pearl White"', false)
            ->assertSee('value="Portable tire inflator"', false)
            ->assertSee('7.07310, 125.61280')
            ->assertSee('Delete');

        $this->actingAs($otherHost)->delete(route('unit-drafts.destroy', $draft))->assertForbidden();
        $this->actingAs($host)->deleteJson(route('unit-drafts.destroy', $draft))->assertOk()->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('unit_drafts', ['id' => $draft->id]);

        $redirectDraft = UnitDraft::create([
            'host_id' => $host->id,
            'title' => 'Delete from draft list',
            'payload' => ['name' => 'Delete from draft list'],
        ]);
        $this->actingAs($host)->delete(route('unit-drafts.destroy', $redirectDraft))->assertRedirect(route('units.create'));
        $this->assertDatabaseMissing('unit_drafts', ['id' => $redirectDraft->id]);
    }

    public function test_legacy_plain_json_draft_is_recovered_and_encrypted_when_opened(): void
    {
        $host = User::factory()->host()->create();
        $legacyPayload = [
            'name' => 'Legacy Draft Van',
            'kind' => 'unit',
            'category' => 'car',
            'car' => ['make' => 'Toyota', 'color' => 'Silver'],
        ];
        $draftId = DB::table('unit_drafts')->insertGetId([
            'host_id' => $host->id,
            'title' => 'Legacy Draft Van',
            'payload' => json_encode($legacyPayload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($host)->get(route('units.create', ['draft' => $draftId]))
            ->assertOk()
            ->assertSee('value="Legacy Draft Van"', false)
            ->assertSee('value="Silver"', false);

        $rawPayload = DB::table('unit_drafts')->where('id', $draftId)->value('payload');
        $this->assertNotSame(json_encode($legacyPayload), $rawPayload);
        $this->assertSame('Legacy Draft Van', UnitDraft::findOrFail($draftId)->payload['name']);
    }

    public function test_draft_encrypted_with_an_unavailable_key_shows_a_recoverable_error_instead_of_500(): void
    {
        $host = User::factory()->host()->create();
        $draftId = DB::table('unit_drafts')->insertGetId([
            'host_id' => $host->id,
            'title' => 'Old encrypted draft',
            'payload' => 'payload-that-cannot-be-decrypted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($host)->followingRedirects()->get(route('units.create', ['draft' => $draftId]))
            ->assertOk()
            ->assertSee('does not match the current application key')
            ->assertSee('Old encrypted draft');

        $this->actingAs($host)->delete(route('unit-drafts.destroy', $draftId))
            ->assertRedirect(route('units.create'));
        $this->assertDatabaseMissing('unit_drafts', ['id' => $draftId]);
    }

    public function test_draft_images_and_primary_selection_are_restored_and_used_by_the_listing(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $firstPhoto = UploadedFile::fake()->image('draft-front.jpg', 900, 600);
        $secondPhoto = UploadedFile::fake()->image('draft-side.jpg', 900, 600);

        $response = $this->actingAs($host)->post(route('unit-drafts.store'), [
            'name' => 'Draft Airport Car',
            'kind' => 'service',
            'category' => 'driving',
            'photos' => [$firstPhoto, $secondPhoto],
            'primary_image' => 'new:1',
        ], ['Accept' => 'application/json'])->assertOk();

        $draft = UnitDraft::findOrFail($response->json('id'));
        $firstPath = 'listing-drafts/'.$host->id.'/'.$firstPhoto->hashName();
        $secondPath = 'listing-drafts/'.$host->id.'/'.$secondPhoto->hashName();
        $this->assertSame([$firstPath, $secondPath], $draft->payload['_draft_photo_paths']);
        $this->assertSame($secondPath, $draft->payload['_draft_primary_photo_path']);
        Storage::disk('public')->assertExists([$firstPath, $secondPath]);

        $this->actingAs($host)->get(route('units.create', ['draft' => $draft]))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($firstPath))
            ->assertSee(Storage::disk('public')->url($secondPath))
            ->assertSee('value="draft:1" checked', false);

        $this->actingAs($host)->post(route('units.store'), [
            'draft_id' => $draft->id,
            'name' => 'Draft Airport Car',
            'kind' => 'service',
            'category' => 'driving',
            'rules' => 'No smoking.',
            'primary_image' => 'draft:1',
            'price' => 1200,
            'pricing_unit' => 'session',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $unit = Unit::with('images')->firstOrFail();
        $this->assertCount(2, $unit->images);
        $this->assertSame($secondPath, $unit->photo_path);
        $this->assertSame($secondPath, $unit->images->first()->path);
        $this->assertDatabaseMissing('unit_drafts', ['id' => $draft->id]);
        Storage::disk('public')->assertExists([$firstPath, $secondPath]);

        $discardPhoto = UploadedFile::fake()->image('discard.jpg');
        $discardResponse = $this->actingAs($host)->post(route('unit-drafts.store'), [
            'name' => 'Discard image draft',
            'photos' => [$discardPhoto],
            'primary_image' => 'new:0',
        ], ['Accept' => 'application/json'])->assertOk();
        $discardDraft = UnitDraft::findOrFail($discardResponse->json('id'));
        $discardPath = $discardDraft->payload['_draft_photo_paths'][0];

        $this->actingAs($host)->delete(route('unit-drafts.destroy', $discardDraft))->assertRedirect(route('units.create'));
        Storage::disk('public')->assertMissing($discardPath);
    }

    public function test_other_service_draft_can_change_category_and_complete_registration(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();

        $response = $this->actingAs($host)->post(route('unit-drafts.store'), [
            'name' => 'Home Care Service',
            'kind' => 'service',
            'category' => 'other',
            'custom_category' => 'Home Care',
            'photos' => [UploadedFile::fake()->image('home-care.jpg')],
            'primary_image' => 'new:0',
        ], ['Accept' => 'application/json'])->assertOk();

        $draft = UnitDraft::findOrFail($response->json('id'));
        $this->assertSame('other', $draft->payload['category']);

        $this->actingAs($host)->postJson(route('unit-drafts.store'), [
            'draft_id' => $draft->id,
            'name' => 'Home Cleaning Service',
            'kind' => 'service',
            'category' => 'cleaning',
            'custom_category' => 'Home Care',
        ])->assertOk();

        $draft->refresh();
        $this->assertSame('cleaning', $draft->payload['category']);
        $this->assertArrayNotHasKey('custom_category', $draft->payload);

        $this->actingAs($host)->get(route('units.create', ['draft' => $draft]))
            ->assertOk()
            ->assertSee('value="cleaning" selected', false)
            ->assertDontSee('value="other" selected', false);

        $this->actingAs($host)->post(route('units.store'), [
            'draft_id' => $draft->id,
            'name' => 'Home Cleaning Service',
            'kind' => 'service',
            'category' => 'cleaning',
            'rules' => 'The client must provide access at the agreed time.',
            'primary_image' => 'draft:0',
            'price' => 900,
            'pricing_unit' => 'session',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $this->assertDatabaseHas('units', [
            'host_id' => $host->id,
            'name' => 'Home Cleaning Service',
            'kind' => 'service',
            'category' => 'cleaning',
        ]);
        $this->assertDatabaseMissing('unit_drafts', ['id' => $draft->id]);
    }

    public function test_mismatched_draft_category_is_recovered_for_the_selected_listing_type(): void
    {
        $host = User::factory()->host()->create();
        $draft = UnitDraft::create([
            'host_id' => $host->id,
            'title' => 'Incorrect category draft',
            'payload' => [
                'name' => 'Incorrect category draft',
                'kind' => 'unit',
                'category' => 'other',
                'custom_category' => 'Old custom service',
            ],
        ]);

        $this->actingAs($host)->get(route('units.create', ['draft' => $draft]))
            ->assertOk()
            ->assertSee('value="car" selected', false)
            ->assertDontSee('value="other" selected', false);

        $this->actingAs($host)->postJson(route('unit-drafts.store'), [
            'draft_id' => $draft->id,
            'name' => 'Recovered car draft',
            'kind' => 'unit',
            'category' => 'other',
            'custom_category' => 'Old custom service',
        ])->assertOk();

        $draft->refresh();
        $this->assertSame('unit', $draft->payload['kind']);
        $this->assertSame('car', $draft->payload['category']);
        $this->assertArrayNotHasKey('custom_category', $draft->payload);
    }

    public function test_host_can_register_a_unit_and_client_cannot(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();

        $payload = [
            'name' => 'City Condo 12A',
            'kind' => 'unit',
            'category' => 'condo',
            'location' => 'Makati',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'description' => 'One-bedroom condo',
            'rules' => "No smoking.\nQuiet hours start at 10 PM.",
            'capacity' => 2,
            'photos' => [
                UploadedFile::fake()->image('living-room.jpg', 1200, 800),
                UploadedFile::fake()->image('bedroom.jpg', 1200, 800),
            ],
            'offered_rates' => ['day', 'week', 'month'],
            'rates' => [
                'day' => 2500,
                'week' => 14000,
                'month' => 45000,
            ],
            'property' => [
                'type' => 'condo',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'beds' => 2,
                'floor_area_sqm' => 54,
            ],
            'property_amenities' => ['wifi', 'kitchen', 'pool'],
            'wifi' => ['ssid' => 'CityCondoGuest', 'password' => 'condo-password'],
            'pool' => ['payment_type' => 'included'],
            'is_active' => 1,
        ];

        $this->actingAs($host)->post(route('units.store'), $payload)->assertRedirect('/units');
        $this->assertDatabaseHas('units', ['host_id' => $host->id, 'name' => 'City Condo 12A']);
        $this->assertDatabaseHas('unit_rates', ['period' => 'week', 'price' => 14000]);
        $this->assertDatabaseMissing('unit_rates', ['period' => '12_hours']);
        $this->assertDatabaseCount('unit_images', 2);
        $unit = Unit::with('images')->firstOrFail();
        $this->assertSame(2, $unit->property_details['bedrooms']);
        $this->assertSame('14.5547000', $unit->latitude);
        $this->assertSame('121.0244000', $unit->longitude);
        $this->assertSame(['wifi', 'kitchen', 'pool'], $unit->property_details['amenities']);
        $this->assertSame('included', $unit->property_details['pool']['payment_type']);
        $unit->images->each(fn ($image) => Storage::disk('public')->assertExists($image->path));

        $this->actingAs($client)->post(route('units.store'), $payload)->assertForbidden();
    }

    public function test_condo_inquiries_and_bookings_use_the_hosts_fixed_check_in_and_check_out_times(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Fixed Schedule Condo',
            'kind' => 'unit',
            'category' => 'condo',
            'property_details' => [
                'type' => 'condo',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
            ],
            'price' => 2500,
            'pricing_unit' => 'day',
        ]);
        $unit->rates()->create(['period' => 'day', 'price' => 2500]);
        $requestedStart = now()->addDays(5)->setTime(9, 15)->startOfMinute();
        $requestedEnd = $requestedStart->copy()->addDays(3)->setTime(18, 45);

        $this->actingAs($client)->post(route('inquiries.store'), [
            'unit_id' => $unit->id,
            'desired_start_at' => $requestedStart->toDateTimeString(),
            'desired_end_at' => $requestedEnd->toDateTimeString(),
            'party_size' => 1,
            'initial_message' => 'I would like to reserve this condo for three nights.',
        ])->assertRedirect();

        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('14:00', $inquiry->desired_start_at->format('H:i'));
        $this->assertSame('12:00', $inquiry->desired_end_at->format('H:i'));

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $requestedStart->toDateTimeString(),
            'end_at' => $requestedEnd->toDateTimeString(),
            'duration_pricing' => 1,
            'party_size' => 1,
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('14:00', $booking->start_at->format('H:i'));
        $this->assertSame('12:00', $booking->end_at->format('H:i'));
        $this->assertSame(3, $booking->rate_quantity);
        $this->assertSame('7500.00', $booking->total_amount);

        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Host-set check-in: 2:00 PM')
            ->assertSee('Host-set check-out: 12:00 PM');
    }

    public function test_service_listing_categories_change_and_other_can_add_a_custom_category(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();

        $this->actingAs($host)->get(route('units.create'))
            ->assertOk()
            ->assertSee('data-category-group="service"', false)
            ->assertSee('<option value="cleaning"', false)
            ->assertSee('<option value="driving"', false)
            ->assertSee('<option value="massage"', false)
            ->assertSee('<option value="consultancy"', false)
            ->assertSee('name="custom_category"', false);

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Weekend Lawn Care',
            'kind' => 'service',
            'category' => 'other',
            'custom_category' => 'Lawn Care',
            'rules' => 'Client must provide access to the property.',
            'photos' => [UploadedFile::fake()->image('lawn-care.jpg')],
            'price' => 750,
            'pricing_unit' => 'session',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $unit = Unit::firstOrFail();
        $this->assertSame('service', $unit->kind);
        $this->assertSame('lawn_care', $unit->category);

        $client = User::factory()->create();
        $this->actingAs($client)->get(route('calendar.index', ['category' => 'lawn_care']))
            ->assertOk()
            ->assertSee('Lawn Care');

        $this->actingAs($host)->get(route('units.edit', $unit))
            ->assertOk()
            ->assertSee('value="other" selected', false)
            ->assertSee('name="custom_category" type="text" value="Lawn Care"', false);
    }

    public function test_car_registration_stores_vehicle_details_and_accessories(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Family Car',
            'kind' => 'unit',
            'category' => 'car',
            'location' => 'Quezon City',
            'capacity' => 5,
            'rules' => "Return with a full tank.\nOnly registered drivers may use the car.",
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('inside.jpg')],
            'car_rate_areas' => ['within_city'],
            'car_offered_rates' => ['within_city' => ['12_hours', 'day']],
            'car_rates' => ['within_city' => ['12_hours' => 1800, 'day' => 2800]],
            'car' => [
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2026,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'color' => 'Silver',
            ],
            'car_accessories' => ['air_conditioning', 'bluetooth', 'reverse_camera'],
            'custom_accessories' => ['Portable tire inflator', 'Emergency toolkit'],
            'car_charges' => [
                'car_wash' => ['enabled' => 1, 'amount' => 350],
                'delivery' => ['enabled' => 0, 'amount' => null],
                'deposit' => ['enabled' => 1, 'amount' => 2500],
            ],
            'is_active' => 1,
        ])->assertRedirect('/units');

        $unit = Unit::firstOrFail();
        $this->assertSame('Toyota', $unit->car_details['make']);
        $this->assertSame('Silver', $unit->car_details['color']);
        $this->assertSame(['air_conditioning', 'bluetooth', 'reverse_camera'], $unit->car_details['accessories']);
        $this->assertSame(['Portable tire inflator', 'Emergency toolkit'], $unit->car_details['custom_accessories']);
        $this->assertEquals(350.0, $unit->car_details['charges']['car_wash']['amount']);
        $this->assertArrayNotHasKey('delivery', $unit->car_details['charges']);
        $this->assertTrue($unit->car_details['charges']['deposit']['refundable']);
        $this->assertDatabaseCount('unit_rates', 2);
        $this->assertDatabaseCount('unit_images', 2);
    }

    public function test_car_can_have_separate_within_city_and_out_of_town_booking_prices(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();

        $this->actingAs($host)->get(route('units.create'))
            ->assertOk()
            ->assertSee('Rental coverage')
            ->assertSee('Within-city use')
            ->assertSee('Out-of-town use');

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Coverage Priced Sedan',
            'kind' => 'unit',
            'category' => 'car',
            'rules' => 'Return on time and follow the selected travel coverage.',
            'photos' => [UploadedFile::fake()->image('sedan.jpg')],
            'car_rate_areas' => ['within_city', 'out_of_town'],
            'car_offered_rates' => [
                'within_city' => ['day'],
                'out_of_town' => ['day'],
            ],
            'car_rates' => [
                'within_city' => ['day' => 2500],
                'out_of_town' => ['day' => 3600],
            ],
            'car' => [
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2026,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'color' => 'White',
            ],
            'is_active' => 1,
        ])->assertRedirect('/units');

        $unit = Unit::firstOrFail();
        $this->assertDatabaseHas('unit_rates', ['unit_id' => $unit->id, 'coverage' => 'within_city', 'period' => 'day', 'price' => 2500]);
        $this->assertDatabaseHas('unit_rates', ['unit_id' => $unit->id, 'coverage' => 'out_of_town', 'period' => 'day', 'price' => 3600]);

        $start = now()->addDays(3)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $start, $start->copy()->addDay());
        $bookingPayload = [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'duration_pricing' => 1,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addDay()->toDateTimeString(),
        ];

        $this->actingAs($client)->post(route('bookings.store'), $bookingPayload)
            ->assertSessionHasErrors('rental_coverage');

        $this->actingAs($client)->post(route('bookings.store'), $bookingPayload + ['rental_coverage' => 'out_of_town'])
            ->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('out_of_town', $booking->rental_coverage);
        $this->assertSame('3600.00', $booking->total_amount);
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Rental coverage')
            ->assertSee('Out-of-town use');
    }

    public function test_editing_a_legacy_car_restores_prices_and_can_add_out_of_town_rates(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Legacy City Car',
            'kind' => 'unit',
            'category' => 'car',
            'price' => 1800,
            'pricing_unit' => '12_hours',
            'car_details' => [
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2025,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'color' => 'Silver',
                'accessories' => [],
            ],
        ]);
        $unit->images()->create(['path' => 'listings/legacy-car.jpg', 'sort_order' => 1]);
        $unit->rates()->createMany([
            ['coverage' => 'standard', 'period' => '12_hours', 'price' => 1800],
            ['coverage' => 'standard', 'period' => 'day', 'price' => 2800],
        ]);

        $this->actingAs($host)->get(route('units.edit', $unit))
            ->assertOk()
            ->assertSee('name="car_rates[within_city][12_hours]" type="number" value="1800.00"', false)
            ->assertSee('name="car_rates[within_city][day]" type="number" value="2800.00"', false);

        $this->actingAs($host)->put(route('units.update', $unit), [
            'name' => 'Legacy City Car',
            'kind' => 'unit',
            'category' => 'car',
            'rules' => 'Follow the selected travel coverage and return on time.',
            'car_rate_areas' => ['within_city', 'out_of_town'],
            'car_offered_rates' => [
                'within_city' => ['12_hours', 'day'],
                'out_of_town' => ['12_hours', 'day'],
            ],
            'car_rates' => [
                'within_city' => ['12_hours' => 1900, 'day' => 2900],
                'out_of_town' => ['12_hours' => 2400, 'day' => 3600],
            ],
            'car' => [
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2025,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'color' => 'Silver',
            ],
            'is_active' => 1,
        ])->assertRedirect('/units');

        $this->assertDatabaseMissing('unit_rates', ['unit_id' => $unit->id, 'coverage' => 'standard']);
        $this->assertDatabaseHas('unit_rates', ['unit_id' => $unit->id, 'coverage' => 'within_city', 'period' => 'day', 'price' => 2900]);
        $this->assertDatabaseHas('unit_rates', ['unit_id' => $unit->id, 'coverage' => 'out_of_town', 'period' => 'day', 'price' => 3600]);

        $this->actingAs($host)->get(route('units.edit', $unit->fresh()))
            ->assertOk()
            ->assertSee('name="car_rates[out_of_town][day]" type="number" value="3600.00"', false);
    }

    public function test_required_car_charges_are_snapshotted_and_added_to_the_booking_total(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Charged Rental Car',
            'kind' => 'unit',
            'category' => 'car',
            'price' => 2500,
            'pricing_unit' => 'day',
            'car_details' => [
                'make' => 'Toyota',
                'model' => 'Vios',
                'color' => 'Blue',
                'charges' => [
                    'car_wash' => ['label' => 'Car wash', 'amount' => 300, 'refundable' => false],
                    'delivery' => ['label' => 'Delivery', 'amount' => 500, 'refundable' => false],
                    'deposit' => ['label' => 'Refundable deposit', 'amount' => 2000, 'refundable' => true],
                ],
            ],
        ]);
        $unit->rates()->create(['period' => 'day', 'price' => 2500]);
        $start = now()->addDays(3)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $start, $start->copy()->addDay());

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'duration_pricing' => 1,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('5300.00', $booking->total_amount);
        $this->assertCount(3, $booking->additional_charges);
        $this->assertTrue($booking->additional_charges[2]['refundable']);
        $this->assertSame(2000.0, $booking->refundableDepositAmount());
        $this->assertSame(3300.0, $booking->revenueAmount());
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Required charges')
            ->assertSee('Refundable deposit')
            ->assertSee('₱5,300.00');
    }

    public function test_host_can_add_and_remove_gallery_images_without_removing_them_all(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $firstPhoto = UploadedFile::fake()->image('one.jpg');
        $secondPhoto = UploadedFile::fake()->image('two.jpg');

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Airport Transfer',
            'kind' => 'service',
            'category' => 'driving',
            'rules' => 'Maximum waiting time is 30 minutes.',
            'photos' => [$firstPhoto, $secondPhoto],
            'primary_image' => 'new:1',
            'price' => 900,
            'pricing_unit' => 'session',
            'is_active' => 1,
        ])->assertRedirect('/units');

        $unit = Unit::with('images')->firstOrFail();
        $this->assertSame('listings/'.$secondPhoto->hashName(), $unit->photo_path);
        $this->assertSame($unit->photo_path, $unit->images->first()->path);
        $removedImage = $unit->images->first();
        $replacementPhoto = UploadedFile::fake()->image('three.jpg');

        $this->actingAs($host)->put(route('units.update', $unit), [
            'name' => 'Airport Transfer',
            'kind' => 'service',
            'category' => 'driving',
            'rules' => 'Maximum waiting time is 30 minutes.',
            'photos' => [$replacementPhoto],
            'primary_image' => 'new:0',
            'remove_images' => [$removedImage->id],
            'price' => 900,
            'pricing_unit' => 'session',
            'is_active' => 1,
        ])->assertRedirect('/units');

        $unit->refresh()->load('images');
        $this->assertCount(2, $unit->images);
        $this->assertSame('listings/'.$replacementPhoto->hashName(), $unit->photo_path);
        $this->assertSame($unit->photo_path, $unit->images->first()->path);
        Storage::disk('public')->assertMissing($removedImage->path);
        $unit->images->each(fn ($image) => Storage::disk('public')->assertExists($image->path));
    }

    public function test_rental_package_sets_the_booking_duration_and_separate_price(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Family SUV',
            'kind' => 'unit',
            'category' => 'car',
            'price' => 2200,
            'pricing_unit' => '12_hours',
        ]);
        $rate = $unit->rates()->create(['period' => 'week', 'price' => 11500]);
        $start = now()->addDays(4)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $start, $start->copy()->addWeek());

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'unit_rate_id' => $rate->id,
            'start_at' => $start->toDateTimeString(),
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertTrue($booking->end_at->equalTo($start->copy()->addWeek()));
        $this->assertSame('week', $booking->rate_period);
        $this->assertSame(1, $booking->rate_quantity);
        $this->assertSame('11500.00', $booking->total_amount);
    }

    public function test_client_can_combine_one_day_and_twelve_hours_and_total_is_itemized(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Flexible Family Car',
            'kind' => 'unit',
            'category' => 'car',
            'price' => 1400,
            'pricing_unit' => '12_hours',
        ]);
        $unit->rates()->createMany([
            ['period' => '12_hours', 'price' => 1400],
            ['period' => 'day', 'price' => 2500],
        ]);
        $start = now()->addDays(4)->startOfHour();
        $end = $start->copy()->addDay()->addHours(12);
        $inquiry = $this->createInquiry($unit, $client, $start, $end);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'duration_pricing' => 1,
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertTrue($booking->end_at->equalTo($start->copy()->addDay()->addHours(12)));
        $this->assertSame('mixed', $booking->rate_period);
        $this->assertSame(2, $booking->rate_quantity);
        $this->assertNull($booking->unit_rate_id);
        $this->assertSame('3900.00', $booking->total_amount);
        $this->assertSame(1, $booking->package_breakdown['12_hours']['quantity']);
        $this->assertSame(1400, $booking->package_breakdown['12_hours']['subtotal']);
        $this->assertSame(1, $booking->package_breakdown['day']['quantity']);
        $this->assertSame(2500, $booking->package_breakdown['day']['subtotal']);
    }

    public function test_client_dates_can_match_one_month_and_two_weeks_with_the_correct_total(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Extended Stay Condo',
            'kind' => 'unit',
            'category' => 'condo',
            'price' => 14000,
            'pricing_unit' => 'week',
        ]);
        $unit->rates()->createMany([
            ['period' => 'week', 'price' => 14000],
            ['period' => 'month', 'price' => 45000],
        ]);
        $start = now()->addDays(5)->setTime(9, 30)->startOfMinute();
        $end = $start->copy()->addMonthNoOverflow()->addWeeks(2);
        $inquiry = $this->createInquiry($unit, $client, $start, $end);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'duration_pricing' => 1,
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('14:00', $booking->start_at->format('H:i'));
        $this->assertSame('12:00', $booking->end_at->format('H:i'));
        $this->assertTrue($booking->end_at->isSameDay($start->copy()->addMonthNoOverflow()->addWeeks(2)));
        $this->assertSame('mixed', $booking->rate_period);
        $this->assertSame(3, $booking->rate_quantity);
        $this->assertSame('73000.00', $booking->total_amount);
        $this->assertSame(2, $booking->package_breakdown['week']['quantity']);
        $this->assertSame(1, $booking->package_breakdown['month']['quantity']);

        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('2 × 1 week')
            ->assertSee('1 × 1 month')
            ->assertSee('₱73,000.00');
    }

    public function test_client_can_book_later_today_but_not_a_past_time_and_end_must_follow_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:00:00'));

        try {
            $host = User::factory()->host()->create();
            $client = User::factory()->create();
            $unit = $this->createUnit($host);
            $pastStart = now()->subHour();
            $laterToday = now()->addHours(2);

            $this->actingAs($client)->get(route('calendar.index', [
                'category' => 'driving',
                'search' => 1,
                'search_start' => $pastStart->toDateTimeString(),
                'search_end' => $pastStart->copy()->addHour()->toDateTimeString(),
                'party_size' => 1,
            ]))->assertSessionHasErrors('search_start');

            $this->actingAs($client)->get(route('calendar.index', [
                'category' => 'driving',
                'search' => 1,
                'search_start' => $laterToday->toDateTimeString(),
                'search_end' => $laterToday->copy()->addHour()->toDateTimeString(),
                'party_size' => 1,
            ]))->assertOk()->assertSee($unit->name);

            $pastInquiry = $this->createInquiry($unit, $client, $pastStart, $pastStart->copy()->addHour());
            $this->actingAs($client)->post(route('bookings.store'), [
                'unit_id' => $unit->id,
                'inquiry_id' => $pastInquiry->id,
                'start_at' => $pastStart->toDateTimeString(),
                'end_at' => $pastStart->copy()->addHour()->toDateTimeString(),
            ])->assertSessionHasErrors('start_at');

            $futureInquiry = $this->createInquiry($unit, $client, $laterToday, $laterToday->copy()->addHours(2));
            $this->actingAs($client)->post(route('bookings.store'), [
                'unit_id' => $unit->id,
                'inquiry_id' => $futureInquiry->id,
                'start_at' => $laterToday->toDateTimeString(),
                'end_at' => $laterToday->copy()->subMinute()->toDateTimeString(),
            ])->assertSessionHasErrors('end_at');

            $this->actingAs($client)->post(route('bookings.store'), [
                'unit_id' => $unit->id,
                'inquiry_id' => $futureInquiry->id,
                'start_at' => $laterToday->toDateTimeString(),
                'end_at' => $laterToday->copy()->addHours(2)->toDateTimeString(),
            ])->assertRedirect();

            $this->assertDatabaseCount('bookings', 1);
            $this->assertTrue(Booking::firstOrFail()->start_at->isSameDay(now()));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_client_and_host_receive_role_specific_overviews(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Dashboard Marketing Condo',
            'kind' => 'unit',
            'category' => 'condo',
            'price' => 2800,
            'pricing_unit' => 'day',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
        ]);
        $unit->rates()->create(['period' => 'day', 'price' => 2800]);

        $this->actingAs($client)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Find the right ride, stay, or service')
            ->assertSee('What can I book near me?')
            ->assertSee('Featured rentals and services')
            ->assertSee('Dashboard Marketing Condo');

        $this->actingAs($host)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rental control center')
            ->assertSee('Live availability')
            ->assertSee('Dashboard Marketing Condo')
            ->assertSee('Available now');
    }

    public function test_gps_access_requires_private_credentials_and_clients_cannot_see_them(): void
    {
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $payload = [
            'name' => 'Tracked SUV',
            'kind' => 'unit',
            'category' => 'car',
            'photos' => [UploadedFile::fake()->image('suv.jpg')],
            'car_rate_areas' => ['within_city'],
            'car_offered_rates' => ['within_city' => ['day']],
            'car_rates' => ['within_city' => ['day' => 3500]],
            'car' => [
                'make' => 'Toyota',
                'model' => 'Fortuner',
                'year' => 2026,
                'transmission' => 'automatic',
                'fuel_type' => 'diesel',
                'color' => 'Black',
            ],
            'car_accessories' => ['gps'],
            'rules' => 'No smoking. Return with a full tank.',
            'is_active' => 1,
        ];

        $this->actingAs($host)->post(route('units.store'), $payload)
            ->assertSessionHasErrors(['gps.device_name', 'gps.username', 'gps.password']);

        $payload['gps'] = [
            'device_name' => 'SinoTrack Pro',
            'login_url' => 'https://example.com/tracker',
            'username' => 'fleet-owner',
            'password' => 'secret-tracker-password',
            'notes' => 'Tracker ID 1001',
        ];

        $this->actingAs($host)->post(route('units.store'), $payload)->assertRedirect('/units');

        $unit = Unit::firstOrFail();
        $this->assertSame('fleet-owner', $unit->gps_details['username']);
        $this->assertArrayNotHasKey('gps_details', $unit->toArray());
        $storedGps = DB::table('units')->where('id', $unit->id)->value('gps_details');
        $this->assertStringNotContainsString('fleet-owner', $storedGps);
        $this->assertStringNotContainsString('secret-tracker-password', $storedGps);

        $this->actingAs($host)->get(route('units.index'))
            ->assertOk()
            ->assertSee('Host-only GPS access')
            ->assertSee('fleet-owner')
            ->assertSee('secret-tracker-password');

        $this->actingAs($client)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Car rules')
            ->assertSee('No smoking. Return with a full tank.')
            ->assertDontSee('fleet-owner')
            ->assertDontSee('secret-tracker-password')
            ->assertDontSee('Tracker ID 1001');
    }

    public function test_condo_wifi_access_is_private_until_confirmation_and_amenity_fees_are_public(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unrelatedClient = User::factory()->create();
        $qrCode = UploadedFile::fake()->image('wifi-qr.png', 300, 300);

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Connected Condo',
            'kind' => 'unit',
            'category' => 'condo',
            'photos' => [UploadedFile::fake()->image('condo.jpg')],
            'offered_rates' => ['day'],
            'rates' => ['day' => 3000],
            'property' => ['type' => 'condo', 'bedrooms' => 1, 'bathrooms' => 1, 'beds' => 1],
            'property_amenities' => ['wifi', 'parking', 'pool'],
            'wifi' => ['ssid' => 'CondoGuest5G', 'password' => 'private-wifi-pass', 'notes' => 'Use the 5 GHz network.'],
            'wifi_qr' => $qrCode,
            'parking' => ['payment_type' => 'separate', 'rate' => 450, 'rate_unit' => 'day'],
            'pool' => ['payment_type' => 'included'],
            'rules' => 'Observe quiet hours after 10 PM.',
            'is_active' => 1,
        ])->assertRedirect('/units');

        $unit = Unit::firstOrFail();
        $rate = $unit->rates()->firstOrFail();
        $this->assertSame('CondoGuest5G', $unit->wifi_details['ssid']);
        $this->assertSame('separate', $unit->property_details['parking']['payment_type']);
        $this->assertSame(450, $unit->property_details['parking']['rate']);
        $this->assertSame('included', $unit->property_details['pool']['payment_type']);
        $this->assertArrayNotHasKey('wifi_details', $unit->toArray());
        $storedWifi = DB::table('units')->where('id', $unit->id)->value('wifi_details');
        $this->assertStringNotContainsString('CondoGuest5G', $storedWifi);
        $this->assertStringNotContainsString('private-wifi-pass', $storedWifi);
        Storage::disk('local')->assertExists($unit->wifi_qr_path);

        $start = now()->addDays(2)->startOfHour();
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'unit_rate_id' => $rate->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addDay(),
            'status' => 'pending',
            'rate_period' => 'day',
            'total_amount' => 3000,
        ]);

        $calendarUrl = route('calendar.index', ['month' => $start->format('Y-m'), 'date' => $start->format('Y-m-d')]);
        $this->actingAs($client)->get($calendarUrl)
            ->assertOk()
            ->assertSee('Parking: ₱450.00 / day')
            ->assertSee('Swimming pool: Included')
            ->assertSee('Wi-Fi access will appear here after the host confirms this booking.')
            ->assertDontSee('CondoGuest5G')
            ->assertDontSee('private-wifi-pass');
        $this->actingAs($client)->get(route('units.wifi-qr', $unit))->assertForbidden();
        $this->actingAs($unrelatedClient)->get(route('units.wifi-qr', $unit))->assertForbidden();

        $this->actingAs($host)->patch(route('bookings.status', $booking), ['status' => 'pre_approved'])->assertRedirect();
        $this->actingAs($client)->post(route('bookings.payment-proof.store', $booking), [
            'payment_proof' => UploadedFile::fake()->image('payment-proof.jpg'),
        ])->assertRedirect();
        $this->actingAs($host)->patch(route('bookings.status', $booking), ['status' => 'confirmed'])->assertRedirect();

        $this->actingAs($client)->get($calendarUrl)
            ->assertOk()
            ->assertSee('CondoGuest5G')
            ->assertSee('private-wifi-pass')
            ->assertSee(route('units.wifi-qr', $unit));
        $this->actingAs($client)->get(route('units.wifi-qr', $unit))->assertOk();
        $this->actingAs($unrelatedClient)->get(route('units.wifi-qr', $unit))->assertForbidden();
    }

    public function test_rental_rate_must_belong_to_the_selected_unit(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $selectedUnit = $this->createUnit($host, ['category' => 'car', 'pricing_unit' => '12_hours']);
        $otherUnit = $this->createUnit($host, ['name' => 'Other car', 'category' => 'car', 'pricing_unit' => '12_hours']);
        $otherRate = $otherUnit->rates()->create(['period' => 'day', 'price' => 3000]);
        $inquiry = $this->createInquiry($selectedUnit, $client, now()->addDays(2), now()->addDays(3));

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $selectedUnit->id,
            'inquiry_id' => $inquiry->id,
            'unit_rate_id' => $otherRate->id,
            'start_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertSessionHasErrors('unit_rate_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_client_can_book_an_available_unit_and_total_is_calculated(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, ['price' => 500, 'pricing_unit' => 'hour']);
        $start = now()->addDays(2)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $start, $start->copy()->addMinutes(90));

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addMinutes(90)->toDateTimeString(),
            'notes' => 'Airport pickup',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'status' => 'pending',
            'total_amount' => 1000,
        ]);
    }

    public function test_overlapping_bookings_are_rejected_but_cancelled_time_is_released(): void
    {
        $host = User::factory()->host()->create();
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $unit = $this->createUnit($host);
        $start = now()->addWeek()->startOfHour();

        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $firstClient->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);

        $payload = [
            'unit_id' => $unit->id,
            'inquiry_id' => $this->createInquiry($unit, $secondClient, $start->copy()->addHour(), $start->copy()->addHours(3))->id,
            'start_at' => $start->copy()->addHour()->toDateTimeString(),
            'end_at' => $start->copy()->addHours(3)->toDateTimeString(),
        ];

        $this->actingAs($secondClient)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('start_at');
        $this->assertDatabaseCount('bookings', 1);

        $booking->update(['status' => 'cancelled']);
        $this->actingAs($secondClient)->post(route('bookings.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_same_unit_can_be_booked_again_for_non_overlapping_dates(): void
    {
        $host = User::factory()->host()->create();
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $unit = $this->createUnit($host);
        $firstStart = now()->addWeek()->startOfHour();
        $secondStart = $firstStart->copy()->addHours(2);

        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $firstClient->id,
            'start_at' => $firstStart,
            'end_at' => $firstStart->copy()->addHours(2),
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);
        $inquiry = $this->createInquiry($unit, $secondClient, $secondStart, $secondStart->copy()->addHours(2));

        $this->actingAs($secondClient)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $secondStart->toDateTimeString(),
            'end_at' => $secondStart->copy()->addHours(2)->toDateTimeString(),
            'party_size' => 2,
        ])->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseHas('bookings', [
            'unit_id' => $unit->id,
            'client_id' => $secondClient->id,
            'status' => 'pending',
            'start_at' => $secondStart->toDateTimeString(),
        ]);
    }

    public function test_client_change_request_keeps_current_booking_until_host_approval(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, ['price' => 600, 'pricing_unit' => 'hour']);
        $originalStart = now()->addDays(5)->startOfHour();
        $originalEnd = $originalStart->copy()->addHours(2);
        $requestedStart = $originalStart->copy()->addDays(3);
        $requestedEnd = $requestedStart->copy()->addHours(3);
        $inquiry = $this->createInquiry($unit, $client, $originalStart, $originalEnd, 2);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'start_at' => $originalStart,
            'end_at' => $originalEnd,
            'party_size' => 2,
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);

        $this->actingAs($client)->patch(route('bookings.change-request', $booking), [
            'change_start_at' => $requestedStart->toDateTimeString(),
            'change_end_at' => $requestedEnd->toDateTimeString(),
            'change_party_size' => 3,
            'change_request_note' => 'Our flight schedule changed.',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertTrue($booking->start_at->equalTo($originalStart));
        $this->assertTrue($booking->end_at->equalTo($originalEnd));
        $this->assertSame(2, $booking->party_size);
        $this->assertSame('pending', $booking->change_request_status);
        $this->assertTrue($booking->change_start_at->equalTo($requestedStart));
        $this->actingAs($host)->get(route('bookings.show', $booking))->assertOk()->assertSee('Approve changes');

        $this->actingAs($host)->patch(route('bookings.change-request.review', $booking), ['decision' => 'approve'])->assertRedirect();

        $booking->refresh();
        $this->assertTrue($booking->start_at->equalTo($requestedStart));
        $this->assertTrue($booking->end_at->equalTo($requestedEnd));
        $this->assertSame(3, $booking->party_size);
        $this->assertSame('approved', $booking->change_request_status);
        $this->assertSame('1800.00', $booking->total_amount);
        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'party_size' => 3,
            'desired_start_at' => $requestedStart->toDateTimeString(),
            'desired_end_at' => $requestedEnd->toDateTimeString(),
        ]);
    }

    public function test_approved_package_date_change_updates_quantity_and_multiplies_locked_rate(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, [
            'name' => 'Daily Rental Condo',
            'kind' => 'unit',
            'category' => 'condo',
            'price' => 2500,
            'pricing_unit' => 'day',
        ]);
        $rate = $unit->rates()->create(['period' => 'day', 'price' => 2500]);
        $originalStart = now()->addDays(5)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $originalStart, $originalStart->copy()->addDay(), 2);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'unit_rate_id' => $rate->id,
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'start_at' => $originalStart,
            'end_at' => $originalStart->copy()->addDay(),
            'party_size' => 2,
            'status' => 'confirmed',
            'rate_period' => 'day',
            'rate_quantity' => 1,
            'total_amount' => 2500,
        ]);
        $requestedStart = $originalStart->copy()->addDays(4);
        $requestedEnd = $requestedStart->copy()->addDays(4);

        $this->actingAs($client)->patch(route('bookings.change-request', $booking), [
            'change_start_at' => $requestedStart->toDateTimeString(),
            'change_end_at' => $requestedEnd->toDateTimeString(),
            'change_party_size' => 2,
        ])->assertRedirect();

        $this->actingAs($host)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('4 × 1 day')
            ->assertSee('₱10,000.00');
        $this->actingAs($host)->patch(route('bookings.change-request.review', $booking), ['decision' => 'approve'])->assertRedirect();

        $booking->refresh();
        $this->assertSame(4, $booking->rate_quantity);
        $this->assertSame('10000.00', $booking->total_amount);
        $this->assertTrue($booking->start_at->isSameDay($requestedStart));
        $this->assertSame('14:00', $booking->start_at->format('H:i'));
        $this->assertTrue($booking->end_at->isSameDay($requestedEnd));
        $this->assertSame('12:00', $booking->end_at->format('H:i'));
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('4 × 1 day')
            ->assertSee('₱2,500.00 each')
            ->assertSee('₱10,000.00');
    }

    public function test_change_request_and_approval_are_rejected_when_schedule_conflicts(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $unit = $this->createUnit($host);
        $originalStart = now()->addDays(4)->startOfHour();
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $originalStart,
            'end_at' => $originalStart->copy()->addHours(2),
            'party_size' => 2,
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);
        $busyStart = $originalStart->copy()->addDays(2);
        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $otherClient->id,
            'start_at' => $busyStart,
            'end_at' => $busyStart->copy()->addHours(2),
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);

        $this->actingAs($client)->patch(route('bookings.change-request', $booking), [
            'change_start_at' => $busyStart->copy()->addHour()->toDateTimeString(),
            'change_end_at' => $busyStart->copy()->addHours(3)->toDateTimeString(),
            'change_party_size' => 2,
        ])->assertSessionHasErrors('change_start_at');
        $this->assertNull($booking->fresh()->change_request_status);

        $safeStart = $busyStart->copy()->addDays(2);
        $this->actingAs($client)->patch(route('bookings.change-request', $booking), [
            'change_start_at' => $safeStart->toDateTimeString(),
            'change_end_at' => $safeStart->copy()->addHours(2)->toDateTimeString(),
            'change_party_size' => 2,
        ])->assertRedirect();
        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $otherClient->id,
            'start_at' => $safeStart,
            'end_at' => $safeStart->copy()->addHours(2),
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);

        $this->actingAs($host)->patch(route('bookings.change-request.review', $booking), ['decision' => 'approve'])
            ->assertSessionHasErrors('change_start_at');
        $booking->refresh();
        $this->assertTrue($booking->start_at->equalTo($originalStart));
        $this->assertSame('pending', $booking->change_request_status);
    }

    public function test_only_owning_host_can_complete_booking_approval(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'pending',
            'total_amount' => 600,
        ]);

        Storage::fake('local');
        $this->actingAs($otherHost)->patch(route('bookings.status', $booking), ['status' => 'pre_approved'])->assertForbidden();
        $this->actingAs($host)->patch(route('bookings.status', $booking), ['status' => 'pre_approved'])->assertRedirect();
        $this->actingAs($client)->post(route('bookings.payment-proof.store', $booking), [
            'payment_proof' => UploadedFile::fake()->image('payment-proof.jpg'),
        ])->assertRedirect();
        $this->actingAs($host)->patch(route('bookings.status', $booking), ['status' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_calendar_shows_availability_and_the_users_booking(): void
    {
        $host = User::factory()->host()->create(['name' => 'Calendar Host']);
        $client = User::factory()->create();
        $unit = $this->createUnit($host, ['name' => 'Green Sedan']);
        $date = now()->addDays(3)->startOfDay();

        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $date->copy()->setTime(9, 0),
            'end_at' => $date->copy()->setTime(11, 0),
            'status' => 'confirmed',
            'total_amount' => 1200,
        ]);

        $this->actingAs($client)->get(route('calendar.index', [
            'month' => $date->format('Y-m'),
            'date' => $date->format('Y-m-d'),
        ]))->assertOk()->assertSee('Green Sedan')->assertSee('Booked')->assertSee('Calendar Host');
    }

    public function test_host_calendar_renders_multi_day_booking_as_a_continuous_span_with_times(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create(['name' => 'Long Stay Client']);
        $unit = $this->createUnit($host, ['name' => 'Multi-day Condo']);
        $weekStart = now()->addWeeks(2)->startOfWeek(Carbon::SUNDAY);
        $start = $weekStart->copy()->addDay()->setTime(15, 0);
        $end = $weekStart->copy()->addDays(4)->setTime(10, 0);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $end,
            'party_size' => 2,
            'status' => 'confirmed',
            'total_amount' => 1800,
        ]);

        $this->actingAs($host)->get(route('calendar.index', [
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]))->assertOk()
            ->assertSee('data-booking-id="'.$booking->id.'"', false)
            ->assertSee('data-segment-start="'.$start->format('Y-m-d').'"', false)
            ->assertSee('data-segment-end="'.$end->format('Y-m-d').'"', false)
            ->assertSee('3:00 PM')
            ->assertSee('→ 10:00 AM')
            ->assertSee('Check times');
    }

    public function test_client_search_returns_only_matching_available_listings(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $available = $this->createUnit($host, ['name' => 'Manila Family Driver', 'capacity' => 5]);
        $this->createUnit($host, ['name' => 'Too Small Driver', 'capacity' => 2]);
        $this->createUnit($host, ['name' => 'Cebu Driver', 'location' => 'Cebu', 'capacity' => 6]);
        $busy = $this->createUnit($host, ['name' => 'Busy Manila Driver', 'capacity' => 6]);
        $start = now()->addDays(5)->startOfHour();
        $end = $start->copy()->addHours(3);

        Booking::create([
            'unit_id' => $busy->id,
            'client_id' => User::factory()->create()->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'confirmed',
            'total_amount' => 1800,
        ]);

        $response = $this->actingAs($client)->get(route('calendar.index', [
            'category' => 'driving',
            'search' => 1,
            'search_start' => $start->toDateTimeString(),
            'search_end' => $end->toDateTimeString(),
            'party_size' => 4,
            'location' => 'Manila',
        ]));

        $response->assertOk()
            ->assertSee('1 match for your trip')
            ->assertSee($available->name)
            ->assertDontSee('Too Small Driver')
            ->assertDontSee('Cebu Driver')
            ->assertDontSee('Busy Manila Driver');
    }

    public function test_client_can_filter_mapped_listings_by_radius(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $nearby = $this->createUnit($host, [
            'name' => 'Nearby Manila Driver',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);
        $this->createUnit($host, [
            'name' => 'Far Cebu Driver',
            'location' => 'Cebu',
            'latitude' => 10.3157,
            'longitude' => 123.8854,
        ]);
        $this->createUnit($host, [
            'name' => 'Unpinned Manila Driver',
            'latitude' => null,
            'longitude' => null,
        ]);
        $start = now()->addDays(5)->startOfHour();
        $end = $start->copy()->addHours(3);

        $response = $this->actingAs($client)->get(route('calendar.index', [
            'category' => 'driving',
            'search' => 1,
            'search_start' => $start->toDateTimeString(),
            'search_end' => $end->toDateTimeString(),
            'party_size' => 2,
            'search_latitude' => 14.5995,
            'search_longitude' => 120.9842,
            'radius_km' => 10,
        ]));

        $response->assertOk()
            ->assertSee('1 match for your trip')
            ->assertSee($nearby->name)
            ->assertSee('0.0 km away')
            ->assertSee('Choose your search area')
            ->assertDontSee('Far Cebu Driver')
            ->assertDontSee('Unpinned Manila Driver');
    }

    public function test_client_radius_search_defaults_to_five_hundred_kilometres(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get(route('calendar.index', ['category' => 'driving']))
            ->assertOk()
            ->assertSee('name="radius_km"', false)
            ->assertSee('max="1000"', false)
            ->assertSee('value="100"', false)
            ->assertSee('100 km');
    }

    public function test_listing_coordinates_must_be_submitted_as_a_valid_pair(): void
    {
        $host = User::factory()->host()->create();

        $this->actingAs($host)->post(route('units.store'), [
            'name' => 'Incomplete Map Pin',
            'kind' => 'service',
            'category' => 'driving',
            'price' => 900,
            'pricing_unit' => 'session',
            'latitude' => 14.5995,
            'is_active' => 1,
        ])->assertSessionHasErrors('longitude');

        $this->assertDatabaseMissing('units', ['name' => 'Incomplete Map Pin']);
    }

    public function test_booking_rejects_a_party_larger_than_the_listing_capacity(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, ['capacity' => 3]);
        $start = now()->addDays(2)->startOfHour();
        $inquiry = $this->createInquiry($unit, $client, $start, $start->copy()->addHours(2), 4);

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
            'party_size' => 4,
        ])->assertSessionHasErrors('party_size');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_can_permanently_delete_a_listing_and_all_of_its_records(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Site Administrator']);
        $firstHost = User::factory()->host()->create(['name' => 'First Listing Host']);
        $secondHost = User::factory()->host()->create(['name' => 'Second Listing Host']);
        $client = User::factory()->create();
        $recordedUnit = $this->createUnit($firstHost, ['name' => 'Recorded Rental']);
        $emptyUnit = $this->createUnit($secondHost, ['name' => 'Unused Rental']);
        $start = now()->subMonth();

        $booking = Booking::create([
            'unit_id' => $recordedUnit->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'status' => 'cancelled',
            'total_amount' => 600,
        ]);
        $inquiry = $this->createInquiry($recordedUnit, $client, $start, $start->copy()->addHour());
        $attachmentPath = 'inquiry-attachments/'.$inquiry->id.'/proof.jpg';
        Storage::disk('local')->put($attachmentPath, 'image contents');
        $message = $inquiry->messages()->create([
            'sender_id' => $client->id,
            'body' => 'Attached booking proof.',
            'attachment_path' => $attachmentPath,
            'attachment_name' => 'proof.jpg',
        ]);

        $this->actingAs($admin)->get(route('units.index'))
            ->assertOk()
            ->assertSee('Administrator listing moderation')
            ->assertSee('Recorded Rental')
            ->assertSee('Unused Rental')
            ->assertSee('First Listing Host')
            ->assertSee('Second Listing Host')
            ->assertSee('Yes, delete it')
            ->assertSee('No, keep it')
            ->assertDontSee('Deletion locked');

        $this->actingAs($admin)->delete(route('units.destroy', $recordedUnit))
            ->assertRedirect(route('units.index'))
            ->assertSessionHas('status', 'Recorded Rental and its 2 booking or inquiry records were permanently deleted.');

        $this->assertDatabaseMissing('units', ['id' => $recordedUnit->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('inquiries', ['id' => $inquiry->id]);
        $this->assertDatabaseMissing('inquiry_messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($attachmentPath);

        $this->actingAs($admin)->delete(route('units.destroy', $emptyUnit))
            ->assertRedirect(route('units.index'));
        $this->assertDatabaseMissing('units', ['id' => $emptyUnit->id]);
    }

    public function test_listing_with_inquiry_history_is_disabled_instead_of_deleted(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->createUnit($host, ['name' => 'Inquired Rental']);
        $start = now()->addDay();
        $this->createInquiry($unit, $client, $start, $start->copy()->addHour());

        $this->actingAs($host)->delete(route('units.destroy', $unit))
            ->assertRedirect(route('units.index'));

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'is_active' => false]);
        $this->assertDatabaseHas('inquiries', ['unit_id' => $unit->id]);
    }

    public function test_host_can_inquire_and_book_another_hosts_listing_but_not_their_own(): void
    {
        $bookingHost = User::factory()->host()->create(['name' => 'Booking Host']);
        $otherHost = User::factory()->host()->create(['name' => 'Other Host']);
        $ownUnit = $this->createUnit($bookingHost, ['name' => 'My Own Service']);
        $otherUnit = $this->createUnit($otherHost, ['name' => 'Another Host Service']);
        $start = now()->addDays(3)->startOfHour();
        $end = $start->copy()->addHours(2);

        $this->actingAs($bookingHost)->get(route('calendar.index', [
            'mode' => 'book',
            'category' => 'driving',
            'search' => 1,
            'search_start' => $start->format('Y-m-d\TH:i'),
            'search_end' => $end->format('Y-m-d\TH:i'),
            'party_size' => 1,
        ]))
            ->assertOk()
            ->assertSee('Another Host Service')
            ->assertDontSee('My Own Service')
            ->assertSee('Your own listings are excluded.');

        $this->actingAs($bookingHost)->post(route('inquiries.store'), [
            'unit_id' => $otherUnit->id,
            'desired_start_at' => $start->toDateTimeString(),
            'desired_end_at' => $end->toDateTimeString(),
            'party_size' => 1,
            'initial_message' => 'I would like to book your service for this schedule.',
        ])->assertRedirect();

        $inquiry = Inquiry::query()->where('unit_id', $otherUnit->id)->where('client_id', $bookingHost->id)->firstOrFail();

        $this->actingAs($bookingHost)->post(route('bookings.store'), [
            'unit_id' => $otherUnit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'party_size' => 1,
        ])->assertRedirect(route('calendar.index', [
            'mode' => 'book',
            'month' => $start->format('Y-m'),
            'date' => $start->format('Y-m-d'),
        ]));

        $this->assertDatabaseHas('bookings', [
            'unit_id' => $otherUnit->id,
            'client_id' => $bookingHost->id,
            'status' => 'pending',
        ]);

        $this->actingAs($bookingHost)->post(route('inquiries.store'), [
            'unit_id' => $ownUnit->id,
            'desired_start_at' => $start->toDateTimeString(),
            'desired_end_at' => $end->toDateTimeString(),
            'party_size' => 1,
            'initial_message' => 'This self inquiry must not be accepted by the server.',
        ])->assertSessionHasErrors('unit_id');

        $forgedOwnInquiry = $this->createInquiry($ownUnit, $bookingHost, $start, $end);
        $this->actingAs($bookingHost)->post(route('bookings.store'), [
            'unit_id' => $ownUnit->id,
            'inquiry_id' => $forgedOwnInquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'party_size' => 1,
        ])->assertSessionHasErrors('unit_id');

        $this->assertDatabaseMissing('bookings', [
            'unit_id' => $ownUnit->id,
            'client_id' => $bookingHost->id,
        ]);

        $this->actingAs($bookingHost)->get(route('listings.show', $ownUnit))
            ->assertOk()
            ->assertSee('You own this listing, so you cannot inquire about or book it.');
    }

    private function createUnit(User $host, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'host_id' => $host->id,
            'name' => 'Driving Service',
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Manila',
            'rules' => 'Follow the host instructions for this listing.',
            'capacity' => 4,
            'price' => 600,
            'pricing_unit' => 'session',
            'is_active' => true,
        ], $attributes));
    }

    private function createInquiry(Unit $unit, User $client, mixed $start, mixed $end, int $partySize = 1): Inquiry
    {
        return Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $unit->host_id,
            'desired_start_at' => $start,
            'desired_end_at' => $end,
            'party_size' => $partySize,
            'status' => 'open',
        ]);
    }
}
