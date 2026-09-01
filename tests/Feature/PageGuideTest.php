<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_guide_explains_the_current_users_workspace_and_shared_tools(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Client dashboard guide')
            ->assertSee('Start a new booking')
            ->assertSee('Track your upcoming plans')
            ->assertSee('Search across the system')
            ->assertSee('See what needs attention')
            ->assertSee('Move around your workspace');

        $host = User::factory()->host()->create();

        $this->actingAs($host)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rental dashboard guide')
            ->assertSee('Use your rental control center')
            ->assertSee('Check every listing quickly');
    }

    public function test_host_calendar_guide_covers_outside_bookings_and_live_availability(): void
    {
        $host = User::factory()->host()->create();

        $this->actingAs($host)->get(route('calendar.index', ['mode' => 'manage']))
            ->assertOk()
            ->assertSee('Availability calendar guide')
            ->assertSee('Record an outside booking')
            ->assertSee('Review live availability')
            ->assertSee('Manage booking requests');
    }

    public function test_guide_hint_stays_above_the_spotlight_and_missing_targets_are_filtered(): void
    {
        $styles = file_get_contents(public_path('css/app.css'));
        $scripts = file_get_contents(public_path('js/app.js'));

        $this->assertStringContainsString('.page-guide { position: relative; z-index: 1000; }', $styles);
        $this->assertStringContainsString('.guide-demo-hint { position: fixed; z-index: 1002;', $styles);
        $this->assertStringContainsString('const findGuideTarget = (selector)', $scripts);
        $this->assertStringContainsString('guideSteps = guideSteps.filter((step) => findGuideTarget(step.selector));', $scripts);
    }
}
