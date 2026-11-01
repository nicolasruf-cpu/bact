<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\DesignateClinicalApprover;
use App\Modules\Identity\Actions\SaveUser;
use App\Modules\Identity\Actions\SetUserActive;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Requests\SaveUserRequest;
use App\Modules\Identity\Queries\UserAdminQuery;
use App\Modules\Identity\Services\SiteRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración de usuarios (users.manage): alta, perfiles por sede, baja y
 * designación del responsable de la configuración clínica.
 */
class UserController extends Controller
{
    public function index(Request $request, UserAdminQuery $query): Response
    {
        $search = $request->string('search')->trim()->toString() ?: null;

        return Inertia::render('admin/users/Index', [
            'users' => $query->list($search),
            'filters' => ['search' => $search],
            'approverCandidates' => $query->approverCandidates(),
        ]);
    }

    public function create(Request $request, UserAdminQuery $query, SiteRoles $siteRoles): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => null,
            ...$query->options($siteRoles->sitesWhere($this->user($request), Permission::UsersManage)),
        ]);
    }

    public function store(SaveUserRequest $request, SaveUser $save): RedirectResponse
    {
        $user = $save->handle($this->user($request), null, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$user->name} creado."]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user, UserAdminQuery $query, SiteRoles $siteRoles): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => $query->detail($user),
            'isSelf' => $this->user($request)->is($user),
            ...$query->options($siteRoles->sitesWhere($this->user($request), Permission::UsersManage)),
        ]);
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        $save->handle($this->user($request), $user, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios guardados.']);

        return to_route('admin.users.index');
    }

    public function setActive(Request $request, User $user, SetUserActive $setActive): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
            'lock_version' => ['required', 'integer'],
        ], [], ['reason' => 'motivo']);

        $user = $setActive->handle($this->user($request), $user, (bool) $validated['active'], $validated['reason'], (int) $validated['lock_version']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? "{$user->name} reactivado." : "{$user->name} dado de baja.",
        ]);

        return back();
    }

    public function designateApprover(Request $request, DesignateClinicalApprover $designate): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => 'motivo', 'user_id' => 'responsable']);

        $target = isset($validated['user_id']) ? User::query()->findOrFail((int) $validated['user_id']) : null;
        $designated = $designate->handle($this->user($request), $target, $validated['reason']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $designated ? "{$designated->name} es el responsable de la configuración clínica." : 'Nadie queda designado responsable.',
        ]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
