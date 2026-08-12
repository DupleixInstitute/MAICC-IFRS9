<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ifrs9ReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_report_page_renders()
    {
        $response = $this->actingAs($this->admin())->get('/ifrs9-reports/ecl');

        $response->assertOk();
    }

    public function test_report_downloads_as_pdf()
    {
        $response = $this->actingAs($this->admin())->get('/ifrs9-reports/ecl?download=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', $response->headers->get('content-disposition'));
    }

    public function test_report_downloads_as_excel()
    {
        $response = $this->actingAs($this->admin())->get('/ifrs9-reports/ecl?download=xlsx');

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_reports_require_permission()
    {
        // The factory assigns a random existing role, so strip everything
        // to get a genuinely permissionless user.
        $user = User::factory()->create();
        $user->syncRoles([]);
        $user->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->actingAs($user)->get('/ifrs9-reports/ecl');

        $response->assertForbidden();
    }
}
