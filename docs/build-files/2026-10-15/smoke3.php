<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '-1');
App\Support\LedgerReadLimits::raise();
$ret = app(App\Services\Rbm\RbmReturnService::class)->build('2026-09');
$payload = (new ReflectionMethod(App\Http\Controllers\RbmReturnController::class, 'payload'))->invoke(new App\Http\Controllers\RbmReturnController(), $ret);
foreach (['pdf', 'xlsx', 'csv'] as $f) {
    $resp = App\Support\ReportDownload::respond($payload, 'smoke-rbm', $f);
    ob_start(); $resp->sendContent(); $len = strlen(ob_get_clean());
    echo "RBM $f ", get_class($resp), " bytes $len\n";
}
$r = App\Services\Reports\Ifrs9NoteService::build('2025-12', '2026-09');
$note = App\Services\Reports\Ifrs9NoteDocuments::build($r, 'k');
foreach (['pdf', 'docx', 'excel'] as $f) {
    $resp = App\Services\Reports\Ifrs9NoteDocuments::$f($note);
    echo "note $f ", get_class($resp), "\n";
}
