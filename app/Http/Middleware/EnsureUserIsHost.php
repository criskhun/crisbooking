<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'host' || $request->user()?->is_admin, 403, 'Host access is required.');

        return $next($request);
    }
}
