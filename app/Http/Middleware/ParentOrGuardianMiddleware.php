<?php

namespace App\Http\Middleware;

use Closure;

class ParentOrGuardianMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->user()->role_id != 4 && $request->user()->role_id != 5) {
            abort(403, 'Unauthorized action.');
        }
        return $next($request);
    }
}
