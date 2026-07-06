<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_type_id')->constrained()->cascadeOnDelete();
            $table->string('block')->nullable(); // e.g., "A", "B"
            $table->string('number'); // e.g., "01", "12A"
            $table->integer('floor')->nullable(); // for apartment
            $table->enum('status', ['available', 'hold', 'booked', 'sold', 'cancelled'])->default('available');
            $table->string('facing_direction')->nullable(); // Utara, Selatan, Timur, Barat
            $table->decimal('premium_charge', 15, 0)->default(0); // extra charge for corner/hook
            $table->decimal('final_price', 15, 0)->default(0);
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'block', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
