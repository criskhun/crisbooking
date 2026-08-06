<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class FacebookAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_redirect_requires_configuration(): void
    {
        config()->set('services.facebook.client_id');
        config()->set('services.facebook.client_secret');

        $this->get('/auth/facebook')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('facebook');
    }

    public function test_facebook_redirect_starts_the_oauth_flow(): void
    {
        config()->set('services.facebook.client_id', 'app-id');
        config()->set('services.facebook.client_secret', 'app-secret');
        Socialite::fake('facebook');

        $this->get('/auth/facebook')->assertRedirect();
    }

    public function test_facebook_callback_registers_and_authenticates_a_new_user(): void
    {
        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-123',
            'name' => 'Facebook Owner',
            'email' => 'facebook@example.com',
            'avatar' => 'https://example.com/facebook-owner.jpg',
            'verified' => true,
        ]));

        $this->get('/auth/facebook/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Facebook Owner',
            'email' => 'facebook@example.com',
            'facebook_id' => 'facebook-123',
            'facebook_avatar' => 'https://example.com/facebook-owner.jpg',
        ]);
    }

    public function test_an_existing_facebook_user_can_log_in_again(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'facebook@example.com',
            'facebook_id' => 'facebook-456',
        ]);

        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-456',
            'email' => 'facebook@example.com',
            'avatar' => 'https://example.com/new-avatar.jpg',
            'verified' => true,
        ]));

        $this->get('/auth/facebook/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($existingUser);
        $this->assertSame(1, User::count());
        $this->assertSame('https://example.com/new-avatar.jpg', $existingUser->fresh()->facebook_avatar);
    }

    public function test_facebook_does_not_automatically_link_an_existing_email_account(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-789',
            'email' => 'existing@example.com',
            'verified' => true,
        ]));

        $this->get('/auth/facebook/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('facebook');

        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertNull(User::firstOrFail()->facebook_id);
    }

    public function test_facebook_callback_requires_a_verified_email(): void
    {
        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => 'facebook-999',
            'email' => 'unverified@example.com',
            'verified' => false,
        ]));

        $this->get('/auth/facebook/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('facebook');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
