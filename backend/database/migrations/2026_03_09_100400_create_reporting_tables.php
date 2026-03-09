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
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id')->nullable();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('request_fingerprint')->nullable()->index();
            $table->json('cursor')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('initiated_by')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('raw_api_payloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('meta_connection_id')->nullable();
            $table->uuid('sync_run_id')->nullable();
            $table->string('resource_type')->index();
            $table->string('resource_key')->nullable()->index();
            $table->json('payload');
            $table->string('payload_hash', 64)->index();
            $table->timestamp('captured_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('meta_connection_id')->references('id')->on('meta_connections')->nullOnDelete();
            $table->foreign('sync_run_id')->references('id')->on('sync_runs')->nullOnDelete();
        });

        Schema::create('insight_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('level')->index();
            $table->uuid('entity_id')->nullable()->index();
            $table->string('entity_external_id')->index();
            $table->date('date')->index();
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->nullable();
            $table->decimal('frequency', 8, 3)->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('link_clicks')->nullable();
            $table->decimal('ctr', 8, 4)->nullable();
            $table->decimal('cpc', 12, 4)->nullable();
            $table->decimal('cpm', 12, 4)->nullable();
            $table->decimal('results', 14, 2)->nullable();
            $table->decimal('cost_per_result', 14, 4)->nullable();
            $table->decimal('leads', 14, 2)->nullable();
            $table->decimal('purchases', 14, 2)->nullable();
            $table->decimal('roas', 14, 4)->nullable();
            $table->decimal('conversions', 14, 2)->nullable();
            $table->json('actions')->nullable();
            $table->string('attribution_setting')->nullable();
            $table->string('source')->default('meta');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'level', 'entity_external_id', 'date', 'source']);
            $table->index(['workspace_id', 'date', 'level']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insight_daily');
        Schema::dropIfExists('raw_api_payloads');
        Schema::dropIfExists('sync_runs');
    }
};
