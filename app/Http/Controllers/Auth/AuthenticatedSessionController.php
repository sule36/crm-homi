<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $portal = $request->query('portal', 'developer');
        if (in_array($portal, ['owner', 'saas', 'admin', 'super-admin'])) {
            $portal = 'owner';
        } else {
            $portal = 'developer';
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'initialPortal' => $portal,
        ]);
    }

    /**
     * Display the Super Admin / SaaS Owner login view.
     */
    public function createSuperAdmin(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'initialPortal' => 'owner',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Redirect Super Admin / Pemilik Platform SaaS langsung ke Control Tower
        if ($user && ($user->hasRole('super_admin') || ($user->email === 'admin@homi.id' && !$user->company_id))) {
            return redirect()->intended(route('super-admin.companies.index'));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
