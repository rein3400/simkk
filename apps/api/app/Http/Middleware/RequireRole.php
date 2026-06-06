<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Sesi tidak valid.'], 401);
        }
        if (!in_array($user->level, $roles, true)) {
            return response()->json(['message' => 'Role tidak memiliki akses ke aksi ini.'], 403);
        }
        return $next($request);
    }
}
