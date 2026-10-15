<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Staging Comparison screen (Staging Module Specification v1, section 4) opens with no run yet, and an unknown run has no export. */
class StagingComparisonScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_opens_and_an_unknown_run_is_not_found(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/staging-comparison');
        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('StageingRules/Comparison', $response->viewData('page')['component']);
        $this->assertNull($props['run']);
        $this->assertSame(['Single-ladder staging', 'Directive-aligned staging', 'Custom'], $props['presets']);
        $this->assertArrayHasKey('staging_threshold_profile', $props['settings']);

        $this->actingAs($user)->get('/staging-comparison/999/export')->assertNotFound();
    }
}
