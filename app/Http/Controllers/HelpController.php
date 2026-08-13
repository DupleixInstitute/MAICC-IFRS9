<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;
use App\Models\HelpCategory;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

/**
 * DB-driven user manual reader (Ticket #010). The same rows feed this
 * page, the per-page help lookup and the PDF export: one source of truth.
 */
class HelpController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return Inertia::render('Help/Index', [
            'company' => $this->company(),
            'categories' => $this->publishedTree(),
            'canManage' => optional(auth()->user())->can('settings') ?? false,
        ]);
    }

    /**
     * Per-page help lookup: which article documents this route?
     * Returns 404 when no mapping exists; the caller hides its button.
     */
    public function forRoute(string $routeName)
    {
        $map = HelpArticleRoute::where('route_name', $routeName)
            ->whereHas('article', fn ($q) => $q->where('status', 'published'))
            ->first();

        abort_unless($map, 404);

        $article = HelpArticle::find($map->help_article_id);

        return response()->json(['slug' => $article->slug, 'title' => $article->title]);
    }

    /**
     * The full manual as a branded PDF, rendered from the same DB rows
     * (figures included via their local file paths for DomPDF).
     */
    public function pdf()
    {
        return Pdf::loadView('manual.help', [
            'company' => $this->company(),
            'generated_at' => now()->format('d M Y'),
            'categories' => $this->publishedTree(),
        ])->setPaper('a4', 'portrait')
          ->download('IFRS9-User-Manual.pdf');
    }

    private function publishedTree()
    {
        $figureNo = 0;

        return HelpCategory::orderBy('order')
            ->with(['articles' => fn ($q) => $q->where('status', 'published')->with(['steps', 'images'])])
            ->get()
            ->filter(fn ($c) => $c->articles->isNotEmpty())
            ->map(function ($c) use (&$figureNo) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'articles' => $c->articles->map(function ($a) use (&$figureNo) {
                        return [
                            'id' => $a->id,
                            'title' => $a->title,
                            'slug' => $a->slug,
                            'body' => $a->body,
                            'updated_at' => optional($a->updated_at)->format('d M Y'),
                            'steps' => $a->steps->map(fn ($s) => $s->text)->values(),
                            'images' => $a->images->map(function ($i) use (&$figureNo) {
                                $figureNo++;

                                return [
                                    'src' => str_starts_with($i->path, 'http') ? $i->path : asset(ltrim($i->path, '/')),
                                    'file' => public_path(ltrim($i->path, '/')),
                                    'caption' => 'Figure ' . $figureNo . '. ' . ($i->caption ?: ''),
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })->values();
    }

    private function company(): string
    {
        $company = config('app.name', 'MAIIC');

        try {
            $company = optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: $company;
        } catch (\Throwable $e) {
            // settings table may be unavailable in some environments
        }

        return $company;
    }
}
