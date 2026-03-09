<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('repo_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('content');
            $table->string('summary');
            $table->unsignedTinyInteger('importance')->default(5);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
            $table->index(['repo_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
