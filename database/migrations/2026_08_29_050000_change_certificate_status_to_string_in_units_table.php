<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE units MODIFY certificate_status VARCHAR(100) NULL DEFAULT 'belum_pecah'");
            } else {
                $table->string('certificate_status')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE units MODIFY certificate_status ENUM('belum_pecah', 'pecah_di_notaris', 'sudah_balik_nama', 'diserahkan_ke_konsumen', 'diserahkan_ke_bank') NULL DEFAULT 'belum_pecah'");
            }
        });
    }
};
