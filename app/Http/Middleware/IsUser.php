<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (!$user || !$user->isUser()) {
            abort(403, 'Halaman ini khusus akun penonton.');
        }

        return $next($request);
    }
}
