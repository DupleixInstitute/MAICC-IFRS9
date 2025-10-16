<?php

use App\Http\Controllers\GeneralImportController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'verified'], 'prefix' => 'custom-imports'], function () {
    Route::get('/', [GeneralImportController::class, 'index'])->name('custom_imports.index');
    Route::get('/create', [GeneralImportController::class, 'create'])->name('custom_imports.create');
    Route::post('/store', [GeneralImportController::class, 'store'])->name('custom_imports.store');
    Route::get('/{customImport}/show', [GeneralImportController::class, 'show'])->name('custom_imports.show');
    Route::get('/{customImport}/edit', [GeneralImportController::class, 'edit'])->name('custom_imports.edit');
    Route::put('/{customImport}/update', [GeneralImportController::class, 'update'])->name('custom_imports.update');
    Route::delete('/{customImport}/destroy', [GeneralImportController::class, 'destroy'])->name('custom_imports.destroy');
});
