<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->integer('progress_percentage')->default(0); // 0 sampai 100
            $table->string('description'); // e.g. "Pemasangan Fondasi", "Plesteran Dinding", "Atap Terpasang"
            $table->text('notes')->nullable();
            $table->date('recorded_date');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'recorded_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_progress');
    }
};
