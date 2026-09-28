<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->foreignId('member_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index('member_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_user_id');
        });
    }
};
