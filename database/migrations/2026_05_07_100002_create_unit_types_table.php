<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Type 36/72", "2BR Deluxe"
            $table->string('code')->nullable(); // e.g., "T36"
            $table->integer('building_area')->nullable(); // m²
            $table->integer('land_area')->nullable(); // m²
            $table->integer('bedrooms')->default(0);
            $table->integer('bathrooms')->default(0);
            $table->integer('floors')->default(1);
            $table->decimal('base_price', 15, 0)->default(0);
            $table->decimal('current_price', 15, 0)->default(0);
            $table->string('floor_plan_image')->nullable();
            $table->json('specs')->nullable(); // {"pondasi":"Batu Kali","dinding":"Bata Merah",...}
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
