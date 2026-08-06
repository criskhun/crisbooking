<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

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
