<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\DocumentFrontMatter;
use App\Support\PdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Repository-authored system documentation (Ticket #011, contract Schedule 1
 * deliverables 6 and 7): the Technical Manual and the Installation and
 * Configuration Guide. Each document is a folder of Markdown chapters under
 * docs/manuals, versioned with the code so developers keep it current. The
 * same files feed the in-app reader and the branded PDF, and the Technical
 * Manual gains a live schema appendix pulled from the connected database so
 * the data dictionary never goes stale.
 */
class SystemDocsController extends Controller
{
    public const DOCS = [
        'technical' => [
            'title' => 'Technical Manual',
            'subtitle' => 'Architecture, data model, engines, security and operations',
            'dir' => 'docs/manuals/technical',
            'file' => 'IFRS9-Technical-Manual.pdf',
            'schema' => true,
            'deliverable' => 'Contract Schedule 1, deliverable 6 (Administrator / Technical Manual)',
        ],
        'installation' => [
            'title' => 'Installation Guide',
            'subtitle' => 'Installation and configuration of the platform in the MAIIC environment',
            'dir' => 'docs/manuals/installation',
            'file' => 'IFRS9-Installation-Guide.pdf',
            'schema' => false,
            'deliverable' => 'Contract Schedule 1, deliverable 7 (Installation and Configuration Guide)',
        ],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function technical()
    {
        return $this->show('technical');
    }

    public function installation()
    {
        return $this->show('installation');
    }

    public function technicalPdf()
    {
        return $this->pdf('technical');
    }

    public function installationPdf()
    {
        return $this->pdf('installation');
    }

    private function show(string $doc)
    {
        $meta = self::DOCS[$doc];

        return Inertia::render('Docs/Show', [
            'doc' => $doc,
            'title' => $meta['title'],
            'subtitle' => $meta['subtitle'],
            'deliverable' => $meta['deliverable'],
            'company' => $this->company(),
            'pdfRoute' => route('docs.' . $doc . '.pdf'),
            'chapters' => $this->chapters($doc),
            'schema' => $meta['schema'] ? $this->liveSchema() : [],
            'lastRevised' => $this->lastRevised($doc),
        ]);
    }

    private function pdf(string $doc)
    {
        $meta = self::DOCS[$doc];

        $company = $this->company();
        $generatedAt = now()->format('d F Y');
        $front = DocumentFrontMatter::for($doc, $company, $generatedAt);
        // The chapter files carry the real revision date; show it as prepared.
        $front['preparedDate'] = $this->lastRevised($doc) ?? $generatedAt;
        $front['revisions'][0][1] = $front['preparedDate'];

        $pdf = Pdf::loadView('manual.docs', [
            'company' => $company,
            'title' => $meta['title'],
            'subtitle' => $meta['subtitle'],
            'deliverable' => $meta['deliverable'],
            'generated_at' => $generatedAt,
            'front' => $front,
            'last_revised' => $this->lastRevised($doc),
            'chapters' => $this->chapters($doc),
            'schema' => $meta['schema'] ? $this->liveSchema() : [],
        ])->setPaper('a4', 'portrait');

        return PdfPageNumbers::stamp($pdf)->download($meta['file']);
    }

    /**
     * Read the chapter files in name order and convert each to HTML. Every
     * h2/h3 receives a stable id so the reader can build a table of contents
     * and deep links; the chapter title is the first h2 of the file.
     *
     * @return array<int, array{slug: string, title: string, html: string, sections: array<int, array{id: string, title: string}>}>
     */
    private function chapters(string $doc): array
    {
        $dir = base_path(self::DOCS[$doc]['dir']);
        if (! is_dir($dir)) {
            return [];
        }

        $files = File::glob($dir . '/*.md');
        sort($files);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $chapters = [];
        foreach ($files as $file) {
            $html = (string) $converter->convert((string) file_get_contents($file));
            $chapterSlug = Str::slug(pathinfo($file, PATHINFO_FILENAME));
            $title = Str::headline($chapterSlug);
            $sections = [];

            $html = preg_replace_callback('/<h([23])>(.*?)<\/h[23]>/s', function ($m) use (&$title, &$sections, $chapterSlug) {
                $text = trim(strip_tags($m[2]));
                if ($m[1] === '2') {
                    $title = $text;

                    return '<h2 id="' . $chapterSlug . '">' . $m[2] . '</h2>';
                }
                $id = $chapterSlug . '-' . Str::slug($text);
                $sections[] = ['id' => $id, 'title' => $text];

                return '<h3 id="' . $id . '">' . $m[2] . '</h3>';
            }, $html);

            $chapters[] = [
                'slug' => $chapterSlug,
                'title' => $title,
                'html' => $html,
                'sections' => $sections,
            ];
        }

        return $chapters;
    }

    /** Newest modification date across the chapter files, shown as the revision date. */
    private function lastRevised(string $doc): ?string
    {
        $dir = base_path(self::DOCS[$doc]['dir']);
        if (! is_dir($dir)) {
            return null;
        }
        $latest = collect(File::glob($dir . '/*.md'))->map(fn ($f) => filemtime($f))->max();

        return $latest ? date('d F Y', $latest) : null;
    }

    /**
     * Column-level data dictionary read from the connected database, so the
     * appendix always matches the schema actually installed. Works on MySQL
     * and MariaDB (production) and on SQLite (test suite).
     *
     * @return array<int, array{name: string, row_count: int, columns: array<int, array{name: string, type: string, nullable: bool, key: string|null}>}>
     */
    private function liveSchema(): array
    {
        $driver = DB::getDriverName();
        $out = [];

        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            foreach ($tables as $t) {
                $cols = DB::select('PRAGMA table_info(' . $t->name . ')');
                $out[] = [
                    'name' => $t->name,
                    'row_count' => $this->safeCount($t->name),
                    'columns' => array_map(fn ($c) => [
                        'name' => $c->name,
                        'type' => $c->type,
                        'nullable' => ! $c->notnull,
                        'key' => $c->pk ? 'PRI' : null,
                    ], $cols),
                ];
            }

            return $out;
        }

        $database = DB::getDatabaseName();
        $tables = DB::select(
            'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE']
        );
        $columns = collect(DB::select(
            'SELECT TABLE_NAME AS tbl, COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_KEY AS key_type
             FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database]
        ))->groupBy('tbl');

        foreach ($tables as $t) {
            $out[] = [
                'name' => $t->name,
                'row_count' => $this->safeCount($t->name),
                'columns' => $columns->get($t->name, collect())->map(fn ($c) => [
                    'name' => $c->name,
                    'type' => $c->type,
                    'nullable' => strtoupper((string) $c->nullable) === 'YES',
                    'key' => $c->key_type ?: null,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
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
