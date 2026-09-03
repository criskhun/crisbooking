<?php

namespace Tests\Feature;

use App\Mail\UnseenNotificationMail;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\NativePushSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
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
        $this->assertNotNull($notification->fresh()->seen_at);

        $this->actingAs($other)->patchJson(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($user)->patchJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJson(['read' => true, 'url' => route('inquiries.index')]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_unread_inquiry_messages_appear_in_the_sidebar_and_live_notification_payload(): void
    {
        $host = User::factory()->host()->create();
        $customer = User::factory()->create();
        $inquiry = $this->inquiry($this->unit($host), $customer, now()->addDays(2));

        $this->actingAs($host)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-inquiry-attention-count', false)
            ->assertSee('Inquiries need your attention');

        $this->actingAs($host)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('inquiry_attention_count', 1);

        $this->actingAs($host)->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('data-live-inquiry-list', false);

        $this->actingAs($host)->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('data-live-inquiry-context', false);

        $this->actingAs($host)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('inquiry_attention_count', 0);
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
            ->assertSee('Enable mobile notifications')
            ->assertSee(route('native-push-subscriptions.store'), false)
            ->assertSee(route('native-push-subscriptions.destroy'), false);
    }

    public function test_user_can_register_reassign_and_remove_an_android_push_subscription(): void
    {
        config()->set('services.firebase.project_id', 'davao-rent-zone-test');
        config()->set('services.firebase.credentials', __FILE__);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $token = str_repeat('native-device-token-', 12);
        $tokenHash = hash('sha256', $token);

        $this->actingAs($firstUser)->postJson(route('native-push-subscriptions.store'), [
            'token' => $token,
            'platform' => 'android',
            'device_name' => 'Pixel test device',
        ])->assertOk()->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('native_push_subscriptions', [
            'user_id' => $firstUser->id,
            'token_hash' => $tokenHash,
            'platform' => 'android',
        ]);

        $this->actingAs($secondUser)->postJson(route('native-push-subscriptions.store'), [
            'token' => $token,
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseCount('native_push_subscriptions', 1);
        $subscription = NativePushSubscription::firstOrFail();
        $this->assertSame($secondUser->id, $subscription->user_id);
        $this->assertSame($token, $subscription->token);

        $this->actingAs($firstUser)->deleteJson(route('native-push-subscriptions.destroy'), [
            'token' => $token,
        ])->assertOk();
        $this->assertDatabaseCount('native_push_subscriptions', 1);

        $this->actingAs($secondUser)->deleteJson(route('native-push-subscriptions.destroy'), [
            'token' => $token,
        ])->assertOk()->assertJson(['subscribed' => false]);
        $this->assertDatabaseCount('native_push_subscriptions', 0);
    }

    public function test_native_subscription_requires_server_side_firebase_configuration(): void
    {
        config()->set('services.firebase.project_id', null);
        config()->set('services.firebase.credentials', null);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('native-push-subscriptions.store'), [
            'token' => str_repeat('unconfigured-token-', 12),
            'platform' => 'android',
        ])->assertServiceUnavailable()
            ->assertJsonPath('message', 'Native push delivery is not configured on the server.');
    }

    public function test_new_application_notification_is_forwarded_to_native_push_delivery(): void
    {
        $user = User::factory()->create();
        $firebase = Mockery::mock(FirebaseCloudMessaging::class);
        $firebase->shouldReceive('send')
            ->once()
            ->withArgs(fn (User $recipient, $notification) => $recipient->is($user)
                && $notification->title === 'Native delivery test');
        $this->app->instance(FirebaseCloudMessaging::class, $firebase);

        app(AppNotificationService::class)->send(
            $user,
            'system',
            'Native delivery test',
            'This notification should be forwarded to FCM.',
            route('dashboard'),
        );

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'title' => 'Native delivery test',
        ]);
    }

    public function test_firebase_delivery_uses_the_device_token_and_notification_destination(): void
    {
        config()->set('services.firebase.project_id', 'davao-rent-zone-test');
        config()->set('services.firebase.credentials', __FILE__);
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
        ]);
        $user = User::factory()->create();
        $token = str_repeat('fcm-test-token-', 12);
        $user->nativePushSubscriptions()->create([
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'platform' => 'android',
        ]);
        $notification = $user->appNotifications()->create([
            'type' => 'booking_request',
            'title' => 'New booking request',
            'body' => 'A customer requested your unit.',
            'url' => route('bookings.show', 123),
        ]);
        $firebase = new class extends FirebaseCloudMessaging
        {
            protected function accessToken(): string
            {
                return 'test-access-token';
            }
        };

        $firebase->send($user, $notification);

        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/davao-rent-zone-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $request['message']['token'] === $token
            && $request['message']['data']['url'] === route('bookings.show', 123)
            && $request['message']['android']['notification']['channel_id'] === 'davao_rent_zone_updates');
    }

    public function test_unseen_notification_is_emailed_after_the_fallback_delay_only_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $notification = app(AppNotificationService::class)->send(
            $user,
            'chat_message',
            'New message',
            'A host replied to your inquiry.',
            route('inquiries.index'),
        );

        Mail::assertNothingSent();

        $this->travel(6)->minutes();
        $this->artisan('notifications:send-email-fallback')->assertSuccessful();

        Mail::assertSent(UnseenNotificationMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertNotNull($notification->fresh()->email_sent_at);

        $this->artisan('notifications:send-email-fallback')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_notification_is_not_emailed_when_the_user_opens_the_system(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $notification = app(AppNotificationService::class)->send(
            $user,
            'chat_message',
            'New message',
            'A host replied to your inquiry.',
            route('inquiries.index'),
        );

        $this->actingAs($user)->getJson(route('notifications.index'))->assertOk();
        $this->assertNotNull($notification->fresh()->seen_at);

        $this->travel(6)->minutes();
        $this->artisan('notifications:send-email-fallback')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_booking_and_review_reminders_are_generated_once_for_each_party(): void
    {
        $host = User::factory()->host()->create();
        $customer = User::factory()->create();
        $unit = $this->unit($host);

        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $customer->id,
            'start_at' => now()->addHours(12),
            'end_at' => now()->addHours(14),
            'status' => 'confirmed',
            'total_amount' => 500,
        ]);
        Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $customer->id,
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHour(),
            'status' => 'confirmed',
            'total_amount' => 500,
        ]);

        $this->artisan('notifications:generate-reminders')->assertSuccessful();
        $this->artisan('notifications:generate-reminders')->assertSuccessful();

        $this->assertDatabaseCount('user_notifications', 4);
        $this->assertSame(2, $host->appNotifications()->count());
        $this->assertSame(2, $customer->appNotifications()->count());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'booking_reminder']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $customer->id, 'type' => 'review_reminder']);
    }

    public function test_outside_booking_balance_and_arrival_departure_reminders_notify_the_host(): void
    {
        Mail::fake();
        $host = User::factory()->host()->create();
        $unit = $this->unit($host);
        $arrival = Booking::create([
            'unit_id' => $unit->id, 'client_id' => $host->id, 'booking_origin' => 'manual',
            'external_customer_name' => 'Outside Guest', 'start_at' => now()->addMinutes(30),
            'end_at' => now()->addHours(3), 'status' => 'confirmed', 'total_amount' => 2500,
        ]);
        $departure = Booking::create([
            'unit_id' => $unit->id, 'client_id' => $host->id, 'booking_origin' => 'manual',
            'external_customer_name' => 'Returning Guest', 'start_at' => now()->subHour(),
            'end_at' => now()->addMinutes(30), 'status' => 'confirmed', 'total_amount' => 1000,
        ]);

        $this->artisan('notifications:generate-reminders')->assertSuccessful();
        $this->artisan('notifications:generate-reminders')->assertSuccessful();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'booking_reminder', 'dedupe_key' => "booking:{$arrival->id}:start-reminder:host"]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'balance_collection_reminder']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $host->id, 'type' => 'checkout_reminder', 'dedupe_key' => "booking:{$departure->id}:checkout-reminder:host"]);
        Mail::assertSent(UnseenNotificationMail::class, fn ($mail) => $mail->hasTo($host->email));
    }

    public function test_authenticated_web_request_updates_user_activity(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subHour()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertTrue($user->fresh()->last_seen_at->gt(now()->subMinute()));
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
