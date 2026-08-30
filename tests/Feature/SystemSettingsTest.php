<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_open_system_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('System settings')
            ->assertSee('Brand identity');

        $this->actingAs($user)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_update_branding_and_it_is_used_by_pages_and_manifest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->validSettings([
            'site_name' => 'Cris Booking Hub',
            'short_name' => 'CBH',
            'tagline' => 'Everything ready when you are',
            'primary_color' => '#123456',
            'secondary_color' => '#102030',
            'accent_color' => '#fedcba',
        ]))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('system_settings', [
            'site_name' => 'Cris Booking Hub',
            'short_name' => 'CBH',
            'updated_by' => $admin->id,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cris Booking Hub')
            ->assertSee('--forest: #123456', false);

        $this->get(route('app.manifest'))
            ->assertOk()
            ->assertJsonPath('name', 'Cris Booking Hub')
            ->assertJsonPath('short_name', 'CBH')
            ->assertJsonPath('theme_color', '#123456');
    }

    public function test_admin_can_upload_and_restore_brand_assets(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->validSettings([
            'logo' => UploadedFile::fake()->image('logo.png', 600, 300),
            'favicon' => UploadedFile::fake()->image('icon.png', 256, 256),
        ]))->assertSessionHasNoErrors();

        $settings = SystemSetting::query()->firstOrFail();
        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertExists($settings->favicon_path);
        $oldLogo = $settings->logo_path;
        $oldFavicon = $settings->favicon_path;

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->validSettings([
            'remove_logo' => '1',
            'remove_favicon' => '1',
        ]))->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertNull($settings->logo_path);
        $this->assertNull($settings->favicon_path);
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertMissing($oldFavicon);
    }

    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'Davao Rent Zone',
            'short_name' => 'DRZ',
            'tagline' => 'Rentals and services in one place',
            'description' => 'A booking platform for local rentals and services.',
            'support_email' => 'support@example.com',
            'support_phone' => '+63 900 000 0000',
            'primary_color' => '#173c34',
            'secondary_color' => '#0f2d27',
            'accent_color' => '#d9ed8b',
        ], $overrides);
    }
}
