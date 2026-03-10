<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create new tables
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('conversation_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('agent_conversations')
                ->nullOnDelete();
        });

        Schema::create('workspace_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('github');
            $table->string('source_reference');
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'source_reference']);
        });

        Schema::create('agent_workspace', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'workspace_id']);
        });

        Schema::create('skill_workspace', function (Blueprint $table) {
            $table->foreignUuid('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->primary(['skill_id', 'workspace_id']);
        });

        // Rewire plans: replace work_item_id with workspace_id
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['work_item_id']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('work_item_id');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        // Rewire execution_audit_logs: replace work_item_id with workspace_id
        Schema::table('execution_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['work_item_id']);
        });
        Schema::table('execution_audit_logs', function (Blueprint $table) {
            $table->dropColumn('work_item_id');
        });
        Schema::table('execution_audit_logs', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        // Rewire agent_memories: replace repo_id with workspace_id
        // Drop index first (required for SQLite which can't drop columns referenced by indexes)
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropIndex(['repo_id', 'type']);
        });
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropForeign(['repo_id']);
            $table->dropColumn('repo_id');
        });
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->index(['workspace_id', 'type']);
        });

        // Drop old join tables (order matters for FK constraints)
        Schema::dropIfExists('repo_indices');
        Schema::dropIfExists('project_repo');
        Schema::dropIfExists('repo_skill');
        Schema::dropIfExists('agent_repo');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('repos');
        Schema::dropIfExists('projects');
    }

    public function down(): void
    {
        // This migration is not reversible in a meaningful way
        Schema::dropIfExists('skill_workspace');
        Schema::dropIfExists('agent_workspace');
        Schema::dropIfExists('workspace_references');
        Schema::dropIfExists('workspaces');
    }
};
