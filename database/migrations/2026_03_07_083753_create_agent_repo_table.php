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
        Schema::create('agent_repo', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('repo_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'repo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_repo');
    }
};
