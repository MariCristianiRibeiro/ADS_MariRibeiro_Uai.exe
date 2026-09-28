<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subordinate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->timestamps();
            $table->unique(['subordinate_user_id', 'supervisor_user_id']);
            $table->index(['supervisor_user_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hierarchy');
    }
};
