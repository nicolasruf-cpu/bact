<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Services\ActiveSite;
use App\Modules\Organization\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SwitchActiveSite
{
    public function __construct(
        private readonly ActiveSite $activeSite,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws AuthorizationException si el usuario no tiene ningún rol en esa sede
     */
    public function handle(User $user, Site $site): void
    {
        if (! $site->is_active || ! $this->activeSite->canUse($user, $site)) {
            throw new AuthorizationException('No tenés acceso a esa sede.');
        }

        $previousSiteId = getPermissionsTeamId();

        DB::transaction(function () use ($user, $site, $previousSiteId) {
            $this->activeSite->switchTo($user, $site);

            $this->auditor->record(
                IdentityAuditAction::ActiveSiteSwitched,
                subject: $site,
                before: ['site_id' => $previousSiteId],
                after: ['site_id' => $site->id],
            );
        });
    }
}
