<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure leads.status is VARCHAR(50) so 'reservation' status is 100% supported on MySQL
        try {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'new'");
        } catch (\Throwable $e) {
            try {
                Schema::table('leads', function (Blueprint $table) {
                    $table->string('status', 50)->default('new')->change();
                });
            } catch (\Throwable $e2) {
                // Ignore if not supported in local sqlite
            }
        }

        // 2. Ensure units.status is VARCHAR(50) so 'reserved' status is 100% supported on MySQL
        try {
            DB::statement("ALTER TABLE units MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'available'");
        } catch (\Throwable $e) {
            try {
                Schema::table('units', function (Blueprint $table) {
                    $table->string('status', 50)->default('available')->change();
                });
            } catch (\Throwable $e2) {
                // Ignore if not supported in local sqlite
            }
        }

        // 3. Ensure reservations table exists and has all full columns
        if (!Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table) {
                $table->id();
                $table->string('reservation_number')->unique();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('negotiation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->string('client_name');
                $table->string('client_phone');
                $table->string('client_email')->nullable();
                $table->string('client_nik')->nullable();

                $table->unsignedBigInteger('amount')->default(0);
                $table->string('payment_method')->default('transfer');
                $table->string('payment_proof')->nullable();

                $table->string('status')->default('active'); // active, converted, refunded, cancelled
                $table->string('refundable_policy')->default('100% Refundable (Garansi Pengembalian Utuh)');
                $table->string('company_name')->nullable();
                $table->string('receipt_title')->nullable();
                $table->string('city')->nullable();
                $table->text('terms_text')->nullable();
                $table->string('policy_title')->nullable();
                $table->text('policy_text')->nullable();
                $table->json('custom_overrides')->nullable();

                $table->foreignId('agent_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('agent_coordinator_name')->nullable();
                $table->string('agent_coordinator_title')->nullable();

                $table->unsignedBigInteger('refund_amount')->nullable();
                $table->dateTime('refund_date')->nullable();
                $table->string('refund_bank_name')->nullable();
                $table->string('refund_account_number')->nullable();
                $table->string('refund_account_name')->nullable();
                $table->text('refund_reason')->nullable();
                $table->string('refund_proof')->nullable();
                $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();

                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->dateTime('expires_at')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('reservations', function (Blueprint $table) {
                if (!Schema::hasColumn('reservations', 'company_name')) {
                    $table->string('company_name')->nullable()->after('refundable_policy');
                }
                if (!Schema::hasColumn('reservations', 'receipt_title')) {
                    $table->string('receipt_title')->nullable()->after('company_name');
                }
                if (!Schema::hasColumn('reservations', 'city')) {
                    $table->string('city')->nullable()->after('receipt_title');
                }
                if (!Schema::hasColumn('reservations', 'terms_text')) {
                    $table->text('terms_text')->nullable()->after('city');
                }
                if (!Schema::hasColumn('reservations', 'policy_title')) {
                    $table->string('policy_title')->nullable()->after('terms_text');
                }
                if (!Schema::hasColumn('reservations', 'policy_text')) {
                    $table->text('policy_text')->nullable()->after('policy_title');
                }
                if (!Schema::hasColumn('reservations', 'custom_overrides')) {
                    $table->json('custom_overrides')->nullable()->after('policy_text');
                }
                if (!Schema::hasColumn('reservations', 'agent_coordinator_id')) {
                    $table->foreignId('agent_coordinator_id')->nullable()->after('custom_overrides');
                }
                if (!Schema::hasColumn('reservations', 'agent_coordinator_name')) {
                    $table->string('agent_coordinator_name')->nullable()->after('agent_coordinator_id');
                }
                if (!Schema::hasColumn('reservations', 'agent_coordinator_title')) {
                    $table->string('agent_coordinator_title')->nullable()->after('agent_coordinator_name');
                }
            });
        }

        // 4. Ensure audit_logs has description
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('audit_logs', 'description')) {
                    $table->text('description')->nullable()->after('action');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op rollback
    }
};
