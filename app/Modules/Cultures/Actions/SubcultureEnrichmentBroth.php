<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Configuration\Services\IncubationTimeCheck;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Events\EnrichmentSubcultured;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Repique de un caldo de enriquecimiento a los medios que indica el protocolo congelado,
 * cumplidas las horas de incubación del caldo (antes, con confirmación: D-0020). Las placas
 * nuevas quedan en el mismo cultivo y se leen en sus lecturas programadas. Idempotente: un caldo ya repicado
 * devuelve sus placas.
 */
class SubcultureEnrichmentBroth
{
    public function __construct(
        private readonly IncubationTimeCheck $timeCheck,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return Collection<int, Plate>
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, Plate $broth, EarlyConfirmation $early = new EarlyConfirmation): Collection
    {
        if (! $user->can(Permission::CulturesInoculate->value)) {
            throw new AuthorizationException('No tenés permiso para repicar.');
        }

        return DB::transaction(function () use ($user, $broth, $early) {
            /** @var Plate $broth */
            $broth = Plate::query()->lockForUpdate()->with('medium')->findOrFail($broth->id);
            /** @var Culture $culture */
            $culture = Culture::query()->lockForUpdate()->with('sampleStudy.protocolVersion.media')->findOrFail($broth->culture_id);

            if (! $broth->is_enrichment) {
                throw ValidationException::withMessages(['plate' => 'Esta placa no es un caldo de enriquecimiento.']);
            }
            if ($broth->status === PlateStatus::Finished && $broth->subcultures()->exists()) {
                return $broth->subcultures()->get()->toBase();
            }
            if ($broth->status !== PlateStatus::Incubating || $culture->status->isClosed()) {
                throw ValidationException::withMessages(['plate' => 'Este caldo ya no se puede repicar.']);
            }

            /** @var ProtocolVersionMedium|null $setting */
            $setting = $culture->sampleStudy->protocolVersion->media->firstWhere('medium_id', $broth->medium_id);
            $targets = $setting === null ? [] : ($setting->enrichment_subculture_media ?? []);
            if ($setting === null || $targets === []) {
                throw ValidationException::withMessages(['plate' => 'El protocolo no indica a qué medios repicar este caldo.']);
            }

            $availableAt = $culture->inoculated_at->copy()->addHours((int) $setting->enrichment_subculture_min_hours);
            $this->timeCheck->ensure($availableAt, $early, $culture, "Repique del caldo {$broth->medium->code}");

            $now = Date::now();
            $plates = collect($targets)->map(fn (array $target) => $culture->plates()->create([
                'source_plate_id' => $broth->id,
                'medium_id' => Medium::query()->where('code', $target['medium_code'])->firstOrFail()->id,
                'inoculum' => "Repique de {$broth->medium->code}",
                'incubation_temperature' => $target['incubation_temperature'],
                'atmosphere' => Atmosphere::from($target['atmosphere']),
                'status' => PlateStatus::Incubating,
                'inoculated_at' => $now,
            ]));

            $broth->update(['status' => PlateStatus::Finished]);

            $this->auditor->record(CultureAuditAction::EnrichmentSubcultured, $culture, after: [
                'broth' => $broth->medium->code,
                'media' => array_column($targets, 'medium_code'),
            ]);

            event(new EnrichmentSubcultured($culture, $broth, $user->id));

            return $plates->toBase();
        });
    }
}
