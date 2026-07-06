<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'reminders' => $request->user()
                    ? \App\Models\FollowUpReminder::with('lead:id,name,phone')
                        ->where('user_id', $request->user()->id)
                        ->where('status', 'pending')
                        ->orderBy('remind_at', 'asc')
                        ->take(10)
                        ->get()
                    : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'api_token' => $request->session()->get('api_token'),
            ],
            'partner_banks' => \Illuminate\Support\Facades\Schema::hasTable('partner_banks')
                ? \App\Models\PartnerBank::where('is_active', true)->get()
                : [],
        ];
    }
}
