<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_steps', function (Blueprint $table) {
            $table->text('progress_summary')->nullable()->after('result');
            $table->unsignedInteger('turns_used')->nullable()->after('progress_summary');
        });
    }

    public function down(): void
    {
        Schema::table('plan_steps', function (Blueprint $table) {
            $table->dropColumn(['progress_summary', 'turns_used']);
        });
    }
};
