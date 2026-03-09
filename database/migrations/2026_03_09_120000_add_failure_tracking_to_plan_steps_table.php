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
        Schema::table('plan_steps', function (Blueprint $table) {
            $table->string('failure_category')->nullable()->after('result');
            $table->unsignedInteger('retry_attempts')->default(0)->after('failure_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_steps', function (Blueprint $table) {
            $table->dropColumn(['failure_category', 'retry_attempts']);
        });
    }
};
