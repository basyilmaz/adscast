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
        Schema::create('campaign_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_ad_account_id')->nullable();
            $table->string('objective');
            $table->text('product_service');
            $table->text('target_audience');
            $table->string('location')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->text('offer')->nullable();
            $table->text('landing_page_url')->nullable();
            $table->string('tone_style')->nullable();
            $table->string('existing_creative_availability')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft')->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->json('publish_response_metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_ad_account_id')->references('id')->on('meta_ad_accounts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('campaign_draft_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_draft_id');
            $table->string('item_type')->index();
            $table->string('title')->nullable();
            $table->json('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['campaign_draft_id', 'item_type']);
            $table->foreign('campaign_draft_id')->references('id')->on('campaign_drafts')->cascadeOnDelete();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('approvable_type')->index();
            $table->uuid('approvable_id')->index();
            $table->string('status')->default('draft')->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('publish_response_metadata')->nullable();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('campaign_draft_items');
        Schema::dropIfExists('campaign_drafts');
    }
};
