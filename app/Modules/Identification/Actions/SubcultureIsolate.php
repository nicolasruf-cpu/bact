<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\IsolateSubcultured;
use App\Modules\Identification\Models\IsolateSubculture;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Repica o purifica un aislamiento en otra placa (revisión de marchas B4): no está puro,
 * hace falta otro medio o más masa para las pruebas. La placa queda en el cultivo, pero no
 * se lee con él: tiene su propia lectura, a la incubación de la primera lectura del protocolo.
 */
class SubcultureIsolate
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, int $mediumId, string $temperature, Atmosphere $atmosphere, ?string $reason): IsolateSubculture
    {
        if (! $user->can(Permission::IdentificationPerform->value) && ! $user->can(Permission::IdentificationBiochemSetup->value)) {
            throw new AuthorizationException('No tenés permiso para repicar aislamientos.');
        }

        return DB::transaction(function () use ($user, $isolate, $mediumId, $temperature, $atmosphere, $reason) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with('culture.sampleStudy.protocolVersion')->findOrFail($isolate->id);
            if ($isolate->status->isResolved() || $isolate->culture->status->isClosed()) {
                throw ValidationException::withMessages(['medium_id' => 'El aislamiento ya está resuelto.']);
            }
            $medium = Medium::query()->where('is_active', true)->findOrFail($mediumId);

            $now = Date::now();
            $plate = $isolate->culture->plates()->create([
                'medium_id' => $medium->id,
                'source_isolate_id' => $isolate->id,
                'inoculum' => 'Repique del aislamiento '.$isolate->number,
                'incubation_temperature' => trim($temperature),
                'atmosphere' => $atmosphere,
                'is_enrichment' => false,
                'inoculated_at' => $now,
                'status' => PlateStatus::Incubating,
            ]);

            $subculture = IsolateSubculture::create([
                'isolate_id' => $isolate->id,
                'plate_id' => $plate->id,
                'reason' => filled($reason) ? $reason : null,
                'subcultured_by' => $user->id,
                'subcultured_at' => $now,
            ]);

            $this->auditor->record(IdentificationAuditAction::Subcultured, $isolate, after: [
                'medium' => $medium->code,
                'atmosphere' => $atmosphere->value,
            ], reason: $reason);

            $readableAt = $now->copy()->addHours($isolate->culture->sampleStudy->protocolVersion->first_read_min_hours);
            event(new IsolateSubcultured($subculture, $readableAt, $user->id));

            return $subculture;
        });
    }
}
