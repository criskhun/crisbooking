<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountListTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_list_requires_authentication(): void
    {
        $this->get('/accounts')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_registered_accounts(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Test Owner',
            'email' => 'owner@crisbooking.test',
            'is_admin' => true,
        ]);
        User::factory()->create([
            'name' => 'Google User',
            'email' => 'google@example.com',
            'google_id' => 'google-123',
        ]);

        $this->actingAs($viewer)
            ->get('/accounts')
            ->assertOk()
            ->assertSee('2 accounts')
            ->assertSee('owner@crisbooking.test')
            ->assertSee('google@example.com')
            ->assertSee('Google');
    }

    public function test_standard_user_cannot_view_registered_accounts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/accounts')->assertForbidden();
    }
}
