<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('contractor_name');
            $table->string('contract_number')->unique();
            $table->string('description');
            $table->decimal('total_amount', 15, 0)->default(0);
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::create('contractor_termins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rab_item_id')->nullable()->constrained('rab_items')->nullOnDelete();
            $table->string('label'); // e.g. Termin 1 / DP 20%
            $table->decimal('percentage', 5, 2)->default(0); // percentage value, e.g. 20.00
            $table->decimal('amount', 15, 0)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();

            $table->index('contractor_contract_id');
            $table->index('rab_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_termins');
        Schema::dropIfExists('contractor_contracts');
    }
};
