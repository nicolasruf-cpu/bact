<?php

namespace App\Modules\WorkBatches\Services;

use App\Modules\WorkBatches\Enums\WorkBatchEventType;
use App\Modules\WorkBatches\Models\WorkBatch;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Historial de la tanda (append-only): qué pasó y cuándo, para retomar y revisar.
 */
class BatchJournal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(WorkBatch $batch, WorkBatchEventType $type, int $userId, array $data = []): void
    {
        DB::table('work_batch_events')->insert([
            'work_batch_id' => $batch->id,
            'type' => $type->value,
            'user_id' => $userId,
            'data' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
            'occurred_at' => Date::now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function nextCode(WorkBatch $batch): string
    {
        $prefix = $batch->type->codePrefix().'-'.Date::now()->format('Ymd').'-';
        $count = WorkBatch::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->count();

        return $prefix.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }
}
