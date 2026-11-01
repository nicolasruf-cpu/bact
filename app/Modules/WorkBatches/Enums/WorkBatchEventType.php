<?php

namespace App\Modules\WorkBatches\Enums;

enum WorkBatchEventType: string
{
    case Started = 'started';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case ItemDone = 'item_done';
    case ItemRemoved = 'item_removed';
    case Finished = 'finished';
}
