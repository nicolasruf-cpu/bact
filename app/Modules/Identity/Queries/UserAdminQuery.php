<?php

namespace App\Modules\Identity\Queries;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\SiteRoles;
use App\Modules\Organization\Models\Site;

/**
 * Datos de la administración de usuarios.
 */
class UserAdminQuery
{
    public function __construct(private readonly SiteRoles $siteRoles) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $search): array
    {
        $sites = Site::query()->pluck('code', 'id');

        return User::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_clinical_approver' => $user->is_clinical_approver,
                'assignments' => array_map(fn (array $a) => [
                    'site' => $sites[$a['site_id']] ?? (string) $a['site_id'],
                    'role' => $a['role']->label(),
                ], $this->siteRoles->assignmentsOf($user)),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_clinical_approver' => $user->is_clinical_approver,
            'lock_version' => $user->lock_version,
            'assignments' => array_map(
                fn (array $a) => ['site_id' => $a['site_id'], 'role' => $a['role']->value],
                $this->siteRoles->assignmentsOf($user),
            ),
        ];
    }

    /**
     * @param  list<int>  $manageableSiteIds
     * @return array{sites: array<int, array{id: int, code: string, name: string, manageable: bool}>, roles: array<int, array{value: string, label: string}>}
     */
    public function options(array $manageableSiteIds): array
    {
        return [
            'sites' => Site::query()->active()->orderBy('name')->get()
                ->map(fn (Site $site) => [
                    'id' => $site->id,
                    'code' => $site->code,
                    'name' => $site->name,
                    'manageable' => in_array($site->id, $manageableSiteIds, true),
                ])->values()->all(),
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
        ];
    }

    /**
     * Directores técnicos activos que pueden ser designados responsables (Q-A8).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function approverCandidates(): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $user) => $this->siteRoles->hasRoleAnywhere($user, Role::TechnicalDirector))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values()->all();
    }
}
