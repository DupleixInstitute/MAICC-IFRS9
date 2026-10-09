<?php

namespace App\Http\Controllers;

use App\Imports\CreditLossDataImport;
use App\Models\CreditLossDefinition;
use App\Services\Macro\ImfWeoParserService;
use Illuminate\Support\Facades\DB;
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
 *  - rbm-policy-rate: MacroStatisticsController::rbmPreview reads two columns,
 *    date and rate, and skips a first row whose rate is not a number.
 *  - imf-weo: ImfWeoParserService reads a tab-delimited file with the
 *    columns ISO, WEO Subject Code, Units, Estimates Start After and one
 *    column per year; it picks the row of the country and the series' WEO
 *    code. The sample has one row per series that carries a WEO code, with
 *    the year cells left blank.
 *
 * Header rows only: no example figures, so nothing in a sample can be
 * mistaken for real data.
 */
class ImportSampleController extends Controller
{
    private const TAB = "\t";
    private const CRLF = "\r\n";

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
            'rbm-policy-rate' => [['date', 'rate'], 'rbm_policy_rate_sample.csv'],
            'imf-weo' => [null, 'imf_weo_sample.tsv'],
            default => abort(404),
        };

        if ($kind === 'imf-weo') {
            return response($this->imfWeoSample(), 200, [
                'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $file . '"',
                'Cache-Control' => 'no-store',
            ]);
        }

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
     * Header row as ImfWeoParserService reads it, then one row per series
     * with a WEO code for the parser's default country, year cells blank.
     */
    private function imfWeoSample(): string
    {
        $years = range(1980, (int) now()->format('Y') + 5);
        $lines = [implode(self::TAB, array_merge(['ISO', 'WEO Subject Code', 'Units', 'Estimates Start After'], $years))];
        foreach (DB::table('macro_statistics')->orderBy('statistic_code')->get(['unit', 'external_codes']) as $s) {
            $code = (json_decode($s->external_codes ?? '', true) ?: [])['imf_weo'] ?? null;
            if ($code) {
                $lines[] = implode(self::TAB, array_merge([ImfWeoParserService::DEFAULT_COUNTRY, $code, (string) $s->unit, ''], array_fill(0, count($years), '')));
            }
        }

        return implode(self::CRLF, $lines) . self::CRLF;
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
