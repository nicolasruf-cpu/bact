<?php

namespace App\Modules\WorkBatches\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\WorkBatches\Enums\WorkBatchAuditAction;
use App\Modules\WorkBatches\Enums\WorkBatchEventType;
use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Enums\WorkBatchStatus;
use App\Modules\WorkBatches\Enums\WorkBatchType;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\WorkBatches\Services\BatchJournal;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Models\Activity;
use App\Modules\Workflow\Services\ActivityLifecycle;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arma e inicia una tanda con actividades listas de la sede. Las actividades quedan
 * tomadas por el usuario (IN_PROGRESS); una actividad no puede estar en dos tandas activas.
 */
class StartWorkBatch
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly BatchJournal $journal,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  list<string>  $activityUlids  en el orden en que se van a procesar
     *
     * @throws ValidationException
     */
    public function handle(User $user, int $siteId, WorkBatchType $type, array $activityUlids): WorkBatch
    {
        return DB::transaction(function () use ($user, $siteId, $type, $activityUlids) {
            $activities = Activity::query()
                ->whereIn('ulid', $activityUlids)
                ->lockForUpdate()
                ->get()
                ->sortBy(fn (Activity $a) => array_search($a->ulid, $activityUlids, true))
                ->values();

            $allowedTypes = array_map(fn ($t) => $t->value, $type->activityTypes());
            $valid = $activities->filter(fn (Activity $a) => $a->site_id === $siteId
                && $a->status === ActivityStatus::Ready
                && in_array($a->type->value, $allowedTypes, true));

            if ($valid->isEmpty() || $valid->count() !== count(array_unique($activityUlids))) {
                throw ValidationException::withMessages([
                    'activities' => 'Algunas actividades ya no están disponibles (otra persona las tomó o cambiaron de estado). Actualizá la mesada.',
                ]);
            }

            $batch = new WorkBatch([
                'type' => $type,
                'site_id' => $siteId,
                'owner_id' => $user->id,
                'status' => WorkBatchStatus::InProgress,
                'started_at' => Date::now(),
            ]);
            $batch->code = $this->journal->nextCode($batch);
            $batch->save();

            foreach ($valid as $position => $activity) {
                $this->lifecycle->claim($activity, $user->id);
                $batch->items()->create([
                    'activity_id' => $activity->id,
                    'position' => $position + 1,
                    'status' => WorkBatchItemStatus::Pending,
                ]);
            }

            $this->journal->record($batch, WorkBatchEventType::Started, $user->id, ['items' => $valid->count()]);
            $this->auditor->record(WorkBatchAuditAction::Started, $batch, after: [
                'code' => $batch->code,
                'type' => $type->value,
                'items' => $valid->count(),
            ]);

            return $batch;
        });
    }
}
