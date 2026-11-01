<?php

namespace App\Modules\WorkBatches\Policies;

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\WorkBatches\Models\WorkBatch;

/**
 * Una tanda la maneja quien la armó (o quien tenga batches.manage_any), en su sede.
 */
class WorkBatchPolicy
{
    public function manage(User $user, WorkBatch $batch): bool
    {
        if ($batch->site_id !== getPermissionsTeamId()) {
            return false;
        }

        return $batch->owner_id === $user->id
            ? $user->can(Permission::BatchesManageOwn->value)
            : $user->can(Permission::BatchesManageAny->value);
    }
}
