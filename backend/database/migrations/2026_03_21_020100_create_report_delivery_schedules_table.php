<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_delivery_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('report_template_id');
            $table->string('delivery_channel')->default('email_stub');
            $table->string('cadence')->index();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->string('send_time', 5);
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->json('recipients');
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->string('last_status')->nullable()->index();
            $table->uuid('last_report_snapshot_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active', 'next_run_at'], 'report_delivery_schedules_due_idx');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('report_template_id')->references('id')->on('report_templates')->cascadeOnDelete();
            $table->foreign('last_report_snapshot_id')->references('id')->on('report_snapshots')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_delivery_schedules');
    }
};
