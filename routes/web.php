<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Cultures\Http\Controllers\InoculationController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use App\Modules\Samples\Http\Controllers\ReceptionController;
use App\Modules\WorkBatches\Http\Controllers\WorkBatchController;
use App\Modules\Workflow\Http\Controllers\InoculationWorkbenchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::put('active-site', [ActiveSiteController::class, 'update'])->name('active-site.update');

    Route::middleware('can:'.Permission::SamplesReceive->value)->group(function () {
        Route::get('reception', [ReceptionController::class, 'create'])->name('reception.create');
        Route::post('reception', [ReceptionController::class, 'store'])->name('reception.store');
    });

    Route::get('workbenches/inoculation', [InoculationWorkbenchController::class, 'index'])
        ->middleware('can:'.Permission::CulturesInoculate->value)
        ->name('workbenches.inoculation');

    // Siembra (individual o en bloque con protocolo estándar).
    Route::post('sample-studies/{sampleStudy}/inoculation', [InoculationController::class, 'store'])->name('inoculations.store');
    Route::post('inoculations/bulk', [InoculationController::class, 'bulk'])->name('inoculations.bulk');

    // Tandas de trabajo.
    Route::post('batches', [WorkBatchController::class, 'store'])->name('batches.store');
    Route::get('batches/{batch}', [WorkBatchController::class, 'show'])->name('batches.show');
    Route::post('batches/{batch}/pause', [WorkBatchController::class, 'pause'])->name('batches.pause');
    Route::post('batches/{batch}/resume', [WorkBatchController::class, 'resume'])->name('batches.resume');
    Route::post('batches/{batch}/finish', [WorkBatchController::class, 'finish'])->name('batches.finish');
    Route::post('batches/{batch}/items/{item}/remove', [WorkBatchController::class, 'removeItem'])->name('batches.items.remove');

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:'.Permission::AuditView->value)
        ->name('audit.index');
});

require __DIR__.'/settings.php';
