<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_lead_invoices')) {
            Schema::create('master_lead_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->foreignId('master_lead_id')->constrained('users')->onDelete('cascade');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('submitted'); // submitted, approved, paid, cancelled
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_proof')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('commissions', 'master_lead_invoice_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->foreignId('master_lead_invoice_id')->nullable()->constrained('master_lead_invoices')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commissions', 'master_lead_invoice_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropForeign(['master_lead_invoice_id']);
                $table->dropColumn('master_lead_invoice_id');
            });
        }
        Schema::dropIfExists('master_lead_invoices');
    }
};
