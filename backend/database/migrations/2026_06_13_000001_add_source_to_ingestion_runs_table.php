<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingestion_runs', function (Blueprint $table): void {
            $table->string('source')->default('manual')->after('plugin_version_id');
            $table->index(['plugin_version_id', 'status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('ingestion_runs', function (Blueprint $table): void {
            $table->dropIndex(['plugin_version_id', 'status', 'source']);
            $table->dropColumn('source');
        });
    }
};
