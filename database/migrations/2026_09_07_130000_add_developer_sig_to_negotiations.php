<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (!Schema::hasColumn('negotiations', 'developer_sig_name')) {
                $table->string('developer_sig_name')->nullable()->after('client_signature');
            }
            if (!Schema::hasColumn('negotiations', 'developer_sig_title')) {
                $table->string('developer_sig_title')->nullable()->after('developer_sig_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('negotiations', 'developer_sig_name')) $columns[] = 'developer_sig_name';
            if (Schema::hasColumn('negotiations', 'developer_sig_title')) $columns[] = 'developer_sig_title';
            
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
