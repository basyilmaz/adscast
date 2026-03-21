<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('report_snapshot_id');
            $table->string('label')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('allow_csv_download')->default(false)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_accessed_at')->nullable()->index();
            $table->unsignedInteger('access_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'report_snapshot_id'], 'report_share_links_snapshot_idx');
            $table->index(['workspace_id', 'revoked_at', 'expires_at'], 'report_share_links_status_idx');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('report_snapshot_id')->references('id')->on('report_snapshots')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_share_links');
    }
};
