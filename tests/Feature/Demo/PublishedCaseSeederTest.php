<?php

use App\Models\User;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Reporting\Models\Report;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PublishedCaseSeeder;
use Illuminate\Support\Facades\Hash;

test('los casos publicados recorren el circuito real sin errores y sin duplicarse', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->seed(PublishedCaseSeeder::class);

    expect(Sample::where('barcode', 'like', 'PUB-%')->count())->toBe(12)
        ->and(Report::count())->toBeGreaterThan(0)
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();

    $status = fn (string $barcode) => SampleStudy::whereHas('sample', fn ($q) => $q->where('barcode', $barcode))->sole()->status;

    // Cada escenario queda en la etapa prevista.
    expect($status('PUB-11'))->toBe(SampleStudyStatus::FinalReported)
        ->and($status('PUB-04'))->toBe(SampleStudyStatus::PreliminaryReported)
        ->and($status('PUB-12'))->toBe(SampleStudyStatus::Pending)
        ->and(CriticalAlert::where('status', 'communicated')->count())->toBe(3)
        ->and(Isolate::whereHas('culture.sampleStudy.sample', fn ($q) => $q->where('barcode', 'PUB-07'))->where('is_significant', false)->count())->toBe(1)
        ->and(SusceptibilityTest::count())->toBe(5);

    // Idempotente.
    $this->seed(PublishedCaseSeeder::class);
    expect(Sample::where('barcode', 'like', 'PUB-%')->count())->toBe(12);
});

test('en una demo expuesta, los usuarios toman la contraseña de DEMO_PASSWORD', function () {
    config(['app.demo_password' => 'Demo-Sintetica-2026']);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(DemoSeeder::class);

    $admin = User::where('email', 'admin@bactruf.test')->sole();
    expect(Hash::check('Demo-Sintetica-2026', $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse();
});
