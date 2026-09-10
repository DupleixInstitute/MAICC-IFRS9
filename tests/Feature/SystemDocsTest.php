<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticket #011: the Technical Manual and the Installation Guide are rendered
 * from repository Markdown (docs/manuals) in-app and to PDF, and the
 * Technical Manual carries a live schema appendix.
 */
class SystemDocsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_technical_manual_renders_chapters_with_anchored_sections_and_live_schema()
    {
        $response = $this->actingAs($this->user())->get(route('docs.technical'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('technical', $props['doc']);
        $this->assertNotEmpty($props['chapters'], 'docs/manuals/technical has no chapters.');
        $first = $props['chapters'][0];
        $this->assertStringContainsString('<h2 id="' . $first['slug'] . '">', $first['html']);
        $this->assertNotEmpty($props['schema'], 'Live schema appendix is empty.');
        $tables = collect($props['schema'])->pluck('name');
        $this->assertTrue($tables->contains('loan_books'));
        $this->assertTrue($tables->contains('help_categories'));
    }

    public function test_installation_guide_renders_without_schema_appendix()
    {
        $response = $this->actingAs($this->user())->get(route('docs.installation'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('installation', $props['doc']);
        $this->assertNotEmpty($props['chapters'], 'docs/manuals/installation has no chapters.');
        $this->assertSame([], $props['schema']);
    }

    public function test_chapter_order_follows_file_names()
    {
        $props = $this->actingAs($this->user())->get(route('docs.technical'))->viewData('page')['props'];
        $slugs = collect($props['chapters'])->pluck('slug')->all();
        $sorted = $slugs;
        sort($sorted);
        $this->assertSame($sorted, $slugs);
    }

    public function test_both_documents_export_to_pdf()
    {
        foreach (['docs.technical.pdf', 'docs.installation.pdf'] as $name) {
            $response = $this->actingAs($this->user())->get(route($name));
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), $name);
        }
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('docs.technical'))->assertRedirect('/login');
        $this->get(route('docs.installation'))->assertRedirect('/login');
        $this->get(route('docs.technical.pdf'))->assertRedirect('/login');
    }

    public function test_documentation_menu_lists_all_four_deliverables()
    {
        $group = collect(config('menu.admin'))->firstWhere('name', 'System Documentation');
        $routes = collect($group['children'])->pluck('route')->all();

        $this->assertSame(['help.index', 'help.admin', 'docs.technical', 'docs.installation'], $routes);
    }
}
