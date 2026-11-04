<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Data\InstrumentLoadData;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\InstrumentLoaded;
use App\Modules\Identification\Models\InstrumentRun;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga un aislamiento en el VITEK (revisión de marchas B3): tarjeta de identificación y/o de
 * antibiograma con su número, casete, posición y suspensión, y la hora de carga (puede ser
 * anterior al registro). Con tarjeta de antibiograma se prepara el antibiograma con el panel
 * VITEK elegido y la hora de la carga (A1). El resultado queda pendiente en la mesada.
 */
class LoadInstrument
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly SetUpSusceptibilityTest $setUp,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, InstrumentLoadData $data): InstrumentRun
    {
        if (! $user->can(Permission::VitekLoad->value) && ! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para cargar el equipo.');
        }
        if (blank($data->idCardType) && blank($data->astCardType)) {
            throw ValidationException::withMessages(['id_card_type' => 'Indicá al menos una tarjeta (identificación o antibiograma).']);
        }
        if (filled($data->astCardType) && $data->astPanelId === null) {
            throw ValidationException::withMessages(['ast_panel_id' => 'Elegí el panel VITEK de la tarjeta de antibiograma.']);
        }

        return DB::transaction(function () use ($user, $isolate, $data) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->findOrFail($isolate->id);
            if ($isolate->status->isResolved()) {
                throw ValidationException::withMessages(['id_card_type' => 'El aislamiento ya está resuelto.']);
            }
            if (filled($data->idCardType) && $isolate->status !== IsolateStatus::InIdentification) {
                throw ValidationException::withMessages(['id_card_type' => 'El aislamiento ya tiene identificación definitiva.']);
            }
            if (filled($data->astCardType) && SusceptibilityTest::currentFor($isolate->id) !== null) {
                throw ValidationException::withMessages(['ast_card_type' => 'El aislamiento ya tiene un antibiograma preparado.']);
            }

            $loadedAt = $data->loadedAt ?? Date::now();
            if ($loadedAt->isFuture()) {
                throw ValidationException::withMessages(['loaded_at' => 'La hora de carga no puede ser futura.']);
            }

            $run = InstrumentRun::create([
                'isolate_id' => $isolate->id,
                'instrument' => trim($data->instrument),
                'id_card_type' => filled($data->idCardType) ? trim((string) $data->idCardType) : null,
                'id_card_number' => filled($data->idCardNumber) ? trim((string) $data->idCardNumber) : null,
                'ast_card_type' => filled($data->astCardType) ? trim((string) $data->astCardType) : null,
                'ast_card_number' => filled($data->astCardNumber) ? trim((string) $data->astCardNumber) : null,
                'cassette' => filled($data->cassette) ? trim((string) $data->cassette) : null,
                'position' => filled($data->position) ? trim((string) $data->position) : null,
                'suspension' => filled($data->suspension) ? trim((string) $data->suspension) : null,
                'loaded_by' => $user->id,
                'loaded_at' => $loadedAt,
            ]);

            if ($run->hasAstCard()) {
                $panel = AstPanel::query()->where('is_active', true)->findOrFail($data->astPanelId);
                if (! $panel->is_vitek) {
                    throw ValidationException::withMessages(['ast_panel_id' => 'La tarjeta de antibiograma necesita un panel VITEK.']);
                }
                $this->setUp->handle($user, $isolate, AstMethod::Mic, $panel->id, [], $loadedAt, $run->id);
            }

            $this->auditor->record(IdentificationAuditAction::InstrumentLoaded, $isolate, after: [
                'instrument' => $run->instrument,
                'id_card' => trim(($run->id_card_type ?? '').' '.($run->id_card_number ?? '')) ?: null,
                'ast_card' => trim(($run->ast_card_type ?? '').' '.($run->ast_card_number ?? '')) ?: null,
                'cassette' => $run->cassette,
                'position' => $run->position,
                'loaded_at' => $loadedAt->toIso8601String(),
            ]);

            event(new InstrumentLoaded($run, $user->id));

            return $run;
        });
    }
}
