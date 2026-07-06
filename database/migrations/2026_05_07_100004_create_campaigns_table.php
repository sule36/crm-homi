<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('platform', ['facebook', 'instagram', 'google', 'tiktok', 'offline', 'referral', 'other'])->default('other');
            $table->decimal('budget', 15, 0)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->integer('leads_count')->default(0);
            $table->integer('conversions_count')->default(0);
            $table->decimal('cost_per_lead', 15, 0)->default(0);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
