<?php

namespace App\Modules\Susceptibility\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Services\AstPanelResolver;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Models\Identification;
use App\Modules\Susceptibility\Data\AdditionalAntibiotic;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Susceptibility\Services\AdditionalAntibiotics;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prepara el antibiograma: panel según el grupo del organismo y la muestra (D-0021), y
 * método (difusión manual o CIM). Un panel VITEK se carga como CIM.
 * Se pueden agregar antibióticos por fuera del panel, cada uno con su método; se leen junto
 * con el panel (D-0022). Idempotente: un aislamiento tiene un antibiograma vigente.
 *
 * Con identificación definitiva, el grupo es el del organismo. Si el protocolo lo permite
 * (A1), se prepara antes: con el grupo de la identificación preliminar o eligiendo el panel
 * (por ejemplo, la tarjeta de antibiograma del VITEK, que se carga con la de identificación).
 * La hora de preparación puede ser anterior a la del registro (la de la carga del VITEK).
 */
class SetUpSusceptibilityTest
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly AstPanelResolver $panels,
        private readonly AdditionalAntibiotics $additionalAntibiotics,
    ) {}

    /**
     * @param  list<AdditionalAntibiotic>  $additional
     *
     * @throws ValidationException
     */
    public function handle(
        User $user,
        Isolate $isolate,
        AstMethod $method,
        ?int $panelId = null,
        array $additional = [],
        ?CarbonInterface $setUpAt = null,
        ?int $instrumentRunId = null,
    ): SusceptibilityTest {
        return DB::transaction(function () use ($user, $isolate, $method, $panelId, $additional, $setUpAt, $instrumentRunId) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with(['organism', 'culture.sampleStudy.sample', 'culture.sampleStudy.protocolVersion'])->findOrFail($isolate->id);

            $existing = SusceptibilityTest::currentFor($isolate->id);
            if ($existing !== null) {
                return $existing;
            }

            $definitive = $isolate->status === IsolateStatus::Identified && $isolate->organism_id !== null;
            if (! $definitive) {
                if ($isolate->status !== IsolateStatus::InIdentification) {
                    throw ValidationException::withMessages(['method' => 'Este aislamiento no requiere antibiograma.']);
                }
                if (! $isolate->culture->sampleStudy->protocolVersion->allow_presumptive_ast) {
                    throw ValidationException::withMessages(['method' => 'El aislamiento necesita identificación definitiva antes del antibiograma.']);
                }
            }

            $sampleTypeId = $isolate->culture->sampleStudy->sample->sample_type_id;
            $group = $definitive ? $isolate->organism->organism_group : $this->presumptiveGroup($isolate);
            $panels = $group !== null ? $this->panels->candidates($group, $sampleTypeId) : $this->panels->forSample($sampleTypeId);
            $panel = $panelId !== null ? $panels->firstWhere('id', $panelId) : ($group !== null ? $panels->first() : null);
            if ($panel === null) {
                throw ValidationException::withMessages(['panel_id' => $group === null
                    ? 'Sin identificación, elegí el panel.'
                    : 'No hay un panel válido para este germen en esta muestra.']);
            }
            if ($panel->is_vitek && $method !== AstMethod::Mic) {
                throw ValidationException::withMessages(['method' => 'El panel VITEK se carga como CIM.']);
            }

            $now = Date::now();
            $at = $setUpAt ?? $now;
            if ($at->greaterThan($now)) {
                throw ValidationException::withMessages(['set_up_at' => 'La hora de preparación no puede ser futura.']);
            }

            $test = SusceptibilityTest::create([
                'isolate_id' => $isolate->id,
                'ast_panel_id' => $panel->id,
                'instrument_run_id' => $instrumentRunId,
                'method' => $method,
                'status' => SusceptibilityTestStatus::SetUp,
                'set_up_by' => $user->id,
                'set_up_at' => $at,
            ]);

            foreach ($panel->antibiotics as $antibiotic) {
                $test->results()->create([
                    'antibiotic_id' => $antibiotic->id,
                    'method' => $method,
                    'set_up_at' => $at,
                    'set_up_by' => $user->id,
                    'is_reported' => true,
                ]);
            }
            $this->additionalAntibiotics->add($user, $test, $additional, $at);

            $this->auditor->record(SusceptibilityAuditAction::SetUp, $test, after: [
                'isolate_id' => $isolate->id,
                'panel' => $panel->code,
                'is_vitek' => $panel->is_vitek,
                'method' => $method->value,
                'presumptive' => ! $definitive,
                'set_up_at' => $at->toIso8601String(),
            ]);

            event(new SusceptibilityTestSetUp($test, $user->id));

            return $test;
        });
    }

    /**
     * Grupo de la última identificación preliminar, si la hay.
     */
    private function presumptiveGroup(Isolate $isolate): ?OrganismGroup
    {
        /** @var Identification|null $preliminary */
        $preliminary = Identification::query()
            ->where('isolate_id', $isolate->id)
            ->where('level', IdentificationLevel::Preliminary->value)
            ->whereNotNull('organism_id')
            ->with('organism')
            ->latest('id')
            ->first();

        return $preliminary?->organism?->organism_group;
    }

    /**
     * Paneles que se pueden elegir para un aislamiento, para la pantalla.
     *
     * @return Collection<int, AstPanel>
     */
    public function panelsFor(Isolate $isolate): Collection
    {
        $isolate->loadMissing(['organism', 'culture.sampleStudy.sample']);
        $sampleTypeId = $isolate->culture->sampleStudy->sample->sample_type_id;
        $group = $isolate->organism !== null ? $isolate->organism->organism_group : $this->presumptiveGroup($isolate);

        return $group !== null ? $this->panels->candidates($group, $sampleTypeId)->toBase() : $this->panels->forSample($sampleTypeId)->toBase();
    }
}
