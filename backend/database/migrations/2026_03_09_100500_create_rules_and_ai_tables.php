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
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('entity_type')->index();
            $table->uuid('entity_id')->nullable()->index();
            $table->string('code')->index();
            $table->string('severity')->default('medium')->index();
            $table->string('summary');
            $table->text('explanation')->nullable();
            $table->text('recommended_action')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->date('date_detected')->index();
            $table->string('source_rule_version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('alert_id')->nullable();
            $table->string('target_type')->index();
            $table->uuid('target_id')->nullable()->index();
            $table->string('summary');
            $table->text('details')->nullable();
            $table->string('action_type')->nullable();
            $table->string('priority')->default('medium')->index();
            $table->string('status')->default('open')->index();
            $table->string('source')->default('rules');
            $table->timestamp('generated_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('alert_id')->references('id')->on('alerts')->nullOnDelete();
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('entity_type')->index();
            $table->uuid('entity_id')->nullable()->index();
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_template');
            $table->json('prompt_context')->nullable();
            $table->longText('prompt_text')->nullable();
            $table->json('output');
            $table->string('status')->default('succeeded')->index();
            $table->json('token_usage')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->timestamp('generated_at')->index();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('alerts');
    }
};
