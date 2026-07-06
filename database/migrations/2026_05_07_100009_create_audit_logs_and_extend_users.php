<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // created, updated, deleted, login, export
            $table->text('description')->nullable();
            $table->string('auditable_type'); // App\Models\Lead, etc.
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });

        // Add extra columns to users table for CRM
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->foreignId('project_id')->nullable()->after('avatar')->constrained()->nullOnDelete(); // assigned project
            $table->enum('status', ['active', 'inactive'])->default('active')->after('project_id');
            $table->integer('lead_capacity')->default(20)->after('status'); // max active leads
            $table->json('settings')->nullable()->after('lead_capacity');
            $table->timestamp('last_login_at')->nullable()->after('settings');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['phone', 'avatar', 'project_id', 'status', 'lead_capacity', 'settings', 'last_login_at', 'deleted_at']);
        });
    }
};
