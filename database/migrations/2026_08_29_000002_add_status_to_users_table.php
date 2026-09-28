<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('email');
            $table->string('role')->default('participant')->after('status');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('role');
            $table->decimal('salary', 12, 2)->nullable()->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'role', 'hourly_rate', 'salary']);
        });
    }
};
