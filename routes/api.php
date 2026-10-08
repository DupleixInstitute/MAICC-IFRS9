<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransitionProfileDefinitionController;
use App\Http\Controllers\ManualsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every route here needs the session (the suite's own pages call these with
| the session cookie) or a Sanctum token. System audit of 9 October 2026,
| finding C7: these answered to anyone, and /collateral-registers returned
| the whole register as JSON with CORS open to any origin. The collateral
| allocation routes named controller methods that do not exist, and the two
| routes under a doubled "/api/api/" prefix had no caller; all are gone. The
| paths the pages call are kept as they were.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/transition-profiles-definitions', [TransitionProfileDefinitionController::class, 'index']);
    Route::delete('/transition-profiles-definitions/{id}', [TransitionProfileDefinitionController::class, 'destroy'])->middleware('permission:reports.ifrs9');
    Route::get('/get-tables', [TransitionProfileDefinitionController::class, 'getTables'])->middleware('permission:reports.ifrs9');
    Route::get('/get-columns/{table}', [TransitionProfileDefinitionController::class, 'getColumns'])->middleware('permission:reports.ifrs9');

    Route::get('/manuals/routes', [ManualsController::class, 'getAvailableRoutes'])->name('manuals.routes');
    Route::get('/manuals/route/{route}', [ManualsController::class, 'showByRoute'])->where('route', '.*');
});
