<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_account_can_set_its_first_email_password_from_profile(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-profile-password-1',
            'password' => 'unknown-social-password',
            'password_set_at' => null,
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Set an email password')
            ->assertDontSee('name="current_password"', false);

        $this->put(route('profile.password.update'), [
            'password' => 'mybooking2026',
            'password_confirmation' => 'mybooking2026',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->password_set_at);
        $this->assertTrue(Hash::check('mybooking2026', $user->password));

        auth()->logout();
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'mybooking2026',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_changing_an_existing_email_password_requires_the_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'oldbooking2026',
            'password_set_at' => now(),
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Change email password')
            ->assertSee('name="current_password"', false);

        $this->put(route('profile.password.update'), [
            'password' => 'newbooking2026',
            'password_confirmation' => 'newbooking2026',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('profile.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'newbooking2026',
            'password_confirmation' => 'newbooking2026',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('profile.password.update'), [
            'current_password' => 'oldbooking2026',
            'password' => 'newbooking2026',
            'password_confirmation' => 'newbooking2026',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newbooking2026', $user->fresh()->password));
    }
}
