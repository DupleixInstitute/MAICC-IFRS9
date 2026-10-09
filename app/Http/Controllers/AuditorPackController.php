<?php

namespace App\Http\Controllers;

use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

/**
 * Report Hub, Auditor Pack (spec v4 section 12.5). The pack for a period is
 * the compliance workbooks, the EIR as at the period end, the baselines and
 * the ECL by stage, zipped with a manifest carrying a SHA-256 per file. Until
 * the system audit of 9 October 2026 (finding M16) it was built only on the
 * console (compliance:audits --pack); this screen lists the packs already
 * built with what their manifests say, builds one for a chosen period and
 * hands a pack out. A pack is built again, never edited: the zip for a period
 * is replaced whole and its manifest says when.
 */
class AuditorPackController extends Controller
{
    public const DIRECTORY = 'app/auditor-packs';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.view|eir.export')->only('index');
        $this->middleware('permission:eir.export')->only(['build', 'download']);
    }

    public function index()
    {
        $packs = [];
        foreach (glob(storage_path(self::DIRECTORY) . '/*.zip') ?: [] as $path) {
            $packs[] = $this->describe($path);
        }
        usort($packs, fn ($a, $b) => strcmp($b['period'] ?? '', $a['period'] ?? '') ?: ($b['modified_at'] <=> $a['modified_at']));
        $lastBuild = DB::table('audit_logs')->where('action', "Auditor's Pack Built")->orderByDesc('id')->first();

        return Inertia::render('Reports/AuditorPack', [
            'packs' => $packs, 'periods' => $this->periods(),
            'lastBuild' => $lastBuild ? ['period' => $lastBuild->reporting_period, 'at' => $lastBuild->created_at, 'result' => json_decode($lastBuild->new_values, true)] : null,
            'canExport' => (bool) (auth()->user()?->can('eir.export') ?? false),
        ]);
    }

    /** Build the pack for a period: the console command, called in the request. */
    public function build(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $code = Artisan::call('compliance:audits', ['--pack' => $data['period']]);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            return back()->with('error', 'The pack could not be built: ' . $e->getMessage());
        }
        AuditLoggerService::log("Auditor's Pack Built", 'auditor_packs', null, ['reporting_period' => $data['period'],
            'new_values' => ['exit_code' => $code, 'output' => mb_substr($output, 0, 2000)], 'meta' => ['user' => $request->user()->id, 'screen' => 'Auditor Pack']]);
        if ($code !== 0) {
            return back()->with('error', 'The pack could not be built for ' . $data['period'] . ': ' . mb_substr($output, 0, 500));
        }

        return back()->with('success', $output);
    }

    public function download(string $file): BinaryFileResponse
    {
        // only a pack the command named, from the pack directory: no path of the caller's choosing
        abort_unless(preg_match('/^MAIIC auditor pack \d{4}-\d{2}\.zip$/', $file) === 1, 404);
        $path = storage_path(self::DIRECTORY . '/' . $file);
        abort_unless(is_file($path), 404);
        AuditLoggerService::log("Auditor's Pack Downloaded", 'auditor_packs', null, ['reporting_period' => substr($file, -11, 7), 'new_values' => ['file' => $file, 'sha256' => hash_file('sha256', $path)]]);

        return response()->download($path, $file);
    }

    /** What a pack on disk says about itself: its period from the name, its size and date, and the counts its manifest carries. */
    private function describe(string $path): array
    {
        $name = basename($path);
        $manifest = null;
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $raw = $zip->getFromName('manifest.json');
                $manifest = $raw !== false ? json_decode($raw, true) : null;
                $entries = $zip->numFiles;
                $zip->close();
            }
        } catch (Throwable) {
            $manifest = null;
        }

        return [
            'file' => $name,
            'period' => preg_match('/(\d{4}-\d{2})\.zip$/', $name, $m) ? $m[1] : null,
            'bytes' => filesize($path),
            'modified_at' => date('Y-m-d H:i:s', filemtime($path)),
            'built_at' => $manifest['built_at'] ?? null,
            'files' => isset($manifest['files']) ? count($manifest['files']) : null,
            'entries' => $entries ?? null,
            'with_sha256' => isset($manifest['files']) ? count(array_filter($manifest['files'], fn ($f) => ! empty($f['sha256']))) : null,
            'names' => isset($manifest['files']) ? array_map(fn ($f) => $f['file'], $manifest['files']) : [],
            'manifest_ok' => $manifest !== null,
        ];
    }

    /** @return list<string> */
    private function periods(): array
    {
        return DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period')->all();
    }
}
