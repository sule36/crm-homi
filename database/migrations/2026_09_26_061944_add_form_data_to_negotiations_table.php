<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (!Schema::hasColumn('negotiations', 'form_data')) {
                $table->json('form_data')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (Schema::hasColumn('negotiations', 'form_data')) {
                $table->dropColumn('form_data');
            }
        });
    }
};
