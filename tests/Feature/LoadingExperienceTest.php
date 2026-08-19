<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_includes_accessible_delayed_loading_feedback(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('is-booting', false)
            ->assertSee('data-global-loader', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Loading your workspace');
    }

    public function test_login_page_uses_the_same_platform_loading_experience(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-global-loader', false)
            ->assertSee('js/app.js', false);
    }
}
