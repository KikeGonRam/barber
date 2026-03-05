<?php

namespace App\Http\Middleware\Role;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasAnyPermission')) {
            abort(403);
        }

        if (! $user->hasAnyPermission($permissions)) {
            abort(403);
        }

        return $next($request);
    }
}
