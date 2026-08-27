<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_balance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('client_name');
            $table->string('company_name')->nullable();
            $table->string('business_type')->default('Perdagangan / Usaha');
            $table->string('phone')->nullable();
            
            // Aktiva Lancar (Current Assets)
            $table->decimal('cash_and_bank', 15, 2)->default(0);
            $table->decimal('inventory', 15, 2)->default(0);
            $table->decimal('receivables', 15, 2)->default(0);
            $table->decimal('other_current_assets', 15, 2)->default(0);

            // Aktiva Tetap (Fixed Assets)
            $table->decimal('equipment', 15, 2)->default(0);
            $table->decimal('vehicles', 15, 2)->default(0);
            $table->decimal('machinery_and_buildings', 15, 2)->default(0);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);

            // Pasiva: Kewajiban / Liabilitas (Liabilities)
            $table->decimal('trade_payables', 15, 2)->default(0);
            $table->decimal('bank_loans', 15, 2)->default(0);
            $table->decimal('other_liabilities', 15, 2)->default(0);

            // Pasiva: Ekuitas (Equity)
            $table->decimal('capital', 15, 2)->default(0);
            $table->decimal('retained_earnings', 15, 2)->default(0);
            $table->decimal('drawings_prive', 15, 2)->default(0);

            // Transaksi & Laba Rugi Bulanan
            $table->decimal('monthly_revenue', 15, 2)->default(0);
            $table->decimal('monthly_net_profit', 15, 2)->default(0);
            $table->decimal('existing_monthly_debt_service', 15, 2)->default(0);

            // Pengajuan KPR Target
            $table->decimal('target_kpr_amount', 15, 2)->default(0);
            $table->integer('target_tenor_years')->default(15);
            $table->decimal('target_interest_rate', 5, 2)->default(5.00);

            // Hasil Rasio & Scoring Bank
            $table->decimal('current_ratio', 8, 2)->default(0);
            $table->decimal('der_ratio', 8, 2)->default(0);
            $table->decimal('dsr_ratio', 8, 2)->default(0);
            $table->enum('approval_score', ['high', 'medium', 'low'])->default('medium');
            $table->json('analysis_summary')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_balance_sheets');
    }
};
