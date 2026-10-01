<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->hasRole('super_admin') || $user->email === 'admin@homi.id')) {
            abort(403, 'Akses Ditolak: Halaman ini khusus untuk Pemilik Platform SaaS.');
        }

        return $next($request);
    }
}
