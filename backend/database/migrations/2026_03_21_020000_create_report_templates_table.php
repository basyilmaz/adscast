<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->string('entity_type')->index();
            $table->uuid('entity_id')->index();
            $table->string('report_type')->index();
            $table->unsignedTinyInteger('default_range_days')->default(30);
            $table->string('layout_preset')->default('standard');
            $table->json('configuration')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'entity_type', 'entity_id'], 'report_templates_workspace_entity_idx');
            $table->index(['workspace_id', 'is_active'], 'report_templates_workspace_active_idx');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
