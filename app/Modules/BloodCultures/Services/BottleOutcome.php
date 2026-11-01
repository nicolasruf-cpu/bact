<?php

namespace App\Modules\BloodCultures\Services;

use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Enums\PositivitySource;
use App\Modules\BloodCultures\Events\BottleNegative;
use App\Modules\BloodCultures\Events\BottlePositive;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Samples\Enums\SampleStudyStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Cambios de estado del frasco que cierran la incubación. Se llama dentro de la
 * transacción de la operación que los provoca, con el frasco ya bloqueado.
 */
class BottleOutcome
{
    public function __construct(private readonly Auditor $auditor) {}

    public function markPositive(
        BloodCultureBottle $bottle,
        PositivitySource $source,
        CarbonInterface $positiveAt,
        int $userId,
        bool $needsSubculture,
        ?string $notes = null,
    ): void {
        $bottle->update([
            'status' => BottleStatus::Positive,
            'positive_at' => $positiveAt,
            'positivity_source' => $source,
            'positive_recorded_by' => $userId,
            'notes' => $notes ?? $bottle->notes,
            'lock_version' => $bottle->lock_version + 1,
        ]);

        $this->auditor->record(BloodCultureAuditAction::BottlePositive, $bottle, after: [
            'source' => $source->value,
            'positive_at' => $positiveAt->toIso8601String(),
            'minutes_to_positivity' => $bottle->minutesToPositivity(),
        ], reason: $notes);

        event(new BottlePositive($bottle, $bottle->sampleStudy, $needsSubculture, $userId));
    }

    public function closeNegative(BloodCultureBottle $bottle, int $userId): void
    {
        $bottle->update([
            'status' => BottleStatus::Negative,
            'closed_at' => Date::now(),
            'closed_by' => $userId,
            'lock_version' => $bottle->lock_version + 1,
        ]);

        $study = $bottle->sampleStudy;
        $study->update(['status' => SampleStudyStatus::ReadyForValidation, 'lock_version' => $study->lock_version + 1]);

        $this->auditor->record(BloodCultureAuditAction::BottleNegative, $bottle, after: [
            'incubation_days' => $bottle->incubationDays(),
        ]);

        event(new BottleNegative($bottle, $study, $userId));
    }
}
