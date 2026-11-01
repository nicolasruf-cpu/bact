<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Identification\Models\Identification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la identificación de un aislamiento.
 * - Preliminar: queda registrada; la identificación sigue pendiente.
 * - Definitiva: fija el organismo. Si requiere antibiograma, debe existir panel para su grupo;
 *   si no lo requiere, hace falta motivo y el aislamiento queda resuelto.
 */
class IdentifyIsolate
{
    public function __construct(
        private readonly CultureClosure $closure,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(
        User $user,
        Isolate $isolate,
        int $organismId,
        IdentificationMethod $method,
        IdentificationLevel $level,
        ?string $tests,
        ?string $confidence,
        bool $requiresSusceptibility,
        ?string $noAstReason,
    ): Identification {
        return DB::transaction(function () use ($user, $isolate, $organismId, $method, $level, $tests, $confidence, $requiresSusceptibility, $noAstReason) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->findOrFail($isolate->id);

            if ($isolate->status !== IsolateStatus::InIdentification) {
                throw ValidationException::withMessages(['organism_id' => 'Este aislamiento ya tiene identificación definitiva.']);
            }

            $organism = Organism::query()->where('is_active', true)->findOrFail($organismId);
            $definitive = $level === IdentificationLevel::Definitive;

            if ($definitive && $requiresSusceptibility
                && ! AstPanel::query()->where('organism_group', $organism->organism_group->value)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'requires_susceptibility' => "No hay panel de antibiograma configurado para {$organism->organism_group->label()}.",
                ]);
            }
            if ($definitive && ! $requiresSusceptibility && blank($noAstReason)) {
                throw ValidationException::withMessages(['no_ast_reason' => 'Indicá por qué no requiere antibiograma.']);
            }

            $identification = Identification::create([
                'isolate_id' => $isolate->id,
                'method' => $method,
                'organism_id' => $organism->id,
                'level' => $level,
                'confidence' => $confidence,
                'tests' => $tests,
                'identified_by' => $user->id,
                'identified_at' => Date::now(),
            ]);

            $this->auditor->record(IdentificationAuditAction::Identified, $isolate, after: [
                'organism' => $organism->code,
                'method' => $method->value,
                'level' => $level->value,
                'requires_susceptibility' => $definitive ? $requiresSusceptibility : null,
            ], reason: $definitive && ! $requiresSusceptibility ? $noAstReason : null);

            if (! $definitive) {
                return $identification;
            }

            if ($requiresSusceptibility) {
                $isolate->update(['organism_id' => $organism->id, 'status' => IsolateStatus::Identified, 'lock_version' => $isolate->lock_version + 1]);
            }

            event(new IsolateIdentified($isolate, $requiresSusceptibility, $user->id));

            if (! $requiresSusceptibility) {
                $this->closure->resolveIsolate($isolate, ['organism_id' => $organism->id, 'no_ast_reason' => $noAstReason]);
            }

            return $identification;
        });
    }
}
