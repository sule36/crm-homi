<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Schema;

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
        $user = $request->user();
        $reminders = [];

        if ($user && Schema::hasTable('follow_up_reminders')) {
            try {
                $reminders = \App\Models\FollowUpReminder::with('lead:id,name,phone')
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->orderBy('remind_at', 'asc')
                    ->take(10)
                    ->get();
            } catch (\Throwable $e) {
                $reminders = [];
            }
        }

        $partnerBanks = [];
        if (Schema::hasTable('partner_banks')) {
            try {
                $partnerBanks = \App\Models\PartnerBank::where('is_active', true)->get();
            } catch (\Throwable $e) {
                $partnerBanks = [];
            }
        }

        $activeProject = null;
        if (Schema::hasTable('projects')) {
            try {
                $activeProject = \App\Models\Project::where('status', 'active')->first();
            } catch (\Throwable $e) {
                $activeProject = null;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'reminders' => $reminders,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'api_token' => $request->session()->get('api_token'),
                'negotiation_link' => $request->session()->get('negotiation_link'),
                'negotiation_token' => $request->session()->get('negotiation_token'),
            ],
            'partner_banks' => $partnerBanks,
            'active_project' => $activeProject,
        ];
    }
}
