<?php

namespace App\Console\Commands;

use App\Services\Ebanker\PackLandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pack 14 RUN 6 (GL_14f) derived on our side from the pack 7 archive, never requested from MAIIC: the read-only review
 * of 17 October 2026 showed that pack 7's CV_04a (CUMVOUCH_BK240824, 12,464 rows in three parts, equal to the table's
 * count in DD_02) holds every row RUN 6 would return. RUN 6's rule, applied to the landed CV_04a rows as received:
 * every row of each batch (transaction date, batch type, batch number) that holds a fee GL leg (4871, 4873, 4874 or
 * 4875 on CB_GLCODE or AC_GLCODE) dated before 1 September 2024, with the source table named first, in RUN 6's order.
 *
 * It writes a pack folder (the CSV and its manifest.json) whose manifest names the source load, pack and files with
 * their SHA-256 and the rule, and the row count as the control; --land lands it under its own id GL_14f on the route
 * DERIVED_LOCAL, through the same gates as any pack. The values stand exactly as CV_04a holds them (dates m/d/yyyy).
 *
 *   php artisan eir:derive-archive-fee-batches [--out=storage/app/derived-packs] [--land] [--user=1]
 */
class DeriveArchiveFeeBatches extends Command
{
    public const FEE_GLS = ['4871', '4873', '4874', '4875'];

    public const BEFORE = '2024-09-01';

    protected $signature = 'eir:derive-archive-fee-batches {--out= : Folder for the derived pack (default storage/app/derived-packs)} {--land : Land it under GL_14f (route DERIVED_LOCAL)} {--user=1}';

    protected $description = 'Derive Pack 14 RUN 6 (GL_14f, archive fee batches) from the landed pack 7 CV_04a rows, with its lineage';

    public function handle(PackLandingService $landing): int
    {
        $rows = DB::table('ebanker_raw_rows')->where('query_id', 'CV_04a')->whereNull('superseded_at')->orderBy('id')->get(['id', 'load_id', 'row_date', 'payload']);
        if ($rows->isEmpty()) {
            $this->error('No CV_04a rows are landed: land pack 7 (ebanker-pack-2026-10-13-p7) first.');

            return self::FAILURE;
        }
        $batches = [];
        $all = [];
        foreach ($rows as $r) {
            $p = json_decode((string) $r->payload, true);
            $key = $r->row_date . '|' . ($p['BATCH_TYPE'] ?? '') . '|' . ($p['BATCH_NUMBER'] ?? '');
            $all[] = [$key, $r->row_date, $p];
            if ($r->row_date !== null && $r->row_date < self::BEFORE
                && (in_array(trim((string) ($p['CB_GLCODE'] ?? '')), self::FEE_GLS, true) || in_array(trim((string) ($p['AC_GLCODE'] ?? '')), self::FEE_GLS, true))) {
                $batches[$key] = true;
            }
        }
        $out = array_values(array_filter($all, fn ($a) => isset($batches[$a[0]])));
        usort($out, fn ($a, $b) => [$a[1], $a[2]['BATCH_TYPE'] ?? '', $a[2]['BATCH_NUMBER'] ?? '', (int) ($a[2]['O_BRANCH_MST_ID'] ?? 0), (int) ($a[2]['CUMVOUCH_DET_ID'] ?? 0)]
            <=> [$b[1], $b[2]['BATCH_TYPE'] ?? '', $b[2]['BATCH_NUMBER'] ?? '', (int) ($b[2]['O_BRANCH_MST_ID'] ?? 0), (int) ($b[2]['CUMVOUCH_DET_ID'] ?? 0)]);

        $loads = $rows->pluck('load_id')->unique()->values();
        $files = DB::table('ebanker_pack_files')->whereIn('load_id', $loads)->where('query_id', 'CV_04a')->orderBy('file')->get(['load_id', 'file', 'sha256', 'rows_loaded']);
        $packs = DB::table('ebanker_loads')->whereIn('id', $loads)->get(['id', 'pack_name', 'pack_hash'])->keyBy('id');
        $stamp = now()->format('Y-m-d');
        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/derived-packs')), '/\\') . DIRECTORY_SEPARATOR . "GL_14f archive fee batches derived {$stamp}";
        @mkdir($dir, 0775, true);
        $name = 'GL_14f_archive_fee_batch_references_derived.csv';
        $header = array_merge(['SOURCE_TABLE'], array_keys($out[0][2] ?? (json_decode((string) $rows->first()->payload, true) ?: [])));
        $f = fopen($dir . DIRECTORY_SEPARATOR . $name, 'w');
        fputcsv($f, $header);
        foreach ($out as [, , $p]) {
            fputcsv($f, array_merge(['CUMVOUCH_BK240824'], array_map(fn ($c) => $p[$c] ?? '', array_slice($header, 1))));
        }
        fclose($f);
        $sha = hash_file('sha256', $dir . DIRECTORY_SEPARATOR . $name);
        $dateFormat = DB::table('ebanker_loads')->whereIn('id', $loads)->value('date_format') ?? 'n/j/Y';
        $manifest = [
            'pack' => "GL_14f archive fee batches, derived on {$stamp} from pack 7 CV_04a (not an extract)",
            'assembled' => $stamp, 'source' => 'derived locally (eir:derive-archive-fee-batches): Pack 14 RUN 6 was not sent to MAIIC',
            'date_format' => $dateFormat,
            'derived_from' => [
                'query_id' => 'CV_04a', 'rule' => 'every CV_04a row of each batch (transaction date, batch type, batch number) holding a fee GL leg (' . implode(', ', self::FEE_GLS) . ' on CB_GLCODE or AC_GLCODE) dated before ' . self::BEFORE . ' (Pack 14 RUN 6)',
                'loads' => $loads->map(fn ($id) => ['load_id' => (int) $id, 'pack' => $packs[$id]->pack_name ?? null, 'pack_hash' => $packs[$id]->pack_hash ?? null])->all(),
                'files' => $files->map(fn ($x) => ['file' => $x->file, 'sha256' => $x->sha256, 'rows' => (int) $x->rows_loaded])->all(),
                'source_rows' => $rows->count(),
            ],
            'accepted_exceptions' => ['GL_14f is derived on our side from CV_04a and was never extracted by MAIIC; it lands on the route DERIVED_LOCAL under its own id, outside the LEDGER family, so no engine reads it'],
            'controls' => ['GL_14f' => ['rows' => count($out), 'source' => 'the derivation itself']],
            'files' => [['file' => $name, 'query_id' => 'GL_14f', 'rows' => count($out), 'sha256' => $sha]],
        ];
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(sprintf('GL_14f derived: %d rows in %d batches from %d CV_04a rows (%d file(s)); written to %s', count($out), count($batches), $rows->count(), $files->count(), $dir));

        if ($this->option('land')) {
            try {
                $r = $landing->land($dir, (int) $this->option('user'), PackLandingService::ROUTE_DERIVED);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info("Landed: load {$r['load_id']}, {$r['status']}.");

            return $r['status'] === 'LANDED' ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
