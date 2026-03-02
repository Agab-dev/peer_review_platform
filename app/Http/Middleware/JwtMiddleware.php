<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'User not found.'], 401);
            }

        } catch (TokenExpiredException) {
            return response()->json(['message' => 'Token has expired.'], 401);
        } catch (TokenInvalidException) {
            return response()->json(['message' => 'Token is invalid.'], 401);
        } catch (JWTException) {
            return response()->json(['message' => 'Token not provided.'], 401);
        }

        return $next($request);
    }
}
