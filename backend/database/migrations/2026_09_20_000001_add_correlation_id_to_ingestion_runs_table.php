<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingestion_runs', function (Blueprint $table): void {
            $table->string('correlation_id')->nullable()->after('source');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ingestion_runs', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
