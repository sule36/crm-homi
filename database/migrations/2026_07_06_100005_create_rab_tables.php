<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // tanah, pondasi, struktur, dinding, atap, mep_listrik, mep_plumbing, finishing_interior, finishing_exterior, infrastruktur, perizinan, overhead, lain_lain
            $table->string('sub_category')->nullable();
            $table->string('description');
            $table->string('unit')->default('ls'); // m², m³, unit, ls, kg, batang, etc.
            $table->decimal('volume', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 0)->default(0);
            $table->decimal('total_price', 15, 0)->default(0); // volume × unit_price
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'category']);
        });

        Schema::create('rab_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rab_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 0)->default(0);
            $table->date('realization_date');
            $table->string('vendor_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('rab_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_realizations');
        Schema::dropIfExists('rab_items');
    }
};
