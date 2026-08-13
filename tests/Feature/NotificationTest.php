<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_request_appears_in_chat_and_notifies_host_with_chat_link(): void
    {
        $host = User::factory()->host()->create(['name' => 'Rental Host']);
        $customer = User::factory()->create(['name' => 'Booking Customer']);
        $unit = $this->unit($host);
        $start = now()->addDays(3)->startOfHour();
        $inquiry = $this->inquiry($unit, $customer, $start);

        $this->actingAs($customer)->post(route('bookings.store'), [
            'unit_id' => $unit->id,
            'inquiry_id' => $inquiry->id,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
            'party_size' => 1,
        ])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $host->id,
            'type' => 'booking_request',
            'title' => 'New booking request',
            'url' => route('inquiries.show', $inquiry),
        ]);

        $this->actingAs($host)->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Booking Customer requested this booking.')
            ->assertSee('View request')
            ->assertSee(route('bookings.show', $booking));
    }

    public function test_chat_message_creates_clickable_recipient_notification(): void
    {
        $host = User::factory()->host()->create();
        $customer = User::factory()->create();
        $unit = $this->unit($host);
        $inquiry = $this->inquiry($unit, $customer, now()->addDays(2));

        $this->actingAs($host)->post(route('inquiries.messages.store', $inquiry), [
            'message' => 'Your requested schedule is currently available.',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'type' => 'chat_message',
            'url' => route('inquiries.show', $inquiry),
        ]);
    }

    public function test_notification_inbox_is_private_and_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = $user->appNotifications()->create([
            'type' => 'inquiry',
            'title' => 'New inquiry',
            'body' => 'A host replied to your inquiry.',
            'url' => route('inquiries.index'),
        ]);

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notification->id);

        $this->actingAs($other)->patchJson(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($user)->patchJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJson(['read' => true, 'url' => route('inquiries.index')]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_register_a_device_push_subscription_and_header_has_bell(): void
    {
        config()->set('services.webpush.public_key', 'test-public-key');
        config()->set('services.webpush.private_key', 'test-private-key');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://push.example.test/subscription/123',
            'keys' => ['p256dh' => 'browser-public-key', 'auth' => 'browser-auth-token'],
            'content_encoding' => 'aes128gcm',
        ])->assertOk()->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/123'),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-notification-center', false)
            ->assertSee('Enable mobile notifications');
    }

    private function unit(User $host): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => 'Notification Test Service',
            'kind' => 'service',
            'category' => 'driving',
            'location' => 'Davao City',
            'rules' => 'Arrive at the agreed time.',
            'capacity' => 4,
            'price' => 500,
            'pricing_unit' => 'hour',
            'is_active' => true,
        ]);
    }

    private function inquiry(Unit $unit, User $customer, mixed $start): Inquiry
    {
        $inquiry = Inquiry::create([
            'unit_id' => $unit->id,
            'client_id' => $customer->id,
            'host_id' => $unit->host_id,
            'desired_start_at' => $start,
            'desired_end_at' => $start->copy()->addHours(2),
            'party_size' => 1,
            'status' => 'open',
        ]);
        $inquiry->messages()->create(['sender_id' => $customer->id, 'body' => 'Is this available for my schedule?']);

        return $inquiry;
    }
}
