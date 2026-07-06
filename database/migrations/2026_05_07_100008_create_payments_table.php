<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->integer('installment_number');
            $table->string('label')->nullable(); // "Booking Fee", "DP 1", "Cicilan 1", etc.
            $table->decimal('amount', 15, 0)->default(0);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->enum('status', ['upcoming', 'due', 'paid', 'overdue'])->default('upcoming');
            $table->string('payment_proof')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 0)->default(0);
            $table->string('payment_method')->nullable(); // transfer, cash, cheque
            $table->string('bank_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('receipt_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('payment_schedules');
    }
};
