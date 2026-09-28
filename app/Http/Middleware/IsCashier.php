<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Halaman loket hanya untuk akun dengan role 'cashier'.
class IsCashier
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isCashier()) {
            abort(403, 'Akses ditolak. Halaman ini khusus petugas kasir.');
        }

        return $next($request);
    }
}
