<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();

            // Relations
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Client Data
            $table->string('client_name');
            $table->string('client_phone');
            $table->string('client_email')->nullable();

            // Negotiation Data
            $table->bigInteger('unit_listed_price')->default(0);
            $table->bigInteger('offered_price')->nullable();
            $table->string('payment_scheme')->nullable(); // cash_keras, cash_bertahap, kpr
            $table->bigInteger('dp_amount')->nullable();
            $table->integer('installment_months')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();

            // Status & Response
            $table->string('status')->default('draft'); // draft, pending, reviewed, counter_offer, approved, rejected, expired
            $table->bigInteger('counter_price')->nullable();
            $table->text('counter_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('client_response')->nullable(); // accepted, rejected, revised
            $table->timestamp('client_response_at')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('created_by');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiations');
    }
};
