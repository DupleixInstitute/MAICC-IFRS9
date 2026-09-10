<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticket #010: DB-driven user manual: reader, per-page lookup, PDF from the
 * same rows, and permission-gated authoring.
 */
class HelpManualTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function seedArticle(string $status = 'published'): HelpArticle
    {
        $category = HelpCategory::create(['title' => 'Test Chapter', 'slug' => 'test-chapter', 'order' => 1]);
        $article = HelpArticle::create([
            'help_category_id' => $category->id,
            'title' => 'Running the ECL calculation',
            'slug' => 'running-the-ecl-calculation',
            'body' => '<p>How to run ECL.</p>',
            'order' => 1,
            'status' => $status,
        ]);
        $article->steps()->create(['step_no' => 1, 'text' => 'Open the ECL screen.']);
        $article->routes()->create(['route_name' => 'testing.help.fake-route']);

        return $article;
    }

    /** The seeded manual is also present, so assertions search rather than
     *  rely on positions. */
    public function test_reader_shows_published_content()
    {
        $this->seedArticle();

        $response = $this->actingAs($this->admin())->get(route('help.index'));

        $response->assertOk();
        $articles = collect($response->viewData('page')['props']['categories'])
            ->flatMap(fn ($c) => collect($c['articles']));
        $mine = $articles->firstWhere('slug', 'running-the-ecl-calculation');
        $this->assertNotNull($mine, 'Seeded test article missing from the reader.');
        $this->assertSame('Open the ECL screen.', $mine['steps'][0]);
    }

    public function test_reader_hides_draft_articles()
    {
        $this->seedArticle('draft');

        $response = $this->actingAs($this->admin())->get(route('help.index'));

        $response->assertOk();
        $slugs = collect($response->viewData('page')['props']['categories'])
            ->flatMap(fn ($c) => collect($c['articles'])->pluck('slug'));
        $this->assertFalse($slugs->contains('running-the-ecl-calculation'), 'Draft article leaked into the reader.');
    }

    public function test_for_route_lookup_finds_the_mapped_article()
    {
        $this->seedArticle();

        $this->actingAs($this->admin())
            ->get(route('help.for-route', 'testing.help.fake-route'))
            ->assertOk()
            ->assertJson(['slug' => 'running-the-ecl-calculation']);

        $this->actingAs($this->admin())
            ->get(route('help.for-route', 'no.such.route'))
            ->assertNotFound();
    }

    public function test_pdf_renders_from_the_same_rows()
    {
        $this->seedArticle();

        $response = $this->actingAs($this->admin())->get(route('help.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_old_manual_url_redirects_to_the_reader()
    {
        $this->actingAs($this->admin())
            ->get('/manual')
            ->assertRedirect(route('help.index'));
    }

    public function test_authoring_creates_article_with_steps_and_routes()
    {
        $admin = $this->admin();
        $category = HelpCategory::create(['title' => 'Chapter', 'slug' => 'chapter', 'order' => 1]);

        $this->actingAs($admin)->post(route('help.manage.articles.store'), [
            'help_category_id' => $category->id,
            'title' => 'New article',
            'body' => '<p>Body</p>',
            'status' => 'published',
            'steps' => ['First step', 'Second step'],
            'routes' => ['dashboard'],
        ])->assertRedirect();

        $article = HelpArticle::where('title', 'New article')->first();
        $this->assertNotNull($article);
        $this->assertSame(2, $article->steps()->count());
        $this->assertSame('dashboard', $article->routes()->first()->route_name);
    }

    /** Ticket #011: the Administrator Manual is a second manual in the same tables. */
    public function test_admin_manual_reader_shows_only_admin_chapters_and_exports_pdf()
    {
        $this->seedArticle();
        $adminChapter = HelpCategory::create(['manual' => 'admin', 'title' => 'Admin Chapter', 'slug' => 'admin-chapter-test', 'order' => 99]);
        HelpArticle::create([
            'help_category_id' => $adminChapter->id,
            'title' => 'Closing a financial period',
            'slug' => 'closing-a-financial-period-test',
            'body' => '<p>Admin only.</p>',
            'order' => 1,
            'status' => 'published',
        ]);

        $admin = $this->actingAs($this->admin())->get(route('help.admin'));
        $admin->assertOk();
        $props = $admin->viewData('page')['props'];
        $this->assertSame('admin', $props['manual']);
        $this->assertSame('Administrator Manual', $props['title']);
        $slugs = collect($props['categories'])->flatMap(fn ($c) => collect($c['articles'])->pluck('slug'));
        $this->assertTrue($slugs->contains('closing-a-financial-period-test'));
        $this->assertFalse($slugs->contains('running-the-ecl-calculation'), 'User Manual article leaked into the Administrator Manual.');

        $user = $this->actingAs($this->admin())->get(route('help.index'))->viewData('page')['props'];
        $userSlugs = collect($user['categories'])->flatMap(fn ($c) => collect($c['articles'])->pluck('slug'));
        $this->assertFalse($userSlugs->contains('closing-a-financial-period-test'), 'Administrator Manual article leaked into the User Manual.');

        $pdf = $this->actingAs($this->admin())->get(route('help.admin.pdf'));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_authoring_a_chapter_for_the_admin_manual_keeps_it_out_of_the_user_manual()
    {
        $this->actingAs($this->admin())->post(route('help.manage.categories.store'), [
            'title' => 'Housekeeping Test',
            'manual' => 'admin',
        ])->assertRedirect();

        $this->assertSame('admin', HelpCategory::where('title', 'Housekeeping Test')->value('manual'));
    }

    public function test_guests_cannot_read_and_cannot_author()
    {
        $this->get(route('help.index'))->assertRedirect('/login');
        $this->get(route('help.manage.index'))->assertRedirect('/login');
    }
}
