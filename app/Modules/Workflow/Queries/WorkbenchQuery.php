<?php

namespace App\Modules\Workflow\Queries;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Enums\Workbench;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Mesadas de lectura, identificación, antibiograma y validación.
 * Orden: inmediata > urgente > demorada > disponible más antigua (WORKFLOW_ENGINE.md §5).
 */
class WorkbenchQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(Workbench $workbench, int $siteId): array
    {
        return $this->open($workbench, $siteId)
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
     * @return array{ready: int, urgent: int, overdue: int, scheduled: int, next_at: string|null}
     */
    public function counters(Workbench $workbench, int $siteId): array
    {
        $open = $this->open($workbench, $siteId);
        $scheduled = $this->base($workbench, $siteId)
            ->where('status', ActivityStatus::Scheduled->value)
            ->where('available_at', '>', Date::now());

        return [
            'ready' => (clone $open)->count(),
            'urgent' => (clone $open)->where('priority', '!=', Priority::Routine->value)->count(),
            'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', Date::now())->count(),
            'scheduled' => (clone $scheduled)->count(),
            'next_at' => (clone $scheduled)->min('available_at') ? Date::parse((clone $scheduled)->min('available_at'))->toIso8601String() : null,
        ];
    }

    /**
     * Trabajo disponible: listo o en curso, y también lo programado cuya hora ya llegó
     * (así la mesada no depende de que el liberador `activities:release` haya corrido).
     *
     * @return Builder<Activity>
     */
    private function open(Workbench $workbench, int $siteId): Builder
    {
        return $this->base($workbench, $siteId)->where(fn (Builder $q) => $q
            ->whereIn('status', [ActivityStatus::Ready->value, ActivityStatus::InProgress->value])
            ->orWhere(fn (Builder $s) => $s->where('status', ActivityStatus::Scheduled->value)->where('available_at', '<=', Date::now())));
    }

    /**
     * @return Builder<Activity>
     */
    private function base(Workbench $workbench, int $siteId): Builder
    {
        return Activity::query()
            ->where('site_id', $siteId)
            ->whereIn('type', array_map(fn (ActivityType $t) => $t->value, $workbench->activityTypes()));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Activity $activity): array
    {
        $subject = $activity->subject;

        [$detail, $url] = match (true) {
            $activity->type === ActivityType::DirectExam && $subject instanceof SampleStudy => [
                $subject->study->name,
                route('direct-exams.show', $subject->id),
            ],
            $subject instanceof Plate => [
                'Caldo '.$subject->medium->name.' · sembrado '.$subject->culture->inoculated_at->diffForHumans(),
                route('enrichments.show', $subject->culture),
            ],
            $subject instanceof Culture => [
                "Lectura n.º {$activity->attempt} · sembrado ".$subject->inoculated_at->diffForHumans(),
                route('readings.create', $subject),
            ],
            $subject instanceof Isolate => [
                "Aislamiento {$subject->number}: ".($subject->organism->name ?? $subject->morphotype),
                $activity->type === ActivityType::Identify
                    ? route('identifications.create', $subject)
                    : route('susceptibility.show', $subject),
            ],
            $subject instanceof SusceptibilityTest => [
                "Aislamiento {$subject->isolate->number}: ".($subject->isolate->organism->name ?? '').' · '.$subject->method->label(),
                route('susceptibility.show', $subject->isolate),
            ],
            $subject instanceof SampleStudy => [
                $subject->study->name,
                route('validations.create', $subject),
            ],
            default => ['', null],
        };

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
            'url' => $url,
        ];
    }
}
