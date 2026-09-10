<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;
use App\Models\HelpCategory;
use App\Models\Setting;
use App\Support\DocumentFrontMatter;
use App\Support\PdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

/**
 * DB-driven manuals (Tickets #010 and #011). The same rows feed the reader,
 * the per-page help lookup and the PDF export: one source of truth per
 * manual. Two manuals live in the help centre: the User Manual (analyst
 * workflow) and the Administrator Manual (configuration, access, periods,
 * data operations, support). The Technical Manual and Installation Guide
 * are repository documents rendered by SystemDocsController.
 */
class HelpController extends Controller
{
    /** Manual key => presentation. Keys are also the help_categories.manual values. */
    public const MANUALS = [
        'user' => [
            'title' => 'User Manual',
            'subtitle' => 'IFRS 9 Expected Credit Loss and Effective Interest Rate Platform',
            'file' => 'IFRS9-User-Manual.pdf',
        ],
        'admin' => [
            'title' => 'Administrator Manual',
            'subtitle' => 'Configuration, access control, periods, data operations and support',
            'file' => 'IFRS9-Administrator-Manual.pdf',
        ],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return $this->reader('user');
    }

    public function admin()
    {
        return $this->reader('admin');
    }

    private function reader(string $manual)
    {
        $meta = self::MANUALS[$manual];

        return Inertia::render('Help/Index', [
            'company' => $this->company(),
            'manual' => $manual,
            'title' => $meta['title'],
            'subtitle' => $meta['subtitle'],
            'pdfRoute' => $manual === 'admin' ? route('help.admin.pdf') : route('help.pdf'),
            'categories' => $this->publishedTree($manual),
            'canManage' => optional(auth()->user())->can('settings') ?? false,
        ]);
    }

    /**
     * Per-page help lookup: which article documents this route? Searches
     * both manuals. Returns 404 when no mapping exists; the caller hides its
     * button.
     */
    public function forRoute(string $routeName)
    {
        $map = HelpArticleRoute::where('route_name', $routeName)
            ->whereHas('article', fn ($q) => $q->where('status', 'published'))
            ->first();

        abort_unless($map, 404);

        $article = HelpArticle::with('category')->find($map->help_article_id);

        return response()->json([
            'slug' => $article->slug,
            'title' => $article->title,
            'manual' => $article->category->manual ?? 'user',
        ]);
    }

    /** The User Manual as a branded PDF, rendered from the same DB rows. */
    public function pdf()
    {
        return $this->renderPdf('user');
    }

    /** The Administrator Manual as a branded PDF. */
    public function adminPdf()
    {
        return $this->renderPdf('admin');
    }

    private function renderPdf(string $manual)
    {
        $meta = self::MANUALS[$manual];

        $company = $this->company();
        $generatedAt = now()->format('d F Y');

        $pdf = Pdf::loadView('manual.help', [
            'company' => $company,
            'title' => $meta['title'],
            'subtitle' => $meta['subtitle'],
            'generated_at' => $generatedAt,
            'front' => DocumentFrontMatter::for($manual, $company, $generatedAt),
            'categories' => $this->publishedTree($manual),
        ])->setPaper('a4', 'portrait');

        return PdfPageNumbers::stamp($pdf)->download($meta['file']);
    }

    private function publishedTree(string $manual)
    {
        $figureNo = 0;

        return HelpCategory::manual($manual)->orderBy('order')
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
