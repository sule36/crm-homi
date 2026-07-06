<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('spk_number')->unique(); // Auto-generated SPK-2026-0001
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booked_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('booking_fee', 15, 0)->default(0);
            $table->decimal('unit_price', 15, 0)->default(0);
            $table->decimal('discount_amount', 15, 0)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('final_price', 15, 0)->default(0);
            $table->date('booking_date');
            $table->enum('payment_scheme', ['cash', 'cash_installment', 'kpr'])->default('kpr');
            $table->string('bank_name')->nullable(); // for KPR
            $table->enum('kpr_status', ['pending', 'submitted', 'analyzing', 'approved', 'rejected', 'akad'])->nullable();
            $table->integer('installment_months')->nullable(); // for cash_installment
            $table->decimal('dp_amount', 15, 0)->default(0);
            $table->integer('dp_installment_months')->nullable();
            $table->enum('status', ['pending', 'approved', 'cancelled', 'converted'])->default('pending');
            $table->string('cancelled_reason')->nullable();
            $table->string('spk_file')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
