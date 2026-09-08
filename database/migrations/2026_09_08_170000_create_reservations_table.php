<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('negotiation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('client_name');
            $table->string('client_phone');
            $table->string('client_email')->nullable();
            $table->string('client_nik')->nullable();

            $table->unsignedBigInteger('amount')->default(0);
            $table->string('payment_method')->default('transfer');
            $table->string('payment_proof')->nullable();

            $table->string('status')->default('active'); // active, converted, refunded, cancelled
            $table->string('refundable_policy')->default('100% Refundable (Garansi Pengembalian Utuh)');

            $table->foreignId('agent_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_coordinator_name')->nullable();
            $table->string('agent_coordinator_title')->nullable();

            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->dateTime('refund_date')->nullable();
            $table->string('refund_bank_name')->nullable();
            $table->string('refund_account_number')->nullable();
            $table->string('refund_account_name')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
