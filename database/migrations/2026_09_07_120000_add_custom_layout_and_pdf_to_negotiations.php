<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            if (!Schema::hasColumn('negotiations', 'custom_layout_options')) {
                $table->json('custom_layout_options')->nullable()->after('special_requests');
            }
            if (!Schema::hasColumn('negotiations', 'custom_layout_notes')) {
                $table->text('custom_layout_notes')->nullable()->after('custom_layout_options');
            }
            if (!Schema::hasColumn('negotiations', 'client_signature')) {
                $table->longText('client_signature')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('negotiations', 'pdf_generated_at')) {
                $table->timestamp('pdf_generated_at')->nullable()->after('expired_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('negotiations', 'custom_layout_options')) $columns[] = 'custom_layout_options';
            if (Schema::hasColumn('negotiations', 'custom_layout_notes')) $columns[] = 'custom_layout_notes';
            if (Schema::hasColumn('negotiations', 'client_signature')) $columns[] = 'client_signature';
            if (Schema::hasColumn('negotiations', 'pdf_generated_at')) $columns[] = 'pdf_generated_at';
            
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
