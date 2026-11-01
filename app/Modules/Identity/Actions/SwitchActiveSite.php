<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Services\ActiveSite;
use App\Modules\Organization\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;

class SwitchActiveSite
{
    public function __construct(private readonly ActiveSite $activeSite) {}

    /**
     * @throws AuthorizationException si el usuario no tiene ningún rol en esa sede
     */
    public function handle(User $user, Site $site): void
    {
        if (! $site->is_active || ! $this->activeSite->canUse($user, $site)) {
            throw new AuthorizationException('No tenés acceso a esa sede.');
        }

        $this->activeSite->switchTo($user, $site);
    }
}
