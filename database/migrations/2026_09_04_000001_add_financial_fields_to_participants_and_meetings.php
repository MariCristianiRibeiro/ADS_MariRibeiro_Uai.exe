<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('type')->default('guest')->after('status');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('type');
            $table->decimal('salary', 12, 2)->nullable()->after('hourly_rate');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->default(60)->after('time');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['type', 'hourly_rate', 'salary']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
