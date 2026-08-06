<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_requires_configuration(): void
    {
        config()->set('services.google.client_id');
        config()->set('services.google.client_secret');

        $this->get('/auth/google')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('google');
    }

    public function test_google_redirect_starts_the_oauth_flow(): void
    {
        config()->set('services.google.client_id', 'client-id');
        config()->set('services.google.client_secret', 'client-secret');
        Socialite::fake('google');

        $this->get('/auth/google')->assertRedirect();
    }

    public function test_google_callback_registers_and_authenticates_a_new_user(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Google Owner',
            'email' => 'owner@gmail.com',
            'avatar' => 'https://example.com/owner.jpg',
            'email_verified' => true,
        ]));

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Google Owner',
            'email' => 'owner@gmail.com',
            'google_id' => 'google-123',
            'google_avatar' => 'https://example.com/owner.jpg',
        ]);
        $this->assertNotNull(User::firstOrFail()->email_verified_at);
    }

    public function test_google_callback_links_an_existing_email_account(): void
    {
        $existingUser = User::factory()->unverified()->create([
            'email' => 'owner@gmail.com',
            'google_id' => null,
        ]);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-456',
            'email' => 'owner@gmail.com',
            'email_verified' => true,
        ]));

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($existingUser);
        $this->assertSame(1, User::count());
        $this->assertSame('google-456', $existingUser->fresh()->google_id);
        $this->assertNotNull($existingUser->fresh()->email_verified_at);
    }

    public function test_google_callback_rejects_an_unverified_email(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-789',
            'email' => 'unverified@gmail.com',
            'email_verified' => false,
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
