<?php

namespace App\Console\Commands;

use App\Models\MacroObservation;
use App\Models\MacroVariable;
use App\Models\SourceImportBatch;
use App\Models\User;
use App\Services\Macro\WorldBankFetcherService;
use App\Services\SourceImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports real historical macro series from the World Bank Open Data API for
 * every macro variable that carries a world_bank indicator code, so a client
 * does not start from scratch. Idempotent (upserts by variable + period) and
 * non-fatal per indicator: a missing series or a network blip skips that one
 * indicator and continues.
 */
class ImportWorldBankMacroCommand extends Command
{
    protected $signature = 'macro:import-worldbank {--code= : Only this macro variable code}';

    protected $description = 'Import historical macro series from the World Bank Open Data API (Zambia)';

    public function handle(WorldBankFetcherService $fetcher): int
    {
        $query = MacroVariable::query()
            ->whereNotNull('external_codes')
            ->where('is_active', true);
        if ($this->option('code')) {
            $query->where('code', $this->option('code'));
        }

        $variables = $query->get()->filter(fn (MacroVariable $v) => ! empty($v->external_codes['world_bank'] ?? null));

        if ($variables->isEmpty()) {
            $this->warn('No macro variables have a World Bank indicator code. Run MacroExternalCodesSeeder first.');
            return self::SUCCESS;
        }

        $userId = User::query()->orderBy('id')->value('id');

        // Universal import log: record this run as a master SourceImportBatch.
        app(SourceImportService::class)->run(
            [
                'source_kind'         => SourceImportBatch::SOURCE_MACRO_WORLDBANK,
                'primary_filename'    => 'World Bank Open Data API (Zambia)',
                'imported_by_user_id' => $userId,
            ],
            function (SourceImportBatch $batch) use ($fetcher, $variables, $userId): array {
                $totalAdded = $totalUpdated = $okSeries = 0;

                foreach ($variables as $variable) {
                    try {
                        $payload = $fetcher->fetch($variable);
                        $rows = $payload['rows'] ?? [];
                        if (empty($rows)) {
                            $this->line("  - {$variable->code} ({$variable->external_codes['world_bank']}): no data returned");
                            continue;
                        }

                        [$added, $updated] = $this->writeRows($variable, $rows, $payload['source'] ?? 'World Bank Open Data', $userId);
                        $totalAdded += $added;
                        $totalUpdated += $updated;
                        $okSeries++;
                        $this->line("  ✓ {$variable->code}: {$added} added, {$updated} updated (" . count($rows) . ' observations)');
                    } catch (\Throwable $e) {
                        $this->warn("  ! {$variable->code} failed: " . substr($e->getMessage(), 0, 80));
                    }
                }

                $this->info("World Bank import complete: {$okSeries}/{$variables->count()} series, {$totalAdded} added, {$totalUpdated} updated.");

                return ['row_count' => $totalAdded + $totalUpdated, 'accepted' => $totalAdded + $totalUpdated, 'file_count' => $variables->count()];
            }
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<array{period_date:string,period_label:string,period_type:string,value:float,value_type:string}>  $rows
     * @return array{0:int,1:int}
     */
    private function writeRows(MacroVariable $variable, array $rows, string $source, ?int $userId): array
    {
        $added = $updated = 0;
        DB::transaction(function () use ($variable, $rows, $source, $userId, &$added, &$updated): void {
            foreach ($rows as $row) {
                $obs = MacroObservation::where('macro_variable_id', $variable->id)
                    ->where('period_date', $row['period_date'])
                    ->where('period_type', $row['period_type'])
                    ->first();
                $payload = [
                    'macro_variable_id' => $variable->id,
                    'period_date'  => $row['period_date'],
                    'period_label' => $row['period_label'],
                    'period_type'  => $row['period_type'],
                    'value'        => $row['value'],
                    'value_type'   => $row['value_type'],
                    'source'       => $source,
                    'created_by'   => $userId,
                ];
                if ($obs) { $obs->update($payload); $updated++; }
                else      { MacroObservation::create($payload); $added++; }
            }
        });
        return [$added, $updated];
    }
}
