<?php

namespace App\Modules\Susceptibility\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prepara el antibiograma: panel según el grupo del organismo identificado y método
 * (difusión manual o CIM/VITEK cargado a mano). Idempotente: un aislamiento tiene un antibiograma.
 */
class SetUpSusceptibilityTest
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, Isolate $isolate, AstMethod $method, ?int $panelId = null): SusceptibilityTest
    {
        return DB::transaction(function () use ($user, $isolate, $method, $panelId) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with('organism')->findOrFail($isolate->id);

            $existing = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($isolate->status !== IsolateStatus::Identified || $isolate->organism === null) {
                throw ValidationException::withMessages(['method' => 'El aislamiento necesita identificación definitiva antes del antibiograma.']);
            }

            $panels = AstPanel::query()->where('organism_group', $isolate->organism->organism_group->value)->where('is_active', true);
            $panel = $panelId !== null ? $panels->find($panelId) : $panels->first();
            if ($panel === null) {
                throw ValidationException::withMessages(['panel_id' => 'No hay un panel válido para este organismo.']);
            }

            $test = SusceptibilityTest::create([
                'isolate_id' => $isolate->id,
                'ast_panel_id' => $panel->id,
                'method' => $method,
                'status' => SusceptibilityTestStatus::SetUp,
                'set_up_by' => $user->id,
                'set_up_at' => Date::now(),
            ]);

            foreach ($panel->antibiotics as $antibiotic) {
                $test->results()->create(['antibiotic_id' => $antibiotic->id, 'is_reported' => true]);
            }

            $this->auditor->record(SusceptibilityAuditAction::SetUp, $test, after: [
                'isolate_id' => $isolate->id,
                'panel' => $panel->code,
                'method' => $method->value,
            ]);

            event(new SusceptibilityTestSetUp($test, $user->id));

            return $test;
        });
    }
}
