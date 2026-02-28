<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    /**
     * Usage in routes: middleware('role:eic') or middleware('role:editor,eic')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Forbidden. Insufficient role.'], 403);
        }

        return $next($request);
    }
}
