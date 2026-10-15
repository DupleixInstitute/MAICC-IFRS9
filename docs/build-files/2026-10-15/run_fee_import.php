<?php
// Run the real EIR Data Intake fee import job on the configured database (a throwaway copy).
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB, Storage};
echo 'DB ', DB::getDatabaseName(), "\n";
if (DB::getDatabaseName() === 'maiic_ifrs9') { exit("refusing to run on production\n"); }
$before = ['fees' => DB::table('contract_fees')->count(), 'held' => DB::table('fee_gl_unassigned')->count()];
$import = App\Models\Import::create(['kind' => 'eir_intake', 'name' => 'EIR fees: test of the 2025 upload', 'status' => 'pending']);
$path = 'temp-imports/fees_test_' . $import->id . '.csv';
Storage::put($path, file_get_contents($argv[1]));
$cols = ['contract_id', 'fee_type', 'amount', 'transaction_date', 'gl_account_ref', 'cashflow_direction', 'currency', 'description', 'source_system', 'source_reference', 'external_transaction_id'];
$job = new App\Jobs\ProcessEirImportJob($import->id, $path, basename($argv[1]), 'fees', array_combine($cols, $cols), [], 1);
app()->call([$job, 'handle']);
$import->refresh();
echo 'status ', $import->status, ' records ', $import->records, ' failed ', $import->failed_records, "\n";
echo 'fees ', $before['fees'], ' -> ', DB::table('contract_fees')->count(), '; held ', $before['held'], ' -> ', DB::table('fee_gl_unassigned')->count(), "\n";
echo 'finance lines ', DB::table('contract_fees')->where('source_system', 'MAIIC Finance EIR assessment 2025')->count(), ', of which superseded ', DB::table('contract_fees')->where('source_system', 'MAIIC Finance EIR assessment 2025')->whereNotNull('superseded_by_source')->count(), "\n";
echo 'GL lines tied by the workbook ', DB::table('contract_fees')->where('description', 'like', '%tied by hand: Finance fee workbook%')->count(), "\n";
if ($import->failed_file_path) echo "exception file:\n", substr(Storage::disk('public')->get($import->failed_file_path), 0, 1500), "\n";
// a second load of the same file changes nothing
$import2 = App\Models\Import::create(['kind' => 'eir_intake', 'name' => 'EIR fees: test again', 'status' => 'pending']);
Storage::put($path . '2', file_get_contents($argv[1]));
app()->call([new App\Jobs\ProcessEirImportJob($import2->id, $path . '2', basename($argv[1]), 'fees', array_combine($cols, $cols), [], 1), 'handle']);
$import2->refresh();
echo 'second load: status ', $import2->status, ' failed ', $import2->failed_records, '; fees now ', DB::table('contract_fees')->count(), "\n";
if ($import2->failed_file_path) echo substr(Storage::disk('public')->get($import2->failed_file_path), 0, 600), "\n";
