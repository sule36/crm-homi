<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broker_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(2.50); // percentage
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('identity_number')->nullable(); // KTP - encrypted
            $table->enum('source', ['facebook', 'instagram', 'google', 'tiktok', 'walk_in', 'referral', 'broker', 'website', 'other'])->default('other');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broker_company_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['new', 'contacted', 'visited', 'negotiation', 'booking', 'won', 'lost'])->default('new');
            $table->integer('score')->default(0); // 0-100
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // {"budget":"500jt-1M","type":"2BR","location":"dekat sekolah"}
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_to']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('broker_companies');
    }
};
