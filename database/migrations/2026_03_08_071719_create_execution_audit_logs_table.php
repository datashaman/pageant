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
        Schema::create('execution_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('work_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('agent_id')->nullable();
            $table->string('type');
            $table->text('detail');
            $table->integer('exit_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('execution_audit_logs');
    }
};
