<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Manual authoring (Ticket #010): chapters and articles with steps, route
 * mappings and uploaded figures. Gated by the settings permission.
 */
class HelpManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index()
    {
        return Inertia::render('Help/Manage', [
            'categories' => HelpCategory::orderBy('order')
                ->with(['articles' => fn ($q) => $q->orderBy('order')->with(['steps', 'images', 'routes'])])
                ->get(),
            'routeNames' => $this->navigableRouteNames(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $v = $request->validate(['title' => 'required|string|max:150', 'order' => 'nullable|integer']);

        HelpCategory::create([
            'title' => $v['title'],
            'slug' => $this->uniqueSlug(HelpCategory::class, $v['title']),
            'order' => $v['order'] ?? (HelpCategory::max('order') + 1),
        ]);

        return back()->with('success', 'Chapter created.');
    }

    public function updateCategory(Request $request, HelpCategory $category)
    {
        $v = $request->validate(['title' => 'required|string|max:150', 'order' => 'nullable|integer']);
        $category->update(['title' => $v['title'], 'order' => $v['order'] ?? $category->order]);

        return back()->with('success', 'Chapter updated.');
    }

    public function destroyCategory(HelpCategory $category)
    {
        $category->delete();

        return back()->with('success', 'Chapter and its articles removed.');
    }

    public function storeArticle(Request $request)
    {
        $v = $this->validateArticle($request);

        $article = HelpArticle::create([
            'help_category_id' => $v['help_category_id'],
            'title' => $v['title'],
            'slug' => $this->uniqueSlug(HelpArticle::class, $v['title']),
            'body' => $v['body'] ?? '',
            'order' => $v['order'] ?? 0,
            'status' => $v['status'],
            'updated_by' => optional($request->user())->name,
        ]);
        $this->syncChildren($article, $v);

        return back()->with('success', 'Article created.');
    }

    public function updateArticle(Request $request, HelpArticle $article)
    {
        $v = $this->validateArticle($request);

        $article->update([
            'help_category_id' => $v['help_category_id'],
            'title' => $v['title'],
            'body' => $v['body'] ?? '',
            'order' => $v['order'] ?? $article->order,
            'status' => $v['status'],
            'updated_by' => optional($request->user())->name,
        ]);
        $this->syncChildren($article, $v);

        return back()->with('success', 'Article updated.');
    }

    public function destroyArticle(HelpArticle $article)
    {
        $article->delete();

        return back()->with('success', 'Article removed.');
    }

    /** Upload a figure for an article; stored on the public disk. */
    public function uploadImage(Request $request, HelpArticle $article)
    {
        $request->validate([
            'image' => 'required|image|max:4096',
            'caption' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('help', 'public');
        $article->images()->create([
            'path' => 'storage/' . $path,
            'caption' => $request->input('caption', ''),
            'order' => (int) $article->images()->max('order') + 1,
        ]);

        return back()->with('success', 'Figure uploaded.');
    }

    public function destroyImage(HelpArticle $article, int $imageId)
    {
        $article->images()->whereKey($imageId)->delete();

        return back()->with('success', 'Figure removed.');
    }

    private function validateArticle(Request $request): array
    {
        return $request->validate([
            'help_category_id' => 'required|exists:help_categories,id',
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:draft,published',
            'steps' => 'nullable|array',
            'steps.*' => 'string',
            'routes' => 'nullable|array',
            'routes.*' => 'string',
        ]);
    }

    private function syncChildren(HelpArticle $article, array $v): void
    {
        if (array_key_exists('steps', $v)) {
            $article->steps()->delete();
            foreach (array_values(array_filter($v['steps'] ?? [], fn ($s) => trim($s) !== '')) as $i => $text) {
                $article->steps()->create(['step_no' => $i + 1, 'text' => $text]);
            }
        }
        if (array_key_exists('routes', $v)) {
            $article->routes()->delete();
            foreach (array_unique(array_filter($v['routes'] ?? [])) as $name) {
                $article->routes()->create(['route_name' => $name]);
            }
        }
    }

    private function uniqueSlug(string $model, string $title): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        $n = 1;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    /** GET route names an article can map to (for the per-page help lookup). */
    private function navigableRouteNames(): array
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name && in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')
                && ! str_starts_with($name, 'ignition.')
                && ! str_starts_with($name, 'sanctum.')) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }
}
