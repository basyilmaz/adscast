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
        Schema::create('creatives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_ad_account_id')->nullable();
            $table->string('meta_creative_id')->nullable();
            $table->string('name')->nullable();
            $table->string('asset_type')->nullable();
            $table->text('body')->nullable();
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('call_to_action')->nullable();
            $table->text('destination_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_creative_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_ad_account_id')->references('id')->on('meta_ad_accounts')->nullOnDelete();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_ad_account_id');
            $table->string('meta_campaign_id')->nullable();
            $table->string('name');
            $table->string('objective')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('effective_status')->nullable();
            $table->string('buying_type')->nullable();
            $table->decimal('daily_budget', 14, 2)->nullable();
            $table->decimal('lifetime_budget', 14, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_campaign_id']);
            $table->index(['workspace_id', 'name']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_ad_account_id')->references('id')->on('meta_ad_accounts')->cascadeOnDelete();
        });

        Schema::create('ad_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('campaign_id');
            $table->string('meta_ad_set_id')->nullable();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->string('effective_status')->nullable();
            $table->string('optimization_goal')->nullable();
            $table->string('billing_event')->nullable();
            $table->string('bid_strategy')->nullable();
            $table->decimal('daily_budget', 14, 2)->nullable();
            $table->decimal('lifetime_budget', 14, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->json('targeting')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_ad_set_id']);
            $table->index(['workspace_id', 'campaign_id']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('campaign_id');
            $table->uuid('ad_set_id');
            $table->uuid('creative_id')->nullable();
            $table->string('meta_ad_id')->nullable();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->string('effective_status')->nullable();
            $table->text('preview_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_ad_id']);
            $table->index(['workspace_id', 'campaign_id', 'ad_set_id']);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('ad_set_id')->references('id')->on('ad_sets')->cascadeOnDelete();
            $table->foreign('creative_id')->references('id')->on('creatives')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_sets');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('creatives');
    }
};
