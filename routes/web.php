<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use App\Modules\Samples\Http\Controllers\ReceptionController;
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

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:'.Permission::AuditView->value)
        ->name('audit.index');
});

require __DIR__.'/settings.php';
