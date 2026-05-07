<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('form_fields', 'config')) {
            Schema::table('form_fields', function (Blueprint $table) {
                $table->json('config')->nullable()->after('options');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('form_fields', 'config')) {
            Schema::table('form_fields', function (Blueprint $table) {
                $table->dropColumn('config');
            });
        }
    }
};
