<?php

namespace App\Http\Controllers;

use App\Imports\CreditLossDataImport;
use App\Models\CreditLossDefinition;
use ReflectionClass;

/**
 * Blank sample files for the import screens that had none. Each header row
 * is taken from what the importer reads, never typed out separately:
 *
 *  - credit-loss-data: CreditLossDataImport reads "period", one column per
 *    credit loss definition (it matches the definition's aliases against the
 *    file's slugged headings) and the optional "source" and "notes".
 *  - sicr-groups: SicrGroupController::import requires name, description.
 *  - sicr-items: SicrItemController::import requires group, name, active.
 *
 * Header rows only: no example figures, so nothing in a sample can be
 * mistaken for real data.
 */
class ImportSampleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(string $kind)
    {
        [$headers, $file] = match ($kind) {
            'credit-loss-data' => [$this->creditLossHeaders(), 'credit_loss_data_sample.csv'],
            'sicr-groups' => [['name', 'description'], 'sicr_groups_sample.csv'],
            'sicr-items' => [['group', 'name', 'active'], 'sicr_items_sample.csv'],
            default => abort(404),
        };

        return response(implode(',', $headers) . "\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * period, then for each definition the first alias the importer accepts
     * (the one written with underscores, which is what a heading slugs to),
     * then source and notes.
     */
    private function creditLossHeaders(): array
    {
        $columns = collect(self::creditLossAliases())
            ->map(fn ($aliases, $code) => collect($aliases)->first(fn ($a) => ! str_contains($a, ' ')) ?? strtolower($code))
            ->values()->all();

        return array_merge(['period'], $columns, ['source', 'notes']);
    }

    /**
     * The aliases CreditLossDataImport accepts for each definition code, read
     * from the importer itself so the screen and the sample never drift from it.
     *
     * @return array<string, list<string>>
     */
    public static function creditLossAliases(): array
    {
        $class = new ReflectionClass(CreditLossDataImport::class);
        $importer = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('getAliasesForDefinition');
        $method->setAccessible(true);

        return CreditLossDefinition::orderBy('id')->pluck('code')
            ->mapWithKeys(fn ($code) => [$code => array_values($method->invoke($importer, $code))])
            ->all();
    }
}
