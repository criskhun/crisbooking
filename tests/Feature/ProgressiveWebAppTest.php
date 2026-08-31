<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProgressiveWebAppTest extends TestCase
{
    public function test_public_pages_include_progressive_web_app_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('data-service-worker', false)
            ->assertSee('mobile-shell-v5.css', false)
            ->assertSee('mobile-menu-v6.css', false)
            ->assertSee('mobile-form-v7.css', false)
            ->assertSee('profile-controls-v8.css', false)
            ->assertSee('address-combobox-v9.css', false)
            ->assertSee('form-controls-v19.css', false)
            ->assertSee('address-combobox-v9.js', false)
            ->assertSee('mobile-shell-v5.js', false)
            ->assertSee('data-pwa-install-banner', false);
    }

    public function test_manifest_and_required_install_assets_are_valid(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Davao Rent Zone', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('./', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);

        foreach (['sw.js', 'offline.html', 'js/pwa.js', 'css/mobile-shell-v5.css', 'css/mobile-menu-v6.css', 'css/mobile-form-v7.css', 'css/profile-controls-v8.css', 'css/address-combobox-v9.css', 'css/form-controls-v19.css', 'js/address-combobox-v9.js', 'js/mobile-shell-v5.js', 'vendor/fontawesome/css/fontawesome.min.css', 'vendor/fontawesome/css/solid.min.css', 'vendor/fontawesome/webfonts/fa-solid-900.woff2', 'vendor/fontawesome/LICENSE.txt', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-192.png', 'icons/icon-maskable-512.png'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }

        $this->assertSame([192, 192], array_slice(getimagesize(public_path('icons/icon-192.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('icons/icon-512.png')), 0, 2));
    }

    public function test_connectivity_banner_rechecks_restored_pages_and_visible_tabs(): void
    {
        $script = file_get_contents(public_path('js/pwa.js'));

        $this->assertStringContainsString("window.addEventListener('pageshow', syncConnectivity)", $script);
        $this->assertStringContainsString("document.visibilityState === 'visible'", $script);
        $this->assertStringContainsString('connectivityStatus.classList.remove(\'is-offline\')', $script);
    }
}
