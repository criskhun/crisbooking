<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_a_new_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Cris Uriarte',
            'email' => 'cris@example.com',
            'password' => 'booking123',
            'password_confirmation' => 'booking123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'cris@example.com']);
        $this->assertTrue(Hash::check('booking123', User::firstOrFail()->password));
        $this->assertNull(User::firstOrFail()->email_verified_at);
    }

    public function test_registration_requires_valid_and_unique_details(): void
    {
        User::factory()->create(['email' => 'used@example.com']);

        $this->post('/register', [
            'name' => '',
            'email' => 'used@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in to your account');
    }

    public function test_an_existing_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'booking123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'booking123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'booking123',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'booking123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_rejects_incorrect_credentials(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
