<?php

namespace App\Http\Middleware;
use Closure;

class SchoolOrParentMiddleware
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
        if ($request->user()->role_id != 2 && $request->user()->role_id != 4) {
            abort(403, 'Unauthorized action.');
        }
        return $next($request);
    }
}
