<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->decimal('interest_rate_fixed', 5, 2)->default(5.00);
            $table->decimal('interest_rate_floating', 5, 2)->default(11.00);
            $table->integer('fixed_duration')->default(3);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_syariah')->default(false);
            $table->decimal('syariah_margin_rate', 5, 2)->default(6.50);
            $table->boolean('is_tiered')->default(false);
            $table->json('tiered_rates')->nullable();
            $table->boolean('show_on_homepage')->default(true);
            $table->boolean('show_in_calculator')->default(true);
            $table->timestamps();
        });

        // Seed default Indonesian partner banks
        \DB::table('partner_banks')->insert([
            [
                'name' => 'BCA (Bank Central Asia)',
                'logo' => null,
                'interest_rate_fixed' => 3.85,
                'interest_rate_floating' => 11.00,
                'fixed_duration' => 3,
                'is_active' => true,
                'is_syariah' => false,
                'syariah_margin_rate' => 0.00,
                'is_tiered' => true,
                'tiered_rates' => json_encode([
                    ['rate' => 3.85, 'years' => 3],
                    ['rate' => 6.85, 'years' => 3],
                    ['rate' => 8.85, 'years' => 4]
                ]),
                'show_on_homepage' => true,
                'show_in_calculator' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank Mandiri',
                'logo' => null,
                'interest_rate_fixed' => 3.99,
                'interest_rate_floating' => 11.50,
                'fixed_duration' => 3,
                'is_active' => true,
                'is_syariah' => false,
                'syariah_margin_rate' => 0.00,
                'is_tiered' => true,
                'tiered_rates' => json_encode([
                    ['rate' => 3.99, 'years' => 3],
                    ['rate' => 7.25, 'years' => 3],
                    ['rate' => 9.25, 'years' => 4]
                ]),
                'show_on_homepage' => true,
                'show_in_calculator' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank BTN (Bank Tabungan Negara)',
                'logo' => null,
                'interest_rate_fixed' => 4.99,
                'interest_rate_floating' => 12.50,
                'fixed_duration' => 2,
                'is_active' => true,
                'is_syariah' => false,
                'syariah_margin_rate' => 0.00,
                'is_tiered' => false,
                'tiered_rates' => null,
                'show_on_homepage' => true,
                'show_in_calculator' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'BSI (Bank Syariah Indonesia)',
                'logo' => null,
                'interest_rate_fixed' => 0.00,
                'interest_rate_floating' => 0.00,
                'fixed_duration' => 0,
                'is_active' => true,
                'is_syariah' => true,
                'syariah_margin_rate' => 6.50,
                'is_tiered' => false,
                'tiered_rates' => null,
                'show_on_homepage' => true,
                'show_in_calculator' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_banks');
    }
};
