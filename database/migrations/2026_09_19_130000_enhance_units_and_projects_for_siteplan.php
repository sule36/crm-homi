<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'promo')) {
                $table->string('promo')->nullable()->after('final_price');
            }
            if (!Schema::hasColumn('units', 'discount_amount')) {
                $table->unsignedBigInteger('discount_amount')->default(0)->after('promo');
            }
            if (!Schema::hasColumn('units', 'carport')) {
                $table->integer('carport')->default(1)->after('discount_amount');
            }
            if (!Schema::hasColumn('units', 'net_price')) {
                $table->unsignedBigInteger('net_price')->nullable()->after('carport');
            }
            if (!Schema::hasColumn('units', 'commission_notes')) {
                $table->string('commission_notes')->nullable()->after('net_price');
            }
            if (!Schema::hasColumn('units', 'management_notes')) {
                $table->text('management_notes')->nullable()->after('commission_notes');
            }
            if (!Schema::hasColumn('units', 'siteplan_coordinates')) {
                $table->json('siteplan_coordinates')->nullable()->after('management_notes');
            }
        });

        // Change units status column to string to safely support 'reserved' and 'hold'
        Schema::table('units', function (Blueprint $table) {
            $table->string('status')->default('available')->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'siteplan_image')) {
                $table->string('siteplan_image')->nullable()->after('master_plan_image');
            }
            if (!Schema::hasColumn('projects', 'siteplan_config')) {
                $table->json('siteplan_config')->nullable()->after('siteplan_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'promo',
                'discount_amount',
                'carport',
                'net_price',
                'commission_notes',
                'management_notes',
                'siteplan_coordinates',
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['siteplan_image', 'siteplan_config']);
        });
    }
};
