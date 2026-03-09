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
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('provider')->default('meta');
            $table->string('api_version')->default('v20.0');
            $table->string('status')->default('active')->index();
            $table->string('external_user_id')->nullable();
            $table->longText('access_token_encrypted');
            $table->longText('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable()->index();
            $table->json('scopes')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'provider']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        Schema::create('meta_businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id');
            $table->string('business_id');
            $table->string('name');
            $table->string('verification_status')->nullable();
            $table->uuid('raw_snapshot_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'business_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->cascadeOnDelete();
        });

        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id');
            $table->uuid('meta_business_id')->nullable();
            $table->string('account_id');
            $table->string('name');
            $table->string('currency', 8)->nullable();
            $table->string('timezone_name')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'account_id']);
            $table->index(['workspace_id', 'name']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->cascadeOnDelete();
            $table->foreign('meta_business_id')->references('id')->on('meta_businesses')->nullOnDelete();
        });

        Schema::create('meta_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id');
            $table->string('page_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->longText('access_token_encrypted')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'page_id']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->cascadeOnDelete();
        });

        Schema::create('meta_pixels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id');
            $table->string('pixel_id');
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'pixel_id']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_pixels');
        Schema::dropIfExists('meta_pages');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('meta_businesses');
        Schema::dropIfExists('meta_connections');
    }
};
