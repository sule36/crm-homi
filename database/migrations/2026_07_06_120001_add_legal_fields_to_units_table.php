<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->enum('certificate_status', [
                'belum_pecah',
                'pecah_di_notaris',
                'sudah_balik_nama',
                'diserahkan_ke_konsumen',
                'diserahkan_ke_bank'
            ])->default('belum_pecah')->after('notes');
            $table->string('certificate_number')->nullable()->after('certificate_status');
            $table->string('imb_number')->nullable()->after('certificate_number'); //PBG / IMB
            $table->string('pbb_number')->nullable()->after('imb_number');
            $table->text('legal_notes')->nullable()->after('pbb_number');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_status',
                'certificate_number',
                'imb_number',
                'pbb_number',
                'legal_notes'
            ]);
        });
    }
};
