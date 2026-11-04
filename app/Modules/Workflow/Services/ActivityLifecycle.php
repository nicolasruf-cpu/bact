<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Events\ActivityCompleted;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use LogicException;

/**
 * Transiciones de estado de las actividades (WORKFLOW_ENGINE.md §2), siempre explícitas.
 */
class ActivityLifecycle
{
    /**
     * Completa la actividad abierta de un tipo sobre un sujeto (si existe).
     */
    public function completeFor(Model $subject, ActivityType $type, int $userId): ?Activity
    {
        $activity = Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('type', $type->value)
            ->whereNotIn('status', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->lockForUpdate()
            ->first();

        if ($activity === null) {
            return null;
        }

        $this->transition($activity, ActivityStatus::Completed, [
            'completed_by' => $userId,
            'completed_at' => Date::now(),
        ]);

        event(new ActivityCompleted($activity));

        return $activity;
    }

    /**
     * Cancela las actividades abiertas de ciertos tipos sobre un sujeto (p. ej., los repiques
     * pendientes de un frasco que dio positivo), con el motivo.
     *
     * @param  list<ActivityType>  $types
     */
    public function cancelOpenFor(Model $subject, array $types, string $reason): void
    {
        Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('type', array_map(fn (ActivityType $t) => $t->value, $types))
            ->whereNotIn('status', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->lockForUpdate()
            ->get()
            ->each(fn (Activity $activity) => $this->transition($activity, ActivityStatus::Cancelled, ['cancellation_reason' => $reason]));
    }

    public function claim(Activity $activity, int $userId): void
    {
        $this->transition($activity, ActivityStatus::InProgress, [
            'claimed_by' => $userId,
            'claimed_at' => Date::now(),
        ]);
    }

    public function release(Activity $activity): void
    {
        $this->transition($activity, ActivityStatus::Ready, [
            'claimed_by' => null,
            'claimed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Activity $activity, ActivityStatus $target, array $attributes = []): void
    {
        // Una actividad programada cuya hora ya llegó está lista, aunque el liberador
        // (activities:release, cada minuto) todavía no la haya marcado. También se completa
        // antes de su hora: los tiempos del protocolo advierten pero no bloquean (D-0020).
        if ($activity->status === ActivityStatus::Scheduled
            && ($activity->available_at->lessThanOrEqualTo(Date::now()) || $target === ActivityStatus::Completed)) {
            $activity->status = ActivityStatus::Ready;
        }

        // Completar desde READY es válido (actividad individual, fuera de tanda).
        $allowed = $activity->status->canTransitionTo($target)
            || ($activity->status === ActivityStatus::Ready && $target === ActivityStatus::Completed);

        if (! $allowed) {
            throw new LogicException("Transición no permitida: {$activity->status->value} → {$target->value}.");
        }

        $activity->fill([...$attributes, 'status' => $target, 'lock_version' => $activity->lock_version + 1])->save();
    }
}
