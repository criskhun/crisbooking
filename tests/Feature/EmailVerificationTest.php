<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'booking123',
            'password_confirmation' => 'booking123',
        ])->assertRedirect('/email/verify');

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/email/verify');
    }

    public function test_user_can_verify_email_with_a_valid_signed_link(): void
    {
        Event::fake();
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/dashboard');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_user_can_resend_the_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
