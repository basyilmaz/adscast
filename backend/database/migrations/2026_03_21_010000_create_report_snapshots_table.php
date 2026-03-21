<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('entity_type')->index();
            $table->uuid('entity_id')->nullable()->index();
            $table->string('report_type')->index();
            $table->string('title');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->json('payload');
            $table->uuid('generated_by')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'entity_type', 'entity_id'], 'report_snapshots_workspace_entity_idx');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
