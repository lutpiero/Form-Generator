<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedInteger('max_submissions')->nullable()->after('success_message');
            $table->timestamp('submission_start_at')->nullable()->after('max_submissions');
            $table->timestamp('submission_end_at')->nullable()->after('submission_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['max_submissions', 'submission_start_at', 'submission_end_at']);
        });
    }
};
