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
        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('repository_url')->nullable();
            $table->string('documentation_path')->default('docs');
            $table->string('default_branch')->default('main');
            $table->timestamps();

            $table->unique(['community_id', 'slug']);
            $table->index(['community_id', 'name']);
        });

        Schema::create('plugin_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('git_ref')->nullable();
            $table->text('changelog')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->timestamps();

            $table->unique(['plugin_id', 'version']);
            $table->index(['plugin_id', 'is_latest']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_version_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('title');
            $table->json('frontmatter')->nullable();
            $table->longText('content_raw');
            $table->longText('content_html');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plugin_version_id', 'path']);
            $table->index(['plugin_version_id', 'sort_order']);
        });

        Schema::create('commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('syntax')->nullable();
            $table->text('description')->nullable();
            $table->json('aliases')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->unique(['plugin_version_id', 'slug']);
            $table->index(['plugin_version_id', 'name']);
            $table->index('category_id');
        });

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('favoritable');
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_user_favoritable_unique');
        });

        Schema::create('command_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('command_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['command_id', 'viewed_at']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('ingestion_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_version_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->json('stats')->nullable();
            $table->json('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['plugin_version_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
        Schema::dropIfExists('command_views');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('commands');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('plugin_versions');
        Schema::dropIfExists('plugins');
    }
};
