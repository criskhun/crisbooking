<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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
        $this->assertNotNull(User::firstOrFail()->password_set_at);
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
        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in to your account')
            ->assertSee('fill="#4285F4"', false)
            ->assertSee('fill="#34A853"', false)
            ->assertSee('facebook-button', false)
            ->assertSee('class="password-visibility-toggle"', false)
            ->assertSee('data-password-reveal', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('fa-eye', false)
            ->assertDontSee('fa-solid fa-g"', false)
            ->assertDontSee('fa-solid fa-f"', false)
            ->assertSee(route('password.request'));
    }

    public function test_user_can_request_and_complete_a_password_reset_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recover@example.com']);
        $token = null;

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Set a new password');

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newbooking123',
            'password_confirmation' => 'newbooking123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newbooking123', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_set_at);
    }

    public function test_mail_transport_failure_returns_to_recovery_form_instead_of_a_500_error(): void
    {
        $user = User::factory()->create(['email' => 'mail-failure@example.com']);
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('SMTP authentication failed'));

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'We could not send the reset email right now. Please try again shortly or contact support.',
            ]);
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
