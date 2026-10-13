<?php

namespace Tests\Feature\Pd;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The consolidated reports that carry the PD parts (ECL by segment: PD by
 * portfolio and PD by sector; RBM classification and provisioning: PD by
 * RBM class) render and download on an empty book, saying what is missing
 * rather than failing.
 */
class SegmentReportsRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_the_reports_with_the_pd_parts_render_and_download(): void
    {
        $admin = $this->admin();
        foreach (['sector-ecl' => ['PD by portfolio', 'PD by sector'], 'rbm-classification' => ['PD by RBM class']] as $report => $parts) {
            $response = $this->actingAs($admin)->get("/ifrs9-reports/{$report}");
            $response->assertOk();
            $titles = array_column($response->viewData('page')['props']['report']['parts'], 'title');
            foreach ($parts as $part) {
                $this->assertContains($part, $titles, $report);
            }
            $this->actingAs($admin)->get("/ifrs9-reports/{$report}?download=xlsx")->assertOk();
        }
    }
}
