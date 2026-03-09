<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_indices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('repo_id')->constrained()->cascadeOnDelete();
            $table->string('commit_hash', 40);
            $table->text('structural_map');
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->unique(['repo_id', 'commit_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_indices');
    }
};
