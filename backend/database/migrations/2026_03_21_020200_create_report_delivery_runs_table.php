<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_delivery_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('report_delivery_schedule_id');
            $table->uuid('report_snapshot_id')->nullable();
            $table->string('delivery_channel')->default('email_stub');
            $table->string('status')->index();
            $table->json('recipients');
            $table->timestamp('prepared_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->uuid('triggered_by')->nullable();
            $table->string('trigger_mode')->default('scheduled')->index();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'prepared_at'], 'report_delivery_runs_workspace_status_idx');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('report_delivery_schedule_id')->references('id')->on('report_delivery_schedules')->cascadeOnDelete();
            $table->foreign('report_snapshot_id')->references('id')->on('report_snapshots')->nullOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_delivery_runs');
    }
};
