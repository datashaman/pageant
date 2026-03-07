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
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description');
            $table->string('argument_hint');
            $table->string('license');
            $table->boolean('enabled')->default(true);
            $table->string('path');
            $table->json('allowed_tools');
            $table->string('provider');
            $table->string('model');
            $table->string('context');
            $table->foreignUuid('agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('source_reference');
            $table->string('source_url')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
