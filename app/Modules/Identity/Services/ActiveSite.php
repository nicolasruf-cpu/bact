<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Organization\Models\Site;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sede activa de la sesión (ADR-0008). Un usuario puede trabajar en las sedes donde
 * tiene al menos un rol asignado; los permisos se evalúan en la sede activa.
 */
class ActiveSite
{
    public const SESSION_KEY = 'active_site_id';

    public function __construct(private readonly Session $session) {}

    /**
     * @return Collection<int, Site>
     */
    public function availableFor(User $user): Collection
    {
        $siteIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $user->getMorphClass())
            ->where(config('permission.column_names.model_morph_key'), $user->getKey())
            ->distinct()
            ->pluck(config('permission.column_names.team_foreign_key'));

        return Site::query()->active()->whereIn('id', $siteIds)->orderBy('name')->get();
    }

    /**
     * Resuelve la sede activa: la guardada en la sesión si sigue habilitada, o la primera disponible.
     */
    public function resolve(User $user): ?Site
    {
        $available = $this->availableFor($user);
        $stored = $this->session->get(self::SESSION_KEY);

        $site = $available->firstWhere('id', $stored) ?? $available->first();

        $this->apply($user, $site);

        return $site;
    }

    public function canUse(User $user, Site $site): bool
    {
        return $this->availableFor($user)->contains('id', $site->id);
    }

    public function switchTo(User $user, Site $site): void
    {
        $this->apply($user, $site);
    }

    private function apply(User $user, ?Site $site): void
    {
        if ($site === null) {
            $this->session->forget(self::SESSION_KEY);
        } else {
            $this->session->put(self::SESSION_KEY, $site->id);
        }

        setPermissionsTeamId($site?->id);

        // spatie cachea las relaciones de la sede anterior.
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
