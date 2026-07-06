<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 0)->default(0);
            $table->decimal('transport_allowance', 15, 0)->default(0);
            $table->decimal('meal_allowance', 15, 0)->default(0);
            $table->decimal('position_allowance', 15, 0)->default(0);
            $table->decimal('other_allowance', 15, 0)->default(0);
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->decimal('basic_salary', 15, 0)->default(0);
            $table->decimal('total_allowances', 15, 0)->default(0);
            $table->decimal('total_deductions', 15, 0)->default(0);
            $table->decimal('bonus', 15, 0)->default(0);
            $table->decimal('overtime', 15, 0)->default(0);
            $table->decimal('net_salary', 15, 0)->default(0);
            $table->enum('status', ['draft', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method')->default('transfer');
            $table->string('bank_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_month', 'period_year']);
            $table->index(['period_year', 'period_month']);
        });

        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // bpjs_kesehatan, bpjs_tk, pinjaman, potongan_lain, pph21
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 0)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('employee_salaries');
    }
};
