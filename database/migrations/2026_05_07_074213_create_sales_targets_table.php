<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('month'); // YYYY-MM
            $table->decimal('target_revenue', 15, 0)->default(0);
            $table->integer('target_units')->default(0);
            $table->integer('target_leads')->default(0);
            $table->integer('achieved_units')->default(0);
            $table->decimal('achieved_revenue', 15, 0)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
