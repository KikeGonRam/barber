<?php

namespace App\Http\Middleware\Role;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasRoleName')) {
            abort(403);
        }

        if ($user->roleNames()->intersect($roles)->isEmpty()) {
            abort(403);
        }

        return $next($request);
    }
}
