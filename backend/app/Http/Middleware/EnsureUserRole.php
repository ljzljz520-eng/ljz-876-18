<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 基于 Sanctum 已登录用户的简单角色校验。
 * 用法：->middleware('role:admin,teacher')
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json(['message' => '无权访问'], 403);
        }

        return $next($request);
    }
}
