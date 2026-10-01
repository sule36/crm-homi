<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table for Daily In-House Duty Schedules (Piket Jaga Harian Kantor Pemasaran)
        if (!Schema::hasTable('project_duty_schedules')) {
            Schema::create('project_duty_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('duty_date')->index();
                $table->string('shift')->default('full_day'); // full_day, pagi, siang
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'project_id', 'duty_date']);
            });
        }

        // 2. Add inhouse_pic_id to leads table for external broker co-handling
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'inhouse_pic_id')) {
                $table->foreignId('inhouse_pic_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            }
        });

        // 3. Add inhouse_pic_id to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'inhouse_pic_id')) {
                $table->foreignId('inhouse_pic_id')->nullable()->after('booked_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'inhouse_pic_id')) {
                $table->dropForeign(['inhouse_pic_id']);
                $table->dropColumn('inhouse_pic_id');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'inhouse_pic_id')) {
                $table->dropForeign(['inhouse_pic_id']);
                $table->dropColumn('inhouse_pic_id');
            }
        });

        Schema::dropIfExists('project_duty_schedules');
    }
};
