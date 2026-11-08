<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('participant');
            $table->string('invitation_status')->default('pending');
            $table->string('confirmation_status')->default('pending');
            $table->string('attendance_status')->default('pending');
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'attendance_status']);
        });

        Schema::create('meeting_cost_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_participant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('directorate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        Schema::create('meeting_conclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'decided_at']);
        });

        Schema::create('meeting_action_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('directorate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('due_date')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->text('delivered_result')->nullable();
            $table->boolean('requires_follow_up')->default(false);
            $table->timestamps();
            $table->index(['meeting_id', 'status', 'due_date']);
            $table->index(['responsible_user_id', 'status']);
        });

        Schema::create('meeting_action_item_dependencies', function (Blueprint $table): void {
            $table->foreignId('action_item_id')->constrained('meeting_action_items')->cascadeOnDelete();
            $table->foreignId('depends_on_action_item_id')->constrained('meeting_action_items')->cascadeOnDelete();
            $table->primary(['action_item_id', 'depends_on_action_item_id']);
            $table->index('depends_on_action_item_id');
        });

        Schema::create('meeting_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['meeting_id', 'created_at']);
        });

        $legacyParticipants = DB::table('meeting_user')
            ->join('users', 'users.id', '=', 'meeting_user.user_id')
            ->leftJoin('job_positions', 'job_positions.id', '=', 'users.job_position_id')
            ->select([
                'meeting_user.meeting_id', 'meeting_user.user_id', 'meeting_user.attendance_status',
                'users.job_position_id', 'users.team_id', 'users.directorate_id',
                'job_positions.hourly_rate as position_hourly_rate', 'users.hourly_rate as user_hourly_rate',
            ])
            ->selectSub(
                DB::table('participants')->select('hourly_rate')
                    ->whereColumn('member_user_id', 'meeting_user.user_id')
                    ->orderByDesc('id')->limit(1),
                'participant_hourly_rate',
            );

        foreach ($legacyParticipants->get() as $participant) {
            $attendanceStatus = match ($participant->attendance_status ?? 'pending') {
                'confirmed' => 'present',
                'absent' => 'absent',
                default => 'pending',
            };

            $participantId = DB::table('meeting_participants')->insertGetId([
                'meeting_id' => $participant->meeting_id,
                'user_id' => $participant->user_id,
                'role' => 'participant',
                'invitation_status' => 'sent',
                'confirmation_status' => $attendanceStatus === 'pending' ? 'pending' : 'confirmed',
                'attendance_status' => $attendanceStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('meeting_cost_snapshots')->insert([
                'meeting_participant_id' => $participantId,
                'job_position_id' => $participant->job_position_id,
                'team_id' => $participant->team_id,
                'directorate_id' => $participant->directorate_id,
                'hourly_rate' => $participant->position_hourly_rate ?? $participant->user_hourly_rate ?? $participant->participant_hourly_rate ?? 0,
                'captured_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $legacyParticipantLinks = DB::table('meeting_participant')
            ->join('participants', 'participants.id', '=', 'meeting_participant.participant_id')
            ->whereNotNull('participants.member_user_id')
            ->leftJoin('users', 'users.id', '=', 'participants.member_user_id')
            ->leftJoin('job_positions', 'job_positions.id', '=', 'users.job_position_id')
            ->select([
                'meeting_participant.meeting_id', 'participants.member_user_id as user_id',
                'meeting_participant.attendance_status', 'users.job_position_id', 'users.team_id',
                'users.directorate_id', 'job_positions.hourly_rate as position_hourly_rate',
                'users.hourly_rate as user_hourly_rate', 'participants.hourly_rate as participant_hourly_rate',
            ]);

        foreach ($legacyParticipantLinks->get() as $participant) {
            $attendanceStatus = match ($participant->attendance_status ?? 'pending') {
                'confirmed' => 'present',
                'absent' => 'absent',
                default => 'pending',
            };

            $participantId = DB::table('meeting_participants')->where([
                'meeting_id' => $participant->meeting_id,
                'user_id' => $participant->user_id,
            ])->value('id');

            if ($participantId === null) {
                $participantId = DB::table('meeting_participants')->insertGetId([
                    'meeting_id' => $participant->meeting_id,
                    'user_id' => $participant->user_id,
                    'role' => 'participant',
                    'invitation_status' => 'sent',
                    'confirmation_status' => $attendanceStatus === 'pending' ? 'pending' : 'confirmed',
                    'attendance_status' => $attendanceStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('meeting_cost_snapshots')->insert([
                    'meeting_participant_id' => $participantId,
                    'job_position_id' => $participant->job_position_id,
                    'team_id' => $participant->team_id,
                    'directorate_id' => $participant->directorate_id,
                    'hourly_rate' => $participant->position_hourly_rate ?? $participant->user_hourly_rate ?? $participant->participant_hourly_rate ?? 0,
                    'captured_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_history');
        Schema::dropIfExists('meeting_action_item_dependencies');
        Schema::dropIfExists('meeting_action_items');
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_conclusions');
        Schema::dropIfExists('meeting_cost_snapshots');
        Schema::dropIfExists('meeting_participants');
    }
};
