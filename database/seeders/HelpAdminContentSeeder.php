<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the Administrator Manual (Ticket #011, contract Schedule 1 item 6)
 * into the help centre as the "admin" manual. Content lives in
 * database/seeders/data/help_admin_content.php so it can be reviewed as a
 * document. Runs only when the Administrator Manual is EMPTY so authored
 * edits are never clobbered.
 *
 *   php artisan db:seed --class=HelpAdminContentSeeder
 */
class HelpAdminContentSeeder extends Seeder
{
    public const MANUAL = 'admin';

    public function run(): void
    {
        if (HelpCategory::manual(self::MANUAL)->exists()) {
            $this->command?->info('Administrator Manual already has content; seeder skipped.');

            return;
        }

        $content = $this->content();

        $order = 0;
        foreach ($content as $chapterTitle => $articles) {
            $category = HelpCategory::create([
                'manual' => self::MANUAL,
                'title' => $chapterTitle,
                'slug' => $this->uniqueSlug(HelpCategory::class, 'admin ' . $chapterTitle),
                'order' => ++$order,
            ]);

            $aOrder = 0;
            foreach ($articles as $title => $spec) {
                $article = HelpArticle::create([
                    'help_category_id' => $category->id,
                    'title' => $title,
                    'slug' => $this->uniqueSlug(HelpArticle::class, 'admin ' . $title),
                    'body' => $spec['body'] ?? '',
                    'order' => ++$aOrder,
                    'status' => 'published',
                    'updated_by' => 'System seed',
                ]);
                foreach (array_values($spec['steps'] ?? []) as $i => $text) {
                    $article->steps()->create(['step_no' => $i + 1, 'text' => $text]);
                }
                $iOrder = 0;
                foreach ($spec['images'] ?? [] as $file => $caption) {
                    $article->images()->create([
                        'path' => 'manual/screenshots/' . $file . '.jpg',
                        'caption' => $caption,
                        'order' => ++$iOrder,
                    ]);
                }
                foreach ($spec['routes'] ?? [] as $routeName) {
                    $article->routes()->create(['route_name' => $routeName]);
                }
            }
        }

        $this->command?->info('Administrator Manual seeded: '
            . HelpCategory::manual(self::MANUAL)->count() . ' chapters, '
            . HelpArticle::whereHas('category', fn ($q) => $q->where('manual', self::MANUAL))->count() . ' articles.');
    }

    /**
     * Chapter => [article => spec]. The shipped text lives in
     * database/seeders/data/help_admin_content/*.php, one file per chapter,
     * loaded in file-name order. The original single file is the fallback.
     *
     * @return array<string, array<string, array>>
     */
    private function content(): array
    {
        $dir = database_path('seeders/data/help_admin_content');
        $files = is_dir($dir) ? glob($dir . '/*.php') : [];
        sort($files);
        if ($files) {
            $content = [];
            foreach ($files as $file) {
                foreach ((array) require $file as $chapter => $articles) {
                    $content[$chapter] = array_merge($content[$chapter] ?? [], $articles);
                }
            }

            return $content;
        }

        return require database_path('seeders/data/help_admin_content.php');
    }

    /** Slugs are unique across both manuals, so admin chapters are prefixed. */
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
}
