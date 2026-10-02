<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table for Agent Site Visits (Buku Tamu / Pencatatan Kedatangan Agent untuk PK, Konten, dll).
     */
    public function up(): void
    {
        if (!Schema::hasTable('agent_site_visits')) {
            Schema::create('agent_site_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('duty_schedule_id')->nullable()->constrained('project_duty_schedules')->nullOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); // Petugas inhouse yang mencatat
                $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete(); // Jika agen sudah terdaftar di CRM
                $table->foreignId('broker_company_id')->nullable()->constrained('broker_companies')->nullOnDelete();
                
                // Agent identification
                $table->string('agent_name');
                $table->string('agent_phone')->nullable();
                $table->string('agency_name')->nullable(); // e.g. Brighton, ERA, Xavier Marks, Freelance
                $table->string('agent_type')->nullable(); // inhouse, agency_agent, master_lead_sub_agent, independent, external
                
                // Visit details
                $table->date('visit_date')->index();
                $table->string('visit_time', 10)->default('10:00'); // e.g. '14:30'
                $table->string('purpose', 50)->default('pk')->index(); // 'pk', 'konten', 'survey_klien', 'koordinasi', 'lainnya'
                $table->unsignedInteger('visitor_count')->default(1);
                $table->text('notes')->nullable();
                $table->string('documentation_url')->nullable(); // link video / foto / drive
                $table->string('status', 30)->default('completed'); // completed, in_progress, cancelled

                $table->timestamps();

                $table->index(['company_id', 'project_id', 'visit_date']);
                $table->index(['project_id', 'visit_date', 'purpose']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_site_visits');
    }
};
