<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('status');
            $table->index('assigned_to');
            $table->index('project_id');
            $table->index('source');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->index('status');
            $table->index('project_id');
            $table->index('unit_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['source']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['unit_type_id']);
        });
    }
};
