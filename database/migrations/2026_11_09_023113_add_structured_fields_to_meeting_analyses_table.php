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
        Schema::table('meeting_analyses', function (Blueprint $table): void {
            $table->string('analysis_type')->default('post_meeting')->after('requested_by');
            $table->unsignedInteger('version')->default(1)->after('analysis_type');
            $table->string('prompt_version')->nullable()->after('model');
            $table->json('input_data')->nullable()->after('transcript');
            $table->json('result')->nullable()->after('action_items');
            $table->json('usage')->nullable()->after('result');
            $table->decimal('provider_cost', 12, 6)->nullable()->after('usage');
            $table->index(['meeting_id', 'analysis_type', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_analyses', function (Blueprint $table): void {
            $table->dropIndex(['meeting_id', 'analysis_type', 'version']);
            $table->dropColumn(['analysis_type', 'version', 'prompt_version', 'input_data', 'result', 'usage', 'provider_cost']);
        });
    }
};
