<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('discord_id')->nullable()->unique()->after('email');
            $table->string('username')->nullable()->after('discord_id');
            $table->string('avatar')->nullable()->after('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['discord_id']);
            $table->dropColumn(['discord_id', 'username', 'avatar']);
        });
    }
};
