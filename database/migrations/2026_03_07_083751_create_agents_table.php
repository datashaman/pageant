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
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description');
            $table->json('tools');
            $table->json('disallowed_tools');
            $table->string('provider');
            $table->string('model')->default('inherit');
            $table->string('permission_mode');
            $table->integer('max_turns');
            $table->boolean('background')->default(false);
            $table->string('isolation')->default('false');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
