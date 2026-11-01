<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BlindSubcultureResult;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Enums\PositivitySource;
use App\Modules\BloodCultures\Events\BlindSubcultureRead;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Services\BottleOutcome;
use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Cultures\Services\CultureStarter;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lectura de la placa de un repique.
 * - Desarrollo: el frasco es positivo y la placa del repique pasa a ser el cultivo en estudio
 *   (se lee y se cargan aislamientos como cualquier cultivo).
 * - Sin desarrollo en el último repique (y todos leídos): el frasco se cierra como negativo.
 */
class ReadBlindSubculture
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly BottleOutcome $outcome,
        private readonly CultureStarter $starter,
    ) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BlindSubculture $subculture, BlindSubcultureResult $result, ?string $notes = null): BlindSubculture
    {
        // Como en el cultivo: informar "sin desarrollo" y "desarrollo" son permisos distintos.
        $permission = $result === BlindSubcultureResult::NoGrowth ? Permission::CulturesReadNegative : Permission::CulturesReadPositive;
        if (! $user->can($permission->value)) {
            throw new AuthorizationException('No tenés permiso para registrar esta lectura.');
        }

        return DB::transaction(function () use ($user, $subculture, $result, $notes) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()->with(['sampleStudy.protocolVersion.media', 'blindSubcultures'])
                ->findOrFail($subculture->blood_culture_bottle_id);
            /** @var BlindSubculture $subculture */
            $subculture = $bottle->blindSubcultures->firstWhere('id', $subculture->id);

            if ($subculture->result !== null) {
                return $subculture;
            }
            if ($bottle->status !== BottleStatus::Incubating) {
                throw ValidationException::withMessages(['result' => 'Este frasco ya no está en incubación.']);
            }

            $version = $bottle->sampleStudy->protocolVersion;
            if ($subculture->subcultured_at->copy()->addHours($version->first_read_min_hours)->isFuture()) {
                throw ValidationException::withMessages(['result' => 'La lectura del repique todavía no está disponible (incubación mínima del protocolo).']);
            }

            $subculture->update([
                'result' => $result,
                'notes' => $notes,
                'read_at' => Date::now(),
                'read_by' => $user->id,
            ]);

            $this->auditor->record(BloodCultureAuditAction::BlindSubcultureRead, $bottle, after: [
                'number' => $subculture->number,
                'result' => $result->value,
            ], reason: $notes);

            event(new BlindSubcultureRead($subculture, $bottle, $user->id));

            if ($result === BlindSubcultureResult::Growth) {
                $this->outcome->markPositive($bottle, PositivitySource::BlindSubculture, Date::now(), $user->id, needsSubculture: false, notes: $notes);
                $this->startWorkupFromPlate($bottle, $subculture);

                return $subculture;
            }

            $allRead = $bottle->blindSubcultures->every(fn (BlindSubculture $s) => $s->result !== null);
            if ($bottle->blindSubcultures->count() >= $bottle->incubationDays() && $allRead) {
                $this->outcome->closeNegative($bottle, $user->id);
            }

            return $subculture;
        });
    }

    /**
     * La placa del repique con desarrollo es el cultivo que se estudia: se registra con la
     * hora del repique, así su lectura queda disponible enseguida.
     */
    private function startWorkupFromPlate(BloodCultureBottle $bottle, BlindSubculture $subculture): void
    {
        $study = $bottle->sampleStudy;
        /** @var ProtocolVersionMedium|null $protocolMedium */
        $protocolMedium = $study->protocolVersion->media->firstWhere('medium_id', $subculture->medium_id);

        $this->starter->start(
            $study,
            [[
                'medium_id' => $subculture->medium_id,
                'inoculum' => "Repique n.º {$subculture->number}",
                'incubation_temperature' => $protocolMedium->incubation_temperature ?? '35 ± 2 °C',
                'atmosphere' => $protocolMedium->atmosphere ?? 'aerobic',
            ]],
            $subculture->subcultured_at,
            $subculture->subcultured_by,
            "Repique n.º {$subculture->number} con desarrollo",
        );
    }
}
