<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Jika user adalah Pemilik Platform SaaS (Super Admin tanpa company_id),
        // blokir dari data operasional rahasia milik Developer dan arahkan ke SaaS Control Tower
        if ($user->company_id === null && ($user->hasRole('super_admin') || $user->email === 'admin@homi.id')) {
            return redirect()->route('super-admin.companies.index')
                ->with('error', 'Akses Dibatasi: Sebagai Pemilik Platform SaaS, Anda bertugas mengelola akun developer & tagihan, dan tidak diperkenankan mengakses data operasional rahasia milik Developer.');
        }

        return $next($request);
    }
}
