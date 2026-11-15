<?php

use App\Services\InstagramLeadCleanupService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        InstagramLeadCleanupService::cleanup(includeAllInstagramSource: false, dryRun: false);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deleted Instagram webhook leads cannot be restored automatically.
    }
};

