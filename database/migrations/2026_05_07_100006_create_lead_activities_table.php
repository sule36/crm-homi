<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['call', 'whatsapp', 'email', 'visit', 'meeting', 'note', 'status_change']);
            $table->text('description')->nullable();
            $table->json('attachments')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('follow_up_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('remind_at');
            $table->string('message')->nullable();
            $table->enum('status', ['pending', 'completed', 'overdue'])->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_reminders');
        Schema::dropIfExists('lead_activities');
    }
};
