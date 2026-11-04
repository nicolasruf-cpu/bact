<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\BloodCultures\Http\Controllers\BloodCultureController;
use App\Modules\Configuration\Http\Controllers\AstPanelController;
use App\Modules\Configuration\Http\Controllers\AstRuleController;
use App\Modules\Configuration\Http\Controllers\BreakpointSetController;
use App\Modules\Configuration\Http\Controllers\CatalogController;
use App\Modules\Configuration\Http\Controllers\IdentificationTestController;
use App\Modules\Configuration\Http\Controllers\ProtocolController;
use App\Modules\CriticalResults\Http\Controllers\CriticalCommunicationController;
use App\Modules\Cultures\Http\Controllers\CultureIncidentController;
use App\Modules\Cultures\Http\Controllers\DirectExamController;
use App\Modules\Cultures\Http\Controllers\EnrichmentController;
use App\Modules\Cultures\Http\Controllers\InoculationController;
use App\Modules\Cultures\Http\Controllers\ReadingController;
use App\Modules\Identification\Http\Controllers\IdentificationController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\ActiveSiteController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Reporting\Http\Controllers\MarchController;
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
    Route::post('plates/{plate}/lost', [CultureIncidentController::class, 'plateLost'])->name('plates.lost');
    Route::post('sample-studies/{sampleStudy}/rejection', [CultureIncidentController::class, 'reject'])->name('sample-studies.reject');
    Route::get('sample-studies/{sampleStudy}/march', [MarchController::class, 'show'])->name('marches.show');

    // Identificación.
    Route::get('isolates/{isolate}/identification', [IdentificationController::class, 'create'])->name('identifications.create');
    Route::post('isolates/{isolate}/identification', [IdentificationController::class, 'store'])->name('identifications.store');
    Route::post('isolates/{isolate}/tests', [IdentificationController::class, 'storeTests'])->name('isolate-tests.store');
    Route::post('isolates/{isolate}/tests/reading', [IdentificationController::class, 'readTests'])->name('isolate-tests.read');
    Route::post('isolates/{isolate}/significance', [IdentificationController::class, 'significance'])->name('isolates.significance');
    Route::post('isolates/{isolate}/subcultures', [IdentificationController::class, 'subculture'])->name('isolate-subcultures.store');
    Route::post('isolate-subcultures/{subculture}/reading', [IdentificationController::class, 'readSubculture'])->name('isolate-subcultures.read');
    Route::post('isolates/{isolate}/instrument-runs', [IdentificationController::class, 'loadInstrument'])->name('instrument-runs.store');
    Route::post('isolates/{isolate}/referrals', [IdentificationController::class, 'refer'])->name('referrals.store');
    Route::post('referrals/{referral}/result', [IdentificationController::class, 'referralResult'])->name('referrals.result');

    // Antibiograma.
    Route::get('isolates/{isolate}/susceptibility', [SusceptibilityController::class, 'show'])->name('susceptibility.show');
    Route::post('isolates/{isolate}/susceptibility', [SusceptibilityController::class, 'setUp'])->name('susceptibility.setup');
    Route::post('isolates/{isolate}/susceptibility/results', [SusceptibilityController::class, 'read'])->name('susceptibility.read');
    Route::post('isolates/{isolate}/susceptibility/cancel', [SusceptibilityController::class, 'cancel'])->name('susceptibility.cancel');
    Route::post('isolates/{isolate}/susceptibility/tests', [SusceptibilityController::class, 'tests'])->name('susceptibility.tests');

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

    // Administración: usuarios.
    Route::middleware('can:'.Permission::UsersManage->value)->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/active', [UserController::class, 'setActive'])->name('users.active');
        Route::post('clinical-approver', [UserController::class, 'designateApprover'])->name('clinical-approver.update');
    });

    // Administración: catálogos generales.
    Route::middleware('can:'.Permission::ConfigCatalogsEdit->value)->prefix('admin')->name('admin.')->group(function () {
        Route::get('catalogs/{catalog?}', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('catalogs/{catalog}', [CatalogController::class, 'store'])->name('catalogs.store');
    });

    // Configuración clínica: la preparan quienes tienen config.clinical.draft; aprueba el responsable (Q-A8).
    Route::middleware('can:'.Permission::CLINICAL_VIEW)->prefix('admin')->name('admin.')->group(function () {
        Route::get('protocols', [ProtocolController::class, 'index'])->name('protocols.index');
        Route::post('protocols', [ProtocolController::class, 'store'])->name('protocols.store');
        Route::get('protocol-versions/{version}', [ProtocolController::class, 'show'])->name('protocol-versions.show');
        Route::post('protocol-versions/{version}/draft', [ProtocolController::class, 'draft'])->name('protocol-versions.draft');
        Route::put('protocol-versions/{version}', [ProtocolController::class, 'update'])->name('protocol-versions.update');
        Route::post('protocol-versions/{version}/approve', [ProtocolController::class, 'approve'])->name('protocol-versions.approve');
        Route::post('protocol-versions/{version}/activate', [ProtocolController::class, 'activate'])->name('protocol-versions.activate');
        Route::post('protocol-versions/{version}/retire', [ProtocolController::class, 'retire'])->name('protocol-versions.retire');

        Route::get('ast-panels', [AstPanelController::class, 'index'])->name('ast-panels.index');
        Route::post('ast-panels', [AstPanelController::class, 'store'])->name('ast-panels.store');
        Route::put('ast-panels/{panel}', [AstPanelController::class, 'update'])->name('ast-panels.update');

        Route::get('identification-tests', [IdentificationTestController::class, 'index'])->name('identification-tests.index');
        Route::post('identification-tests', [IdentificationTestController::class, 'store'])->name('identification-tests.store');
        Route::put('identification-tests/{test}', [IdentificationTestController::class, 'update'])->name('identification-tests.update');

        Route::get('ast-rules', [AstRuleController::class, 'index'])->name('ast-rules.index');
        Route::post('ast-rules', [AstRuleController::class, 'store'])->name('ast-rules.store');
        Route::put('ast-rules/{rule}', [AstRuleController::class, 'update'])->name('ast-rules.update');

        Route::get('breakpoint-sets', [BreakpointSetController::class, 'index'])->name('breakpoint-sets.index');
        Route::post('breakpoint-sets', [BreakpointSetController::class, 'store'])->name('breakpoint-sets.store');
        Route::get('breakpoint-sets/template', [BreakpointSetController::class, 'template'])->name('breakpoint-sets.template');
        Route::get('breakpoint-sets/{set}', [BreakpointSetController::class, 'show'])->name('breakpoint-sets.show');
        Route::get('breakpoint-sets/{set}/export', [BreakpointSetController::class, 'export'])->name('breakpoint-sets.export');
        Route::post('breakpoint-sets/{set}/import', [BreakpointSetController::class, 'import'])->name('breakpoint-sets.import');
        Route::put('breakpoint-sets/{set}/breakpoints', [BreakpointSetController::class, 'save'])->name('breakpoint-sets.save');
        Route::post('breakpoint-sets/{set}/status', [BreakpointSetController::class, 'status'])->name('breakpoint-sets.status');
    });
});

require __DIR__.'/settings.php';
