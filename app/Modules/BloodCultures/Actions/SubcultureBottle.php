<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Events\BottleSubcultured;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Services\CultureStarter;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subcultivo del frasco positivo en los medios del protocolo. Desde acá sigue el circuito
 * común del cultivo: lectura, aislamientos, identificación y antibiograma.
 * Idempotente: si el frasco ya tiene cultivo, lo devuelve.
 */
class SubcultureBottle
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly CultureStarter $starter,
    ) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle): Culture
    {
        if (! $user->can(Permission::CulturesInoculate->value)) {
            throw new AuthorizationException('No tenés permiso para subcultivar frascos.');
        }

        return DB::transaction(function () use ($user, $bottle) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()->with('sampleStudy.protocolVersion.media.medium')->findOrFail($bottle->id);
            $study = $bottle->sampleStudy;

            $existing = Culture::query()->where('sample_study_id', $study->id)->where('attempt', 1)->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($bottle->status !== BottleStatus::Positive) {
                throw ValidationException::withMessages(['bottle' => 'Solo se subcultiva un frasco positivo.']);
            }

            $media = $study->protocolVersion->media;
            $culture = $this->starter->start(
                $study,
                $media->map(fn (ProtocolVersionMedium $m) => [
                    'medium_id' => $m->medium_id,
                    'inoculum' => $m->inoculum,
                    'incubation_temperature' => $m->incubation_temperature,
                    'atmosphere' => $m->atmosphere,
                ])->all(),
                Date::now(),
                $user->id,
            );

            $this->auditor->record(BloodCultureAuditAction::BottleSubcultured, $bottle, after: [
                'culture' => $culture->ulid,
                'media' => $media->map(fn (ProtocolVersionMedium $m) => $m->medium->code)->values()->all(),
            ]);

            event(new BottleSubcultured($bottle, $user->id));

            return $culture;
        });
    }
}
