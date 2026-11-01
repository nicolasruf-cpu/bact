<?php

namespace App\Modules\Workflow\Queries;

use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\CriticalResults\Services\CriticalAlertLink;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Mesada de hemocultivos: trabajo disponible (carga, positivos, repiques, descargas,
 * comunicaciones) y los frascos en incubación de la sede.
 * Orden: inmediata > urgente > rutina → demoradas → más antiguas.
 */
class BloodCultureWorkbenchQuery
{
    /** @var list<ActivityType> */
    public const TYPES = [
        ActivityType::LoadBottle,
        ActivityType::GramStain,
        ActivityType::SubcultureBottle,
        ActivityType::CommunicateCritical,
        ActivityType::BlindSubculture,
        ActivityType::ReadBlindSubculture,
        ActivityType::ConfirmNegativeBottle,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $siteId): array
    {
        return $this->open($siteId)
            ->with(['sample.labOrder.patient', 'sample.sampleType', 'subject'])
            ->orderByRaw("FIELD(priority, 'stat', 'urgent', 'routine')")
            ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 ELSE 1 END', [Date::now()])
            ->orderBy('available_at')
            ->limit(500)
            ->get()
            ->map(fn (Activity $activity): array => $this->present($activity))
            ->values()
            ->all();
    }

    /**
     * Frascos en incubación de la sede, con el día de incubación.
     *
     * @return array<int, array<string, mixed>>
     */
    public function incubating(int $siteId): array
    {
        return BloodCultureBottle::query()
            ->where('status', BottleStatus::Incubating->value)
            ->whereHas('sampleStudy', fn ($q) => $q->where('processing_site_id', $siteId))
            ->with(['sampleStudy.sample.labOrder.patient', 'sampleStudy.study'])
            ->orderBy('loaded_at')
            ->limit(500)
            ->get()
            ->map(fn (BloodCultureBottle $bottle) => [
                'ulid' => $bottle->ulid,
                'barcode' => $bottle->sampleStudy->sample->barcode,
                'patient' => $bottle->sampleStudy->sample->labOrder->patient->fullName(),
                'study' => $bottle->sampleStudy->study->name,
                'method' => $bottle->method->value,
                'method_label' => $bottle->method->label(),
                'instrument_position' => $bottle->instrument_position,
                'loaded_at' => $bottle->loaded_at->toIso8601String(),
                'day' => (int) floor($bottle->loaded_at->diffInHours(Date::now()) / 24) + 1,
                'incubation_ends_at' => $bottle->incubation_ends_at->toIso8601String(),
                'url' => route('blood-cultures.show', $bottle->sample_study_id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{ready: int, urgent: int, overdue: int, scheduled: int, next_at: string|null, incubating: int}
     */
    public function counters(int $siteId): array
    {
        $open = $this->open($siteId);
        $scheduled = $this->base($siteId)
            ->where('status', ActivityStatus::Scheduled->value)
            ->where('available_at', '>', Date::now());
        $nextAt = (clone $scheduled)->min('available_at');

        return [
            'ready' => (clone $open)->count(),
            'urgent' => (clone $open)->where('priority', '!=', Priority::Routine->value)->count(),
            'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', Date::now())->count(),
            'scheduled' => (clone $scheduled)->count(),
            'next_at' => $nextAt ? Date::parse($nextAt)->toIso8601String() : null,
            'incubating' => BloodCultureBottle::query()
                ->where('status', BottleStatus::Incubating->value)
                ->whereHas('sampleStudy', fn ($q) => $q->where('processing_site_id', $siteId))
                ->count(),
        ];
    }

    /**
     * @return Builder<Activity>
     */
    private function open(int $siteId): Builder
    {
        return $this->base($siteId)->where(fn (Builder $q) => $q
            ->whereIn('status', [ActivityStatus::Ready->value, ActivityStatus::InProgress->value])
            ->orWhere(fn (Builder $s) => $s->where('status', ActivityStatus::Scheduled->value)->where('available_at', '<=', Date::now())));
    }

    /**
     * @return Builder<Activity>
     */
    private function base(int $siteId): Builder
    {
        return Activity::query()
            ->where('site_id', $siteId)
            ->whereIn('type', array_map(fn (ActivityType $t) => $t->value, self::TYPES));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Activity $activity): array
    {
        $subject = $activity->subject;

        [$studyId, $detail] = match (true) {
            $subject instanceof SampleStudy => [$subject->id, $subject->study->name],
            $subject instanceof BloodCultureBottle => [$subject->sample_study_id, $subject->method->label()],
            $subject instanceof BlindSubculture => [$subject->bottle->sample_study_id, "Repique n.º {$subject->number}"],
            $subject instanceof CriticalAlert => [null, $subject->summary],
            default => [null, ''],
        };

        if ($activity->type === ActivityType::BlindSubculture) {
            $detail = "Repique n.º {$activity->attempt}";
        }

        return [
            'ulid' => $activity->ulid,
            'type' => $activity->type->value,
            'type_label' => $activity->type->label(),
            'status' => $activity->status->value,
            'priority' => $activity->priority->value,
            'priority_label' => $activity->priority->label(),
            'available_at' => $activity->available_at->toIso8601String(),
            'due_at' => $activity->due_at?->toIso8601String(),
            'overdue' => $activity->isOverdue(),
            'barcode' => $activity->sample->barcode,
            'patient' => $activity->sample->labOrder->patient->fullName(),
            'sample_type' => $activity->sample->sampleType->name,
            'detail' => $detail,
            'url' => match (true) {
                $subject instanceof CriticalAlert => CriticalAlertLink::for($subject),
                $studyId !== null => route('blood-cultures.show', $studyId),
                default => null,
            },
        ];
    }
}
