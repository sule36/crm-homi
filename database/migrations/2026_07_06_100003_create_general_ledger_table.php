<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_ledger', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('type', ['income', 'expense']);
            $table->string('category'); // customer_payment, operational_expense, salary, commission, rab_realization
            $table->string('reference_type')->nullable(); // Polymorphic: App\Models\Transaction, App\Models\Expense, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('debit', 15, 0)->default(0);  // uang keluar
            $table->decimal('credit', 15, 0)->default(0); // uang masuk
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'type']);
            $table->index(['category']);
            $table->index(['project_id', 'date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledger');
    }
};
