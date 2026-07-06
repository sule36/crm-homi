<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., "CGV" for Citraland Grand View
            $table->text('description')->nullable();
            $table->string('location');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('total_units')->default(0);
            $table->integer('sold_units')->default(0);
            $table->integer('booked_units')->default(0);
            $table->integer('available_units')->default(0);
            $table->decimal('price_range_min', 15, 0)->nullable();
            $table->decimal('price_range_max', 15, 0)->nullable();
            $table->string('master_plan_image')->nullable();
            $table->string('brochure_file')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['upcoming', 'active', 'completed'])->default('active');
            $table->json('amenities')->nullable(); // ["Kolam Renang", "Gym", "Taman"]
            $table->json('settings')->nullable(); // project-specific configs
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
