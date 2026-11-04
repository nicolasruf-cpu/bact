<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Services\AstPanelResolver;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identification\Events\InstrumentRunResulted;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identification\Models\InstrumentRun;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la identificación de un aislamiento.
 * - Preliminar: queda registrada; la identificación sigue pendiente. Si el protocolo lo
 *   permite, con ella ya se puede preparar el antibiograma (A1).
 * - No concluyente (B6): queda registrada con el motivo (mezcla, perfil bajo, etc.) y la
 *   identificación sigue pendiente, para repetirla.
 * - Definitiva: fija el organismo. Si requiere antibiograma, debe existir panel para su grupo
 *   en esta muestra (D-0021), salvo que ya se haya preparado con la presuntiva; si no lo
 *   requiere, hace falta motivo y el aislamiento queda resuelto (un antibiograma preparado
 *   con la presuntiva se descarta). Un probable contaminante no lleva antibiograma.
 * Con el VITEK, el resultado se asocia a la tarjeta cargada (B3).
 */
class IdentifyIsolate
{
    public function __construct(
        private readonly CultureClosure $closure,
        private readonly Auditor $auditor,
        private readonly AstPanelResolver $panels,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(
        User $user,
        Isolate $isolate,
        ?int $organismId,
        IdentificationMethod $method,
        IdentificationLevel $level,
        ?string $tests,
        ?string $confidence,
        bool $requiresSusceptibility,
        ?string $noAstReason,
        ?string $notes = null,
    ): Identification {
        return DB::transaction(function () use ($user, $isolate, $organismId, $method, $level, $tests, $confidence, $requiresSusceptibility, $noAstReason, $notes) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with('culture.sampleStudy.sample.sampleType')->findOrFail($isolate->id);

            if ($isolate->status !== IsolateStatus::InIdentification) {
                throw ValidationException::withMessages(['organism_id' => 'Este aislamiento ya tiene identificación definitiva.']);
            }

            $organism = $organismId === null ? null : Organism::query()->where('is_active', true)->findOrFail($organismId);
            if ($organism === null && $level !== IdentificationLevel::Inconclusive) {
                throw ValidationException::withMessages(['organism_id' => 'Elegí el organismo.']);
            }
            if ($level === IdentificationLevel::Inconclusive && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Indicá por qué no es concluyente (p. ej., perfil bajo, mezcla).']);
            }

            $definitive = $level === IdentificationLevel::Definitive;
            $existingTest = SusceptibilityTest::currentFor($isolate->id);

            if ($isolate->significance === IsolateSignificance::ProbableContaminant) {
                $requiresSusceptibility = false;
                $noAstReason = filled($noAstReason) ? $noAstReason : IsolateSignificance::ProbableContaminant->label();
            }

            if ($definitive && $requiresSusceptibility && $existingTest === null
                && ! $this->panels->has($organism->organism_group, $isolate->culture->sampleStudy->sample->sample_type_id)) {
                throw ValidationException::withMessages([
                    'requires_susceptibility' => "No hay panel de antibiograma configurado para {$organism->organism_group->label()} en {$isolate->culture->sampleStudy->sample->sampleType->name}.",
                ]);
            }
            if ($definitive && ! $requiresSusceptibility && blank($noAstReason)) {
                throw ValidationException::withMessages(['no_ast_reason' => 'Indicá por qué no requiere antibiograma.']);
            }

            // El resultado del VITEK es el de la tarjeta de identificación cargada y sin resultado.
            $run = null;
            if ($method === IdentificationMethod::Vitek && $level !== IdentificationLevel::Preliminary) {
                $run = InstrumentRun::query()->where('isolate_id', $isolate->id)->whereNotNull('id_card_type')
                    ->whereNull('id_result_at')->lockForUpdate()->latest('loaded_at')->first();
            }

            $identification = Identification::create([
                'isolate_id' => $isolate->id,
                'method' => $method,
                'organism_id' => $organism?->id,
                'instrument_run_id' => $run?->id,
                'level' => $level,
                'confidence' => $confidence,
                'tests' => $tests,
                'notes' => filled($notes) ? $notes : null,
                'identified_by' => $user->id,
                'identified_at' => Date::now(),
            ]);

            $this->auditor->record(
                $level === IdentificationLevel::Inconclusive ? IdentificationAuditAction::Inconclusive : IdentificationAuditAction::Identified,
                $isolate,
                after: [
                    'organism' => $organism?->code,
                    'method' => $method->value,
                    'level' => $level->value,
                    'instrument_run' => $run?->id,
                    'requires_susceptibility' => $definitive ? $requiresSusceptibility : null,
                ],
                reason: $level === IdentificationLevel::Inconclusive ? $notes : ($definitive && ! $requiresSusceptibility ? $noAstReason : null),
            );

            if ($run !== null) {
                $run->update(['id_result_at' => Date::now()]);
                event(new InstrumentRunResulted($run, $user->id));
            }

            if (! $definitive) {
                return $identification;
            }

            if (! $requiresSusceptibility && $existingTest !== null) {
                $existingTest->update([
                    'status' => SusceptibilityTestStatus::Cancelled,
                    'cancelled_by' => $user->id,
                    'cancelled_at' => Date::now(),
                    'cancellation_reason' => 'La identificación definitiva no requiere antibiograma: '.$noAstReason,
                    'lock_version' => $existingTest->lock_version + 1,
                ]);
            }

            if ($requiresSusceptibility) {
                $isolate->update(['organism_id' => $organism->id, 'status' => IsolateStatus::Identified, 'lock_version' => $isolate->lock_version + 1]);
            }

            event(new IsolateIdentified($isolate, $requiresSusceptibility, $user->id, $existingTest));

            if (! $requiresSusceptibility) {
                $this->closure->resolveIsolate($isolate, ['organism_id' => $organism->id, 'no_ast_reason' => $noAstReason]);
            }

            return $identification;
        });
    }
}
