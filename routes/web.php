<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\BloodCultures\Http\Controllers\BloodCultureController;
use App\Modules\CriticalResults\Http\Controllers\CriticalCommunicationController;
use App\Modules\Cultures\Http\Controllers\DirectExamController;
use App\Modules\Cultures\Http\Controllers\EnrichmentController;
use App\Modules\Cultures\Http\Controllers\InoculationController;
use App\Modules\Cultures\Http\Controllers\ReadingController;
use App\Modules\Identification\Http\Controllers\IdentificationController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use App\Modules\Reporting\Http\Controllers\ValidationController;
use App\Modules\Samples\Http\Controllers\ReceptionController;
use App\Modules\Statistics\Http\Controllers\SusceptibilityProfileController;
use App\Modules\Susceptibility\Http\Controllers\SusceptibilityController;
use App\Modules\WorkBatches\Http\Controllers\WorkBatchController;
use App\Modules\Workflow\Http\Controllers\BloodCultureWorkbenchController;
use App\Modules\Workflow\Http\Controllers\InoculationWorkbenchController;
use App\Modules\Workflow\Http\Controllers\WorkbenchController;
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

    // Examen directo y caldos de enriquecimiento.
    Route::get('sample-studies/{sampleStudy}/direct-exam', [DirectExamController::class, 'show'])->name('direct-exams.show');
    Route::post('sample-studies/{sampleStudy}/direct-exam', [DirectExamController::class, 'store'])->name('direct-exams.store');
    Route::get('cultures/{culture}/enrichment', [EnrichmentController::class, 'show'])->name('enrichments.show');
    Route::post('plates/{plate}/enrichment-subculture', [EnrichmentController::class, 'store'])->name('enrichments.store');

    // Tandas de trabajo.
    Route::post('batches', [WorkBatchController::class, 'store'])->name('batches.store');
    Route::get('batches/{batch}', [WorkBatchController::class, 'show'])->name('batches.show');
    Route::post('batches/{batch}/pause', [WorkBatchController::class, 'pause'])->name('batches.pause');
    Route::post('batches/{batch}/resume', [WorkBatchController::class, 'resume'])->name('batches.resume');
    Route::post('batches/{batch}/finish', [WorkBatchController::class, 'finish'])->name('batches.finish');
    Route::post('batches/{batch}/items/{item}/remove', [WorkBatchController::class, 'removeItem'])->name('batches.items.remove');

    // Hemocultivos y retrocultivos: un frasco por muestra.
    Route::get('workbenches/blood-cultures', [BloodCultureWorkbenchController::class, 'index'])->name('workbenches.blood-cultures');
    Route::get('blood-cultures/{sampleStudy}', [BloodCultureController::class, 'show'])->name('blood-cultures.show');
    Route::post('blood-cultures/{sampleStudy}/load', [BloodCultureController::class, 'load'])->name('blood-cultures.load');
    Route::post('bottles/{bottle}/positive', [BloodCultureController::class, 'positive'])->name('bottles.positive');
    Route::post('bottles/{bottle}/negative', [BloodCultureController::class, 'negative'])->name('bottles.negative');
    Route::post('bottles/{bottle}/blind-subcultures', [BloodCultureController::class, 'blindSubculture'])->name('bottles.blind-subcultures.store');
    Route::post('blind-subcultures/{subculture}/reading', [BloodCultureController::class, 'readBlindSubculture'])->name('blind-subcultures.read');
    Route::post('bottles/{bottle}/gram', [BloodCultureController::class, 'gram'])->name('bottles.gram');
    Route::post('bottles/{bottle}/subculture', [BloodCultureController::class, 'subculture'])->name('bottles.subculture');

    // Resultados críticos.
    Route::post('critical-alerts/{alert}/communications', [CriticalCommunicationController::class, 'store'])->name('critical-alerts.communications.store');

    // Mesadas de lectura, identificación, antibiogramas y validación.
    Route::get('workbenches/{workbench}', [WorkbenchController::class, 'show'])->name('workbenches.show');

    // Lectura de cultivos.
    Route::get('cultures/{culture}/reading', [ReadingController::class, 'create'])->name('readings.create');
    Route::post('cultures/{culture}/reading', [ReadingController::class, 'store'])->name('readings.store');

    // Identificación.
    Route::get('isolates/{isolate}/identification', [IdentificationController::class, 'create'])->name('identifications.create');
    Route::post('isolates/{isolate}/identification', [IdentificationController::class, 'store'])->name('identifications.store');

    // Antibiograma.
    Route::get('isolates/{isolate}/susceptibility', [SusceptibilityController::class, 'show'])->name('susceptibility.show');
    Route::post('isolates/{isolate}/susceptibility', [SusceptibilityController::class, 'setUp'])->name('susceptibility.setup');
    Route::post('isolates/{isolate}/susceptibility/results', [SusceptibilityController::class, 'read'])->name('susceptibility.read');

    // Validación e informes.
    Route::get('sample-studies/{sampleStudy}/validation', [ValidationController::class, 'create'])->name('validations.create');
    Route::post('sample-studies/{sampleStudy}/validation', [ValidationController::class, 'store'])->name('validations.store');
    Route::post('sample-studies/{sampleStudy}/preliminary-report', [ValidationController::class, 'preliminary'])->name('reports.preliminary');
    Route::get('reports/{report}', [ValidationController::class, 'show'])->name('reports.show');

    // Estadísticas.
    Route::get('statistics/susceptibility', SusceptibilityProfileController::class)
        ->middleware('can:'.Permission::StatisticsView->value)
        ->name('statistics.susceptibility');

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:'.Permission::AuditView->value)
        ->name('audit.index');
});

require __DIR__.'/settings.php';
