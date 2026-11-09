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
        Schema::table('meeting_conclusions', function (Blueprint $table): void {
            $table->string('objective_achievement')->nullable();
            $table->unsignedInteger('decisions_count')->default(0);
            $table->unsignedInteger('completed_topics_count')->default(0);
            $table->text('pending_topics')->nullable();
            $table->boolean('objective_changed')->nullable();
            $table->boolean('duration_sufficient')->nullable();
            $table->boolean('time_well_used')->nullable();
            $table->boolean('replaceable_by_email')->nullable();
            $table->boolean('needs_follow_up')->nullable();
            $table->unsignedTinyInteger('productivity_score')->nullable();
            $table->unsignedTinyInteger('investment_return_score')->nullable();
        });
        Schema::table('meeting_participants', function (Blueprint $table): void {
            $table->boolean('participation_effective')->nullable();
            $table->boolean('relevant_contribution')->nullable();
            $table->boolean('contributed_to_decision')->nullable();
            $table->boolean('participation_necessary')->nullable();
            $table->unsignedTinyInteger('self_rating')->nullable();
        });
        Schema::table('meeting_action_items', function (Blueprint $table): void {
            $table->text('expected_result')->nullable();
            $table->text('delay_reason')->nullable();
            $table->boolean('needs_additional_meeting')->default(false);
            $table->foreignId('follow_up_meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
        });
        Schema::create('meeting_participant_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            foreach (['clarity', 'objectivity', 'organization', 'time_management', 'participation', 'effectiveness'] as $metric) {
                $table->unsignedTinyInteger($metric.'_score')->nullable();
            }
            $table->text('suggestion')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });
        Schema::create('meeting_action_item_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_action_item_id')->constrained('meeting_action_items')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['meeting_action_item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_action_item_histories');
        Schema::dropIfExists('meeting_participant_feedback');
        Schema::table('meeting_action_items', function (Blueprint $table): void {
            $table->dropForeign(['follow_up_meeting_id']);
            $table->dropColumn(['expected_result', 'delay_reason', 'needs_additional_meeting', 'follow_up_meeting_id']);
        });
        Schema::table('meeting_participants', function (Blueprint $table): void {
            $table->dropColumn(['participation_effective', 'relevant_contribution', 'contributed_to_decision', 'participation_necessary', 'self_rating']);
        });
        Schema::table('meeting_conclusions', function (Blueprint $table): void {
            $table->dropColumn(['objective_achievement', 'decisions_count', 'completed_topics_count', 'pending_topics', 'objective_changed', 'duration_sufficient', 'time_well_used', 'replaceable_by_email', 'needs_follow_up', 'productivity_score', 'investment_return_score']);
        });
    }
};
