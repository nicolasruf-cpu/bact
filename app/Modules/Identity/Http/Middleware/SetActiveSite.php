<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Models\User;
use App\Modules\Identity\Services\ActiveSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveSite
{
    public function __construct(private readonly ActiveSite $activeSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $request->attributes->set('activeSite', $this->activeSite->resolve($user));
        }

        return $next($request);
    }
}
