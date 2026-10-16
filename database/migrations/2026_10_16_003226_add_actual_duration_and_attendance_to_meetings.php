<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedInteger('actual_duration_seconds')->nullable();
        });

        Schema::table('meeting_user', function (Blueprint $table) {
            $table->string('attendance_status')->default('pending');
        });

        Schema::table('meeting_participant', function (Blueprint $table) {
            $table->string('attendance_status')->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_participant', function (Blueprint $table) {
            $table->dropColumn('attendance_status');
        });

        Schema::table('meeting_user', function (Blueprint $table) {
            $table->dropColumn('attendance_status');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('actual_duration_seconds');
        });
    }
};
