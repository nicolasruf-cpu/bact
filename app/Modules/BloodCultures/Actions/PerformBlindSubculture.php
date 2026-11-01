<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Events\BlindSubcultureDone;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Repique diario del frasco manual a una placa. Cada repique se habilita al cumplirse
 * su intervalo desde la carga, hasta completar los días de incubación del protocolo.
 */
class PerformBlindSubculture
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle): BlindSubculture
    {
        if (! $user->can(Permission::CulturesInoculate->value)) {
            throw new AuthorizationException('No tenés permiso para repicar frascos.');
        }

        return DB::transaction(function () use ($user, $bottle) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()->with(['sampleStudy.protocolVersion', 'blindSubcultures'])->findOrFail($bottle->id);

            if ($bottle->isAutomated()) {
                throw ValidationException::withMessages(['bottle' => 'Los frascos del equipo no se repican a ciegas.']);
            }
            if ($bottle->status !== BottleStatus::Incubating) {
                throw ValidationException::withMessages(['bottle' => 'Este frasco ya no está en incubación.']);
            }

            $settings = $bottle->settings();
            $version = $bottle->sampleStudy->protocolVersion;
            $days = $bottle->incubationDays();
            $number = $bottle->blindSubcultures->count() + 1;

            if ($number > $days) {
                throw ValidationException::withMessages(['bottle' => "Ya se hicieron los {$days} repiques del protocolo."]);
            }

            $dueFrom = $bottle->loaded_at->copy()->addHours($number * $settings->blindSubcultureIntervalHours);
            if ($dueFrom->isFuture()) {
                throw ValidationException::withMessages(['bottle' => 'Todavía no corresponde el próximo repique.']);
            }

            $medium = Medium::query()->where('code', $settings->blindSubcultureMediumCode)->firstOrFail();
            $now = Date::now();

            $subculture = $bottle->blindSubcultures()->create([
                'number' => $number,
                'medium_id' => $medium->id,
                'subcultured_at' => $now,
                'subcultured_by' => $user->id,
            ]);

            $this->auditor->record(BloodCultureAuditAction::BlindSubcultureDone, $bottle, after: [
                'number' => $number,
                'medium' => $medium->code,
            ]);

            event(new BlindSubcultureDone(
                subculture: $subculture,
                bottle: $bottle,
                readAvailableAt: $now->copy()->addHours($version->first_read_min_hours),
                readDueAt: $now->copy()->addHours($version->first_read_due_hours),
                nextSubcultureAt: $number < $days
                    ? $bottle->loaded_at->copy()->addHours(($number + 1) * $settings->blindSubcultureIntervalHours)
                    : null,
                userId: $user->id,
            ));

            return $subculture;
        });
    }
}
