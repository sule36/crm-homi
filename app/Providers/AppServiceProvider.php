<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        \Illuminate\Support\Facades\Route::bind('booking', function ($value) {
            return \App\Models\Booking::withTrashed()
                ->where('id', $value)
                ->orWhere('spk_number', $value)
                ->firstOrFail();
        });

        // Self-healing migration for production deployment (Hostinger/VPS)
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('project_duty_schedules') || 
                !\Illuminate\Support\Facades\Schema::hasColumn('leads', 'inhouse_pic_id') ||
                !\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'inhouse_pic_id')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AppServiceProvider auto-migrate failed: ' . $e->getMessage());
        }
    }
}
