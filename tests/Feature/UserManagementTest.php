<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_account_can_be_promoted_to_admin_from_the_console(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'is_admin' => false,
            'is_active' => false,
        ]);

        $this->artisan('user:make-admin', ['email' => 'OWNER@example.com'])
            ->expectsOutput('owner@example.com is now an active administrator.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_admin_promotion_command_fails_for_an_unknown_account(): void
    {
        $this->artisan('user:make-admin', ['email' => 'missing@example.com'])
            ->expectsOutput('No account exists for missing@example.com. Register or sign in first, then run this command again.')
            ->assertFailed();
    }

    public function test_admin_can_update_a_users_name_role_and_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->put(route('accounts.update', $user), [
            'name' => 'Managed User',
            'is_admin' => '1',
            'is_active' => '0',
        ])->assertRedirect('/accounts');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Managed User',
            'is_admin' => true,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'account_update',
        ]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $user))
            ->assertRedirect('/accounts');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_demote_suspend_or_delete_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('accounts.update', $admin), [
            'name' => $admin->name,
            'is_admin' => '0',
            'is_active' => '0',
        ])->assertSessionHasErrors('account');

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $admin))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_suspended_session_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
