<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_a_private_verification_profile(): void
    {
        Storage::fake('local');
        $user = User::factory()->incompleteProfile()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Verified Client',
            'phone' => '09171234567',
            'date_of_birth' => '1995-04-18',
            'nationality' => 'Filipino',
            'address' => '123 Booking Street',
            'country' => 'Philippines',
            'province' => 'Davao del Sur',
            'city' => 'Davao City',
            'barangay' => 'Buhangin',
            'bio' => 'I am a careful and responsible client who values clear communication.',
            'emergency_contact_name' => 'Maria Client',
            'emergency_contact_phone' => '09179876543',
            'government_id_type' => 'national_id',
            'government_id_number' => 'PH-1234-5678-9000',
            'government_id' => UploadedFile::fake()->image('national-id.jpg'),
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertTrue($user->hasCompleteProfile());
        $this->assertSame('+639171234567', $user->phone);
        Storage::disk('local')->assertExists($user->government_id_path);
        $this->assertStringNotContainsString('PH-1234-5678-9000', DB::table('users')->where('id', $user->id)->value('government_id_number'));
        $this->actingAs($user)->get(route('profiles.document', $user))->assertOk();
    }

    public function test_profile_requires_age_seventeen_but_not_mobile_otp_verification(): void
    {
        $user = User::factory()->create();
        $payload = [
            'name' => $user->name,
            'phone' => $user->phone,
            'date_of_birth' => today()->subYears(17)->addDay()->format('Y-m-d'),
            'nationality' => 'Filipino',
            'address' => $user->address,
            'country' => $user->country,
            'province' => $user->province,
            'city' => $user->city,
            'barangay' => $user->barangay,
            'bio' => $user->bio,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_phone' => $user->emergency_contact_phone,
            'government_id_type' => $user->government_id_type,
            'government_id_number' => $user->government_id_number,
        ];

        $this->actingAs($user)->put(route('profile.update'), $payload)->assertSessionHasErrors('date_of_birth');

        $payload['date_of_birth'] = today()->subYears(17)->format('Y-m-d');
        $this->put(route('profile.update'), $payload)
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();
    }

    public function test_verification_form_has_searchable_profile_choices_and_embedded_id_preview(): void
    {
        $user = User::factory()->create(['government_id_path' => 'identity-documents/testing/current-id.jpg']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('class="searchable-combobox"', false)
            ->assertSee('data-country-input', false)
            ->assertSee('data-province-input', false)
            ->assertSee('data-city-input', false)
            ->assertSee('data-barangay-input', false)
            ->assertSee('data-id-preview-image', false)
            ->assertDontSee('Send OTP')
            ->assertDontSee('data-send-phone-code', false)
            ->assertSee('max="'.today()->subYears(17)->format('Y-m-d').'"', false);
    }

    public function test_profile_location_choices_unwrap_and_filter_geographic_api_results(): void
    {
        Http::fake([
            'psgc.cloud/api/v2/provinces' => Http::response(['data' => [
                ['code' => '1102300000', 'name' => 'Davao del Sur'],
            ]]),
            'psgc.cloud/api/v2/regions/1300000000/cities-municipalities' => Http::response(['data' => [
                ['code' => '1380600000', 'name' => 'City of Manila', 'type' => 'City'],
                ['code' => '1380608000', 'name' => 'Ermita', 'type' => 'SubMun'],
            ]]),
            'psgc.cloud/api/v2/cities-municipalities/1380600000/barangays' => Http::response(['data' => []]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('profile.locations.provinces'))
            ->assertOk()->assertJsonFragment(['name' => 'Davao del Sur'])->assertJsonFragment(['name' => 'Metro Manila']);
        $this->getJson(route('profile.locations.cities', ['province_code' => '1300000000']))
            ->assertOk()->assertJsonFragment(['name' => 'City of Manila'])->assertJsonMissing(['name' => 'Ermita']);
        $this->getJson(route('profile.locations.barangays', ['city_code' => '1380600000']))
            ->assertOk()->assertJsonFragment(['name' => 'Ermita']);
    }

    public function test_profile_location_choices_do_not_return_server_errors_when_the_provider_is_unavailable(): void
    {
        Http::fake([
            'psgc.cloud/*' => Http::response(['message' => 'Server Error'], 500),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('profile.locations.barangays', ['city_code' => '1124020000']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_incomplete_profiles_cannot_inquire_or_register_listings(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->incompleteProfile()->create();
        $incompleteHost = User::factory()->host()->incompleteProfile()->create();
        $unit = $this->unit($host);
        $start = now()->addDays(2)->startOfHour();

        $this->actingAs($client)->post(route('inquiries.store'), [
            'unit_id' => $unit->id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(2),
            'party_size' => 2,
            'initial_message' => 'Is this available for our trip?',
        ])->assertRedirect(route('profile.edit'));

        $this->actingAs($incompleteHost)->get(route('units.create'))->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_profile_badge_warns_incomplete_users_about_verification_limits(): void
    {
        $incompleteClient = User::factory()->incompleteProfile()->create();
        $completeClient = User::factory()->create();

        $this->actingAs($incompleteClient)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('profile-verification-warning', false)
            ->assertSee('complete your profile before you can inquire or request a booking');

        $this->actingAs($completeClient)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('profile-verification-warning', false);
    }

    public function test_inquiry_opens_chat_and_participants_can_validate_profiles(): void
    {
        Storage::fake('local');
        $host = User::factory()->host()->create();
        $client = User::factory()->create([
            'government_id_path' => UploadedFile::fake()->image('client-id.jpg')->store('identity-documents/'.$host->id, 'local'),
        ]);
        $stranger = User::factory()->create();
        $unit = $this->unit($host);
        $start = now()->addDays(3)->startOfHour();

        $response = $this->actingAs($client)->post(route('inquiries.store'), [
            'unit_id' => $unit->id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(3),
            'party_size' => 2,
            'initial_message' => 'Hello, may I confirm the pickup details before booking?',
        ]);

        $inquiry = Inquiry::firstOrFail();
        $response->assertRedirect(route('inquiries.show', $inquiry));
        $this->assertDatabaseHas('inquiry_messages', ['inquiry_id' => $inquiry->id, 'sender_id' => $client->id]);

        $this->actingAs($host)->post(route('inquiries.messages.store', $inquiry), [
            'message' => 'Yes, I can confirm the pickup point here.',
        ])->assertRedirect();
        $this->actingAs($host)->get(route('profiles.show', $client))->assertOk()->assertSee($client->phone);
        $this->actingAs($client)->get(route('profiles.show', $host))->assertOk()->assertSee($host->phone);
        $this->actingAs($stranger)->get(route('profiles.show', $client))->assertForbidden();
        $this->actingAs($stranger)->get(route('inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_booking_request_requires_an_inquiry_for_the_same_client_and_unit(): void
    {
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $unit = $this->unit($host);
        $start = now()->addDays(2)->startOfHour();

        $this->actingAs($client)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'party_size' => 2,
        ])->assertSessionHasErrors('inquiry_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_chat_updates_expose_typing_presence_and_new_messages_only_to_participants(): void
    {
        config()->set('cache.default', 'array');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $unit = $this->unit($host);
        $inquiry = Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $host->id,
            'desired_start_at' => now()->addDays(2),
            'desired_end_at' => now()->addDays(2)->addHours(2),
            'party_size' => 2,
            'status' => 'open',
        ]);
        $message = $inquiry->messages()->create(['sender_id' => $host->id, 'body' => 'I am available for those dates 😊']);

        $this->actingAs($host)->postJson(route('inquiries.typing', $inquiry), ['is_typing' => true])->assertOk();
        $this->actingAs($client)->getJson(route('inquiries.messages.index', ['inquiry' => $inquiry, 'after_id' => 0]))
            ->assertOk()
            ->assertJsonPath('typing', true)
            ->assertJsonPath('typing_text', $host->name.' is typing…')
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.body', 'I am available for those dates 😊');

        $this->actingAs($stranger)->getJson(route('inquiries.messages.index', $inquiry))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('inquiries.typing', $inquiry), ['is_typing' => true])->assertForbidden();
    }

    public function test_private_image_attachments_unlock_only_after_booking_confirmation(): void
    {
        Storage::fake('local');
        $host = User::factory()->host()->create();
        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $unit = $this->unit($host);
        $start = now()->addDays(3)->startOfHour();
        $inquiry = Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $host->id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(2),
            'party_size' => 2,
            'status' => 'booking_requested',
        ]);

        $this->actingAs($client)->post(route('inquiries.messages.store', $inquiry), [
            'attachment' => UploadedFile::fake()->image('before-approval.jpg'),
        ])->assertSessionHasErrors('attachment');

        Booking::create([
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'party_size' => 2,
            'status' => 'confirmed',
            'total_amount' => 800,
        ]);

        $response = $this->actingAs($client)->postJson(route('inquiries.messages.store', $inquiry), [
            'message' => 'Here is the pickup reference photo 📍',
            'attachment' => UploadedFile::fake()->image('pickup.jpg', 800, 600),
        ]);

        $response->assertOk()->assertJsonPath('message.attachment_name', 'pickup.jpg');
        $message = $inquiry->messages()->latest()->firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->actingAs($host)->get(route('inquiries.attachments.show', $message))->assertOk();
        $this->actingAs($stranger)->get(route('inquiries.attachments.show', $message))->assertForbidden();
    }

    public function test_upcoming_booking_opens_its_details_and_specific_inquiry_chat(): void
    {
        $host = User::factory()->host()->create(['name' => 'Detail Host']);
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $unit = $this->unit($host);
        $unit->images()->createMany([
            ['path' => 'listings/detail-one.jpg', 'sort_order' => 0],
            ['path' => 'listings/detail-two.jpg', 'sort_order' => 1],
        ]);
        $start = now()->addDays(4)->startOfHour();
        $inquiry = Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $client->id,
            'host_id' => $host->id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(3),
            'party_size' => 3,
            'status' => 'confirmed',
        ]);
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'client_id' => $client->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(3),
            'party_size' => 3,
            'status' => 'confirmed',
            'total_amount' => 2400,
            'notes' => 'Pickup at the hotel lobby.',
        ]);

        $this->actingAs($client)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee(route('bookings.show', $booking));

        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking approved')
            ->assertSee($unit->name)
            ->assertSee('3 people')
            ->assertSee('Pickup at the hotel lobby.')
            ->assertSee(route('inquiries.show', $inquiry))
            ->assertSee('Go to inquiry chat')
            ->assertSee('View all 2 photos')
            ->assertSee('data-booking-gallery-dialog', false)
            ->assertSee('<details class="client-unit-rules booking-detail-rules">', false);

        $this->actingAs($client)->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Back to booking details')
            ->assertSee('View booking details')
            ->assertSee(route('bookings.show', $booking));

        $this->actingAs($host)->get(route('bookings.show', $booking))->assertOk()->assertSee('View client profile');
        $this->actingAs($otherClient)->get(route('bookings.show', $booking))->assertForbidden();
    }

    private function unit(User $host): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => 'Verified Driving Service',
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Davao City',
            'rules' => 'Please arrive on time and communicate route changes.',
            'capacity' => 4,
            'price' => 800,
            'pricing_unit' => 'session',
            'is_active' => true,
        ]);
    }
}
