<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\ActiveSite;
use App\Modules\Organization\Models\Site;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeSite = $request->attributes->get('activeSite');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // Roles en la sede activa, con su nombre para mostrar.
                'roles' => $user instanceof User
                    ? $user->getRoleNames()->map(fn (string $name) => Role::tryFrom($name)?->label() ?? $name)->values()
                    : [],
                // Permisos en la sede activa: el frontend solo los usa para mostrar u ocultar;
                // la autorización real se hace siempre en el servidor.
                'permissions' => $user instanceof User
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'site' => [
                'active' => $activeSite instanceof Site ? $activeSite->only(['id', 'code', 'name']) : null,
                'available' => $user instanceof User
                    ? app(ActiveSite::class)->availableFor($user)->map->only(['id', 'code', 'name'])->values()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
