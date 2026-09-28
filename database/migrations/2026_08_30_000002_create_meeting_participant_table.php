<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participant', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['meeting_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participant');
    }
};
