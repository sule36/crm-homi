<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (!Schema::hasColumn('negotiations', 'negotiation_number')) {
                $table->string('negotiation_number')->nullable()->after('token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (Schema::hasColumn('negotiations', 'negotiation_number')) {
                $table->dropColumn('negotiation_number');
            }
        });
    }
};
