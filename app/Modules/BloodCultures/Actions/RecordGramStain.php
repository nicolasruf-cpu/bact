<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Models\GramStain;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gram del frasco positivo. Si el protocolo lo indica, abre un resultado crítico para
 * comunicar (lo hace el módulo de resultados críticos al recibir el evento).
 * Idempotente: un frasco tiene un solo Gram.
 */
class RecordGramStain
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle, string $optionCode, ?string $description = null): GramStain
    {
        if (! $user->can(Permission::GramPerform->value)) {
            throw new AuthorizationException('No tenés permiso para registrar el Gram.');
        }

        return DB::transaction(function () use ($user, $bottle, $optionCode, $description) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()
                ->with(['sampleStudy.protocolVersion', 'sampleStudy.study', 'sampleStudy.sample', 'gramStain'])
                ->findOrFail($bottle->id);

            if ($bottle->gramStain !== null) {
                return $bottle->gramStain;
            }
            if ($bottle->status !== BottleStatus::Positive) {
                throw ValidationException::withMessages(['option_code' => 'El Gram se registra sobre un frasco positivo.']);
            }

            $settings = $bottle->settings();
            if (! collect($settings->gramOptions)->contains('code', $optionCode)) {
                throw ValidationException::withMessages(['option_code' => 'Opción de Gram no válida para este protocolo.']);
            }

            $gram = $bottle->gramStain()->create([
                'option_code' => $optionCode,
                'description' => $description,
                'performed_by' => $user->id,
                'performed_at' => Date::now(),
            ]);

            $this->auditor->record(BloodCultureAuditAction::GramRecorded, $bottle, after: [
                'option' => $optionCode,
                'description' => $description,
            ]);

            $study = $bottle->sampleStudy;
            $summary = "{$study->study->name} positivo · {$study->sample->barcode} · Gram: {$settings->gramLabel($optionCode)}";

            event(new GramStainRecorded(
                gram: $gram,
                bottle: $bottle,
                sampleStudy: $study,
                summary: $summary,
                communicate: $settings->communicateOnPositiveGram,
                userId: $user->id,
            ));

            return $gram;
        });
    }
}
