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
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/api/transition-profiles-definitions', [TransitionProfileDefinitionController::class, 'getProfiles']);
Route::get('/get-tables', [TransitionProfileDefinitionController::class, 'getTables']);
Route::get('/get-columns/{table}', [TransitionProfileDefinitionController::class, 'getColumns']);
Route::get('/transition-profiles-definitions', [TransitionProfileDefinitionController::class, 'index']);
Route::delete('/api/transition-profiles-definitions/{id}', [TransitionProfileDefinitionController::class, 'destroy']);

//Manual Routes

// Route::get('/manuals/route/{route}', [ManualsController::class, 'showByRoute']);
Route::get('/manuals/routes', [ManualsController::class, 'getAvailableRoutes'])->name('manuals.routes');
Route::get('/manuals/route/{route}', [ManualsController::class, 'showByRoute'])
    ->where('route', '.*');