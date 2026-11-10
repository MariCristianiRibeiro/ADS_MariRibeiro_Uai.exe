<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\MeetingPredictionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meetings:refresh-predictions {--evaluate-only : Avalia previsões existentes sem gerar novas} {--regenerate : Cria uma nova geração preservando as anteriores} {--limit= : Limita a quantidade de reuniões processadas}')]
#[Description('Gera previsões históricas reproduzíveis e avalia previsões contra resultados efetivos; não retreina automaticamente modelos.')]
class RefreshMeetingPredictions extends Command
{
    public function handle(MeetingPredictionService $predictions): int
    {
        $generated = 0;
        if (! $this->option('evaluate-only')) {
            $query = Meeting::query()->with([
                'project.team.directorate', 'meetingParticipants.user.team', 'meetingParticipants.user.directorate',
                'meetingParticipants.costSnapshot.team', 'meetingParticipants.costSnapshot.directorate',
                'meetingParticipants.costSnapshot.jobPosition', 'actionItems', 'conclusion', 'users.team',
            ])->orderBy('date')->orderBy('id');
            if ($this->option('limit')) {
                $query->limit(max(1, (int) $this->option('limit')));
            }

            foreach ($query->get() as $meeting) {
                $generated += $predictions->generateFor($meeting, null, (bool) $this->option('regenerate'))->count();
            }
        }

        $evaluated = $predictions->evaluatePending();
        $this->info("Previsões criadas: {$generated}. Resultados avaliados: {$evaluated}.");
        $this->comment('A avaliação preserva cada previsão e não atualiza nem retreina o modelo estatístico.');

        return self::SUCCESS;
    }
}
