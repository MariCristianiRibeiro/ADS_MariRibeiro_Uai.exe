<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-teal-700">{{ $meeting->category ?: 'Reunião' }}</p><h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $meeting->title }}</h2></div>
            @if (auth()->id() === $meeting->user_id)
                <a href="{{ route('meetings.edit', $meeting) }}" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white">Editar reunião</a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @php
                $meetingStatusLabels = ['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'];
                $priorityLabels = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
                $formatLabels = ['in_person' => 'Presencial', 'virtual' => 'Virtual', 'hybrid' => 'Híbrido'];
                $recurrenceLabels = ['daily' => 'Diária', 'weekly' => 'Semanal', 'monthly' => 'Mensal', 'yearly' => 'Anual'];
                $recurrenceUnits = ['daily' => 'dia', 'weekly' => 'semana', 'monthly' => 'mês', 'yearly' => 'ano'];
                $recurrencePluralUnits = ['daily' => 'dias', 'weekly' => 'semanas', 'monthly' => 'meses', 'yearly' => 'anos'];
                $participantRoleLabels = ['organizer' => 'Organizador', 'participant' => 'Participante', 'decision_responsible' => 'Responsável pela decisão', 'guest' => 'Convidado'];
                $invitationStatusLabels = ['pending' => 'Pendente', 'sent' => 'Enviado', 'accepted' => 'Aceito', 'declined' => 'Recusado'];
                $confirmationStatusLabels = ['pending' => 'Pendente', 'confirmed' => 'Confirmada', 'declined' => 'Recusada'];
                $attendanceStatusLabels = ['pending' => 'Pendente', 'present' => 'Presente', 'absent' => 'Ausente', 'confirmed' => 'Confirmada'];
                $actionStatusLabels = ['open' => 'Aberta', 'in_progress' => 'Em andamento', 'blocked' => 'Bloqueada', 'completed' => 'Concluída', 'canceled' => 'Cancelada'];
                $actionEventLabels = ['created' => 'Criada', 'updated' => 'Atualizada', 'completed' => 'Concluída', 'reopened' => 'Reaberta'];
            @endphp
            @if (session('success')) <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div> @endif
            @if (session('warning')) <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">{{ session('warning') }}</div> @endif
            @if (session('warning')) <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">{{ session('warning') }}</div> @endif
            @if ($errors->any()) <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><p class="font-semibold">Revise os campos informados.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

            @if ($aiInsight)
                <section class="rounded-lg border-l-4 border-teal-600 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Análise da reunião</p>
                    @if ($aiInsight['summary'])<p class="mt-2 text-base font-semibold leading-6 text-gray-900">{{ $aiInsight['summary'] }}</p>@endif
                    @if ($aiInsight['finding'])<p class="mt-2 text-sm leading-5 text-gray-700">{{ $aiInsight['finding'] }}</p>@endif
                    @if ($aiInsight['recommendation'])
                        <p class="mt-2 text-sm text-gray-700"><span class="font-semibold">{{ $aiInsight['recommendation']['title'] }}</span>@if ($aiInsight['recommendation']['detail']){{ $aiInsight['recommendation']['title'] ? ' — ' : '' }}{{ $aiInsight['recommendation']['detail'] }}@endif</p>
                    @endif
                </section>
            @endif

            <section class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 class="font-semibold text-gray-900">Planejamento</h3>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs uppercase text-gray-500">Data e horário</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->date?->format('d/m/Y') }} · {{ $meeting->time ? substr($meeting->time, 0, 5) : 'horário não definido' }}{{ $meeting->end_time ? '–'.substr($meeting->end_time, 0, 5) : '' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Status e prioridade</dt><dd class="mt-1 text-sm text-gray-800">{{ $meetingStatusLabels[$meeting->status] ?? 'Não informado' }} · {{ $priorityLabels[$meeting->priority] ?? 'Não informada' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Organizador</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->organizer?->name ?? 'Não definido' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Local e formato</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->location ?? 'Não definido' }} · {{ $formatLabels[$meeting->meeting_format] ?? 'Formato não definido' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Duração prevista</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->duration_minutes }} minutos</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Recorrência</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->recurrence_type === 'recurring' ? (($recurrenceLabels[$meeting->recurrence_frequency] ?? 'Recorrência').' · a cada '.($meeting->recurrence_interval ?? 1).' '.((int) ($meeting->recurrence_interval ?? 1) === 1 ? ($recurrenceUnits[$meeting->recurrence_frequency] ?? 'período') : ($recurrencePluralUnits[$meeting->recurrence_frequency] ?? 'períodos'))) : 'Única' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Objetivo</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->objective ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Contexto</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->description ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Pauta</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->agenda ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Resultado esperado</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->expected_outcome ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Observações adicionais</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->notes ?: '—' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Participantes</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @forelse ($meeting->meetingParticipants as $participant)
                            <li class="py-3">
                                <p class="font-medium text-gray-800">{{ $participant->user?->name }}</p>
                                <p class="text-xs text-gray-500">{{ $participantRoleLabels[$participant->role] ?? 'Participante' }} · Convite: {{ $invitationStatusLabels[$participant->invitation_status] ?? 'Não informado' }} · Confirmação: {{ $confirmationStatusLabels[$participant->confirmation_status] ?? 'Não informada' }} · Presença: {{ $attendanceStatusLabels[$participant->attendance_status] ?? 'Não informada' }}</p>
                                <p class="text-xs text-gray-500">{{ $participant->costSnapshot?->jobPosition?->name ?? $participant->user?->jobPosition?->name ?? 'Cargo não definido' }} · {{ $participant->costSnapshot?->team?->name ?? $participant->user?->team?->name ?? 'Equipe não definida' }}</p>
                                @can('view-costs')<p class="text-xs text-gray-500">Valor-hora registrado: R$ {{ number_format((float) ($participant->costSnapshot?->hourly_rate ?? 0), 2, ',', '.') }}/h</p>@endcan
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-500">Nenhum participante vinculado.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="hidden grid gap-6">
                <div class="rounded-lg border border-teal-200 bg-teal-50/40 p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Previsões históricas</h3>
                    <p class="mt-1 text-xs text-gray-600">Estimativas estatísticas validadas separadamente da análise generativa. Amostra insuficiente não produz valor previsto.</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($statisticalPredictions->whereNotIn('prediction_type', ['planned_cost', 'recurring_monthly_cost', 'recurring_annual_cost']) as $prediction)
                            <article class="rounded-md bg-white p-3">
                                <p class="text-sm font-medium text-gray-800">{{ ['actual_duration_minutes' => 'Duração estimada', 'meetings_to_conclude' => 'Encontros prováveis para concluir', 'on_time_probability' => 'Conclusão no prazo', 'additional_meeting_risk' => 'Risco de encontro adicional', 'resolution_days' => 'Tempo estimado de resolução'][$prediction->prediction_type] ?? $prediction->prediction_type }}</p>
                                @if ($prediction->status === 'validated' && $prediction->predicted_value !== null)
                                    <p class="mt-1 text-lg font-semibold text-teal-900">{{ in_array($prediction->prediction_type, ['on_time_probability', 'additional_meeting_risk'], true) ? number_format($prediction->predicted_value * 100, 1, ',', '.').'%' : number_format($prediction->predicted_value, 1, ',', '.').' '.$prediction->unit }}</p>
                                    <p class="text-xs text-gray-500">{{ $prediction->model_name }} v{{ $prediction->model_version }} · {{ $prediction->observations_count }} observações · {{ $prediction->validation_observations }} testes temporais · qualidade empírica {{ number_format(($prediction->confidence ?? 0) * 100, 1, ',', '.') }}% (não é probabilidade de acerto).</p>
                                @else<p class="mt-1 text-sm text-amber-800">Histórico insuficiente para uma previsão validada. {{ $prediction->observations_count }} observações e {{ $prediction->validation_observations }} testes disponíveis.</p>@endif
                            </article>
                        @empty<p class="text-sm text-gray-500">Nenhuma previsão estatística disponível.</p>@endforelse
                        @can('view-costs')
                            @foreach ($statisticalPredictions->whereIn('prediction_type', ['planned_cost', 'recurring_monthly_cost', 'recurring_annual_cost']) as $prediction)
                                <article class="rounded-md bg-white p-3"><p class="text-sm font-medium text-gray-800">{{ ['planned_cost' => 'Custo previsto determinístico', 'recurring_monthly_cost' => 'Projeção mensal da recorrência', 'recurring_annual_cost' => 'Projeção anual da recorrência'][$prediction->prediction_type] }}</p><p class="mt-1 text-lg font-semibold text-teal-900">R$ {{ number_format($prediction->predicted_value, 2, ',', '.') }}</p><p class="text-xs text-gray-500">Calculado pelo custo-hora real e frequência cadastrada · {{ $prediction->model_version }}</p></article>
                            @endforeach
                        @endcan
                    </div>
                </div>
            </section>

            <section id="conclusion" class="grid gap-6">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Decisões</h3>
                    <ul class="mt-3 space-y-3">
                        @forelse ($meeting->decisions as $decision)
                            <li class="rounded-md bg-gray-50 p-3"><p class="font-medium text-gray-800">{{ $decision->title }}</p><p class="whitespace-pre-line text-sm text-gray-600">{{ $decision->description }}</p><p class="mt-1 text-xs text-gray-500">{{ $decision->decisionMaker?->name ?? 'Responsável não informado' }} · {{ $decision->decided_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p></li>
                        @empty <li class="text-sm text-gray-500">Nenhuma decisão registrada.</li> @endforelse
                    </ul>
                    @can('meetings.edit')
                        <form method="POST" action="{{ route('meetings.decisions.store', $meeting) }}" class="mt-5 space-y-3 border-t pt-4">@csrf
                            <label class="block text-sm font-medium text-gray-700">Nova decisão<input name="title" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                            <textarea name="description" rows="2" placeholder="Detalhes" class="block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                            <select name="decided_by" class="block w-full rounded-md border-gray-300 shadow-sm"><option value="">Responsável pela decisão</option>@foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
                            <button class="rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase text-white">Registrar decisão</button>
                        </form>
                    @endcan
                </div>
                <div class="w-full rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Conclusão</h3>
                    @if ($meeting->conclusion)<p class="mt-2 text-xs text-gray-500">Registrada por {{ $meeting->conclusion->recorder?->name ?? 'usuário removido' }} · {{ $meeting->conclusion->concluded_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>@endif
                    @if (($meeting->status === 'completed' || $isPastUnstarted) && $canManageMeetingConclusion && auth()->user()->can('meetings.edit'))
                    <form id="meeting-conclusion-form" method="POST" action="{{ route('meetings.conclusion.save', $meeting) }}" class="mt-3 space-y-3">@csrf @method('PUT')
                        @if ($isPastUnstarted)
                            <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">Esta agenda venceu sem iniciar o cronômetro. Informe por que não foi realizada na data planejada e preencha os dados de conclusão para encerrá-la.</p>
                        @else
                            <p class="text-sm text-gray-600">Informe o resultado real. Esses dados serão preservados para os insights da IA e análises futuras, incluindo reuniões recorrentes.</p>
                        @endif
                        @if ($meeting->schedule_advanced_at !== null || $isPastUnstarted)
                            <label class="block text-sm font-medium text-gray-700">Motivo da {{ $isPastUnstarted ? 'não realização na data planejada' : 'antecipação da agenda' }}<textarea name="schedule_change_reason" required minlength="10" maxlength="5000" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('schedule_change_reason', $meeting->conclusion?->schedule_change_reason) }}</textarea></label>
                            @error('schedule_change_reason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        @endif
                        <label class="block text-sm font-medium text-gray-700">Resumo<textarea name="summary" required minlength="10" maxlength="10000" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('summary', $meeting->conclusion?->summary) }}</textarea></label>
                        <label class="block text-sm font-medium text-gray-700">Resultado obtido<textarea name="outcome" required minlength="10" maxlength="10000" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('outcome', $meeting->conclusion?->outcome) }}</textarea></label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Objetivo alcançado<select name="objective_achievement" required class="mt-1 block w-full rounded-md border-gray-300"><option value="">Selecione</option>@foreach (['yes' => 'Sim', 'partially' => 'Parcialmente', 'no' => 'Não'] as $value => $label)<option value="{{ $value }}" @selected(old('objective_achievement', $meeting->conclusion?->objective_achievement) === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="text-sm font-medium text-gray-700">Decisões tomadas<input type="number" min="0" name="decisions_count" value="{{ old('decisions_count', $meeting->conclusion?->decisions_count ?? 0) }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm font-medium text-gray-700">Assuntos concluídos<input type="number" min="0" name="completed_topics_count" value="{{ old('completed_topics_count', $meeting->conclusion?->completed_topics_count ?? 0) }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm font-medium text-gray-700">Produtividade (1–5)<input type="number" min="1" max="5" name="productivity_score" value="{{ old('productivity_score', $meeting->conclusion?->productivity_score) }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm font-medium text-gray-700">Retorno percebido (1–5)<input type="number" min="1" max="5" name="investment_return_score" value="{{ old('investment_return_score', $meeting->conclusion?->investment_return_score) }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                        </div>
                        <label class="block text-sm font-medium text-gray-700">Assuntos pendentes<textarea name="pending_topics" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('pending_topics', $meeting->conclusion?->pending_topics) }}</textarea></label>
                        <div class="grid gap-2 sm:grid-cols-2">@foreach (['objective_changed' => 'O objetivo mudou?', 'duration_sufficient' => 'A duração foi suficiente?', 'time_well_used' => 'O tempo foi bem aproveitado?', 'replaceable_by_email' => 'Poderia ser substituída por e-mail?', 'needs_follow_up' => 'Será necessário outro encontro?'] as $field => $label)<label class="text-sm text-gray-700">{{ $label }}<select name="{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Não informado</option><option value="1" @selected((string) old($field, $meeting->conclusion?->{$field}) === '1')>Sim</option><option value="0" @selected((string) old($field, $meeting->conclusion?->{$field}) === '0')>Não</option></select></label>@endforeach</div>
                        @if ($meeting->meetingParticipants->isNotEmpty())
                            <fieldset class="rounded-md border p-3"><legend class="px-1 text-sm font-semibold">Participação (uso contextual, sem classificar desempenho individual)</legend>
                                @foreach ($meeting->meetingParticipants as $participant)<div class="grid gap-2 border-b py-3 last:border-0 sm:grid-cols-2"><p class="text-sm font-medium text-gray-700 sm:col-span-2">{{ $participant->user?->name }} · Presença: {{ $attendanceStatusLabels[$participant->attendance_status] ?? 'Não informada' }}</p><label class="text-xs text-gray-600">Presença<select name="participation[{{ $participant->id }}][attendance_status]" class="mt-1 block w-full rounded-md border-gray-300"><option value="pending" @selected($participant->attendance_status === "pending")>Pendente</option><option value="present" @selected($participant->attendance_status === "present")>Presente</option><option value="absent" @selected($participant->attendance_status === "absent")>Ausente</option></select></label>@foreach (['participation_effective' => 'Participação efetiva?', 'relevant_contribution' => 'Contribuição relevante?', 'contributed_to_decision' => 'Contribuiu para decisões?', 'participation_necessary' => 'Participação necessária?'] as $field => $label)<label class="text-xs text-gray-600">{{ $label }}<select name="participation[{{ $participant->id }}][{{ $field }}]" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Não informado</option><option value="1" @selected((string) old("participation.{$participant->id}.{$field}", $participant->{$field}) === '1')>Sim</option><option value="0" @selected((string) old("participation.{$participant->id}.{$field}", $participant->{$field}) === '0')>Não</option></select></label>@endforeach<label class="text-xs text-gray-600">Autoavaliação opcional (1–5)<input type="number" min="1" max="5" name="participation[{{ $participant->id }}][self_rating]" value="{{ old("participation.{$participant->id}.self_rating", $participant->self_rating) }}" class="mt-1 block w-full rounded-md border-gray-300"></label></div>@endforeach
                            </fieldset>
                        @endif
                    </form>
                    @else
                        @if ($meeting->conclusion)
                            <p class="mt-3 text-sm text-gray-700"><strong>Resumo:</strong> {{ $meeting->conclusion->summary }}</p>
                            <p class="mt-2 text-sm text-gray-700"><strong>Resultado:</strong> {{ $meeting->conclusion->outcome }}</p>
                            @if ($meeting->conclusion->schedule_change_reason)<p class="mt-2 text-sm text-gray-700"><strong>Motivo da alteração da agenda:</strong> {{ $meeting->conclusion->schedule_change_reason }}</p>@endif
                        @endif
                        @if ($meeting->status !== 'completed' && ! $isPastUnstarted)
                            <p class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">Finalize o cronômetro para liberar o registro da conclusão.</p>
                        @elseif (auth()->id() !== $meeting->organizer_id)
                            <p class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">O organizador da reunião deve preencher e salvar a conclusão.</p>
                        @else
                            <p class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">Você precisa da permissão de edição de reuniões para registrar a conclusão.</p>
                        @endif
                    @endif
                </div>
            </section>

            @if (session('action_item_created'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('action_item_created') }}</div>
            @endif

            @can('meetings.edit')
                <details class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900 marker:hidden">
                        <span>Criar encaminhamento <span class="ml-2 text-sm font-normal text-slate-500">Abra para registrar uma nova ação</span></span>
                        <span class="text-xl font-normal text-teal-700" aria-hidden="true">+</span>
                    </summary>
                    <form method="POST" action="{{ route('meetings.action-items.store', $meeting) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">@csrf
                        <label class="block text-sm font-medium text-gray-700">Título<input name="title" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                        <label class="block text-sm font-medium text-gray-700">Responsável<select name="responsible_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-gray-700 sm:col-span-2">Descrição<textarea name="description" required rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea></label>
                        <label class="block text-sm font-medium text-gray-700 sm:col-span-2">Resultado esperado<textarea name="expected_result" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea></label>
                        <label class="block text-sm font-medium text-gray-700">Departamento<select name="directorate_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não definido</option>@foreach ($directorates as $directorate)<option value="{{ $directorate->id }}">{{ $directorate->name }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-gray-700">Prazo<input type="date" name="due_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                        <label class="block text-sm font-medium text-gray-700">Prioridade<select name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="low">Baixa</option><option value="normal" selected>Normal</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></label>
                        <label class="block text-sm font-medium text-gray-700">Dependências<select name="dependencies[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($meeting->actionItems as $actionItem)<option value="{{ $actionItem->id }}">{{ $actionItem->title }}</option>@endforeach</select></label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2"><input type="checkbox" name="requires_follow_up" value="1"> Necessita cobrança</label>
                        <button class="w-fit rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Salvar encaminhamento</button>
                    </form>
                </details>
            @endcan

            @if ($meeting->status === 'completed' && $meeting->meetingParticipants->contains('user_id', auth()->id()))
                <section class="rounded-lg bg-white p-5 shadow-sm"><h3 class="font-semibold text-gray-900">Feedback opcional do participante</h3><p class="mt-1 text-xs text-gray-500">As notas são consideradas agregadas e não classificam desempenho individual.</p><form method="POST" action="{{ route('meetings.participant-feedback.save', $meeting) }}" class="mt-3 grid gap-3 sm:grid-cols-3">@csrf @method('PUT')
                    @foreach (['clarity' => 'Clareza da pauta', 'objectivity' => 'Objetividade', 'organization' => 'Organização', 'time_management' => 'Gestão do tempo', 'participation' => 'Participação', 'effectiveness' => 'Efetividade'] as $field => $label)<label class="text-sm text-gray-700">{{ $label }}<select name="{{ $field }}_score" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Opcional</option>@for ($score = 1; $score <= 5; $score++)<option value="{{ $score }}" @selected(old($field.'_score', $currentParticipantFeedback?->{$field.'_score'}) == $score)>{{ $score }}</option>@endfor</select></label>@endforeach
                    <label class="text-sm text-gray-700 sm:col-span-3">Sugestão de melhoria<textarea name="suggestion" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('suggestion', $currentParticipantFeedback?->suggestion) }}</textarea></label><label class="text-sm text-gray-700 sm:col-span-3">Comentários livres<textarea name="comments" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('comments', $currentParticipantFeedback?->comments) }}</textarea></label><button class="w-fit rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase text-white">Salvar feedback</button></form></section>
            @endif

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900">Histórico de aprendizado</h3>
                <p class="mt-1 text-sm text-gray-600">As previsões permanecem versionadas para comparação com os resultados reais e as atualizações das demandas.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-gray-500">Encontros realizados</p><p class="mt-1 font-semibold text-gray-900">{{ $learningMetrics['meetings_count'] }}</p></div>
                    <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-gray-500">Tempo total de reunião</p><p class="mt-1 font-semibold text-gray-900">{{ number_format($learningMetrics['actual_seconds'] / 3600, 1, ',', '.') }} h</p></div>
                    <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-gray-500">Horas colaborador</p><p class="mt-1 font-semibold text-gray-900">{{ number_format($learningMetrics['collaborator_hours'], 1, ',', '.') }} h</p></div>
                    @can('view-costs')<div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-gray-500">Custo real acumulado</p><p class="mt-1 font-semibold text-gray-900">R$ {{ number_format((float) $learningMetrics['actual_cost'], 2, ',', '.') }}</p></div>@endcan
                </div>
                @php($preMeetingForecasts = $meeting->analyses->where('analysis_type', 'pre_meeting')->sortBy('version'))
                @if ($preMeetingForecasts->isNotEmpty())<div class="mt-4"><h4 class="text-sm font-semibold text-gray-800">Previsões iniciais preservadas</h4><ol class="mt-2 space-y-2">@foreach ($preMeetingForecasts as $forecast)<li class="rounded-md border p-3"><p class="text-xs text-gray-500">Versão {{ $forecast->version }} · {{ $forecast->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p><p class="mt-1 text-sm text-gray-700">{{ $forecast->result['summary'] ?? 'Previsão registrada antes da análise estruturada.' }}</p></li>@endforeach</ol></div>@endif
                <div class="mt-4"><h4 class="text-sm font-semibold text-gray-800">Resultado real e encaminhamentos</h4><p class="mt-1 text-sm text-gray-700">{{ $meeting->conclusion?->outcome ?: 'Resultado ainda não informado.' }} · {{ $meeting->actionItems->where('status', 'completed')->count() }}/{{ $meeting->actionItems->count() }} demandas concluídas; {{ $meeting->actionItems->filter(fn ($item) => $item->status === 'completed' && $item->due_date && $item->completed_at?->toDateString() > $item->due_date->toDateString())->count() }} após o prazo.</p></div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold text-gray-900">Encaminhamentos</h3><span class="text-xs text-gray-500">Originados nesta reunião</span></div>
                <div class="mt-3 space-y-3">
                    @forelse ($meeting->actionItems as $actionItem)
                        <article class="rounded-md border border-gray-200 p-4">
                            <div class="flex flex-wrap justify-between gap-2"><div><h4 class="font-medium text-gray-800">{{ $actionItem->title }}</h4><p class="whitespace-pre-line text-sm text-gray-600">{{ $actionItem->description }}</p></div><span class="text-xs font-semibold text-gray-600">{{ $actionStatusLabels[$actionItem->status] ?? 'Não informado' }} · {{ $priorityLabels[$actionItem->priority] ?? 'Não informada' }}</span></div>
                            <p class="mt-2 text-xs text-gray-500">Responsável: {{ $actionItem->responsible?->name ?? 'Não atribuído' }} · Departamento: {{ $actionItem->directorate?->name ?? 'Não definido' }} · Prazo: {{ $actionItem->due_date?->format('d/m/Y') ?? 'Sem prazo' }}</p>
                            @if ($actionItem->dependencies->isNotEmpty())<p class="mt-1 text-xs text-gray-500">Dependências: {{ $actionItem->dependencies->pluck('title')->implode(', ') }}</p>@endif
                            @if ($actionItem->expected_result)<p class="mt-1 text-sm text-gray-700">Resultado esperado: {{ $actionItem->expected_result }}</p>@endif
                            @if ($actionItem->delivered_result)<p class="mt-2 text-sm text-gray-700">Resultado entregue: {{ $actionItem->delivered_result }}</p>@endif
                            @if ($actionItem->completed_at)<p class="mt-1 text-xs text-gray-500">Resolução em {{ $actionItem->completed_at->diffInDays($actionItem->created_at) }} dia(s) · {{ !$actionItem->due_date || $actionItem->completed_at->toDateString() <= $actionItem->due_date->toDateString() ? 'no prazo' : 'após o prazo' }}</p>@endif
                            @if ($actionItem->delay_reason)<p class="mt-1 text-sm text-amber-800">Motivo do atraso: {{ $actionItem->delay_reason }}</p>@endif
                            @if ($actionItem->history->isNotEmpty())<details class="mt-2 text-xs text-gray-500"><summary class="cursor-pointer">Histórico da demanda</summary><ul class="mt-2 space-y-1">@foreach ($actionItem->history as $event)<li>{{ $event->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $actionEventLabels[$event->event] ?? 'Atualização' }} — {{ $event->actor?->name ?? 'Sistema' }}</li>@endforeach</ul></details>@endif
                            @can('meetings.edit')
                                <form method="POST" action="{{ route('meetings.action-items.update', [$meeting, $actionItem]) }}" class="mt-3 grid gap-2 sm:grid-cols-3">@csrf @method('PATCH')
                                    <select name="status" class="rounded-md border-gray-300 text-sm"><option value="open" @selected($actionItem->status === 'open')>Aberto</option><option value="in_progress" @selected($actionItem->status === 'in_progress')>Em andamento</option><option value="blocked" @selected($actionItem->status === 'blocked')>Bloqueado</option><option value="completed" @selected($actionItem->status === 'completed')>Concluído</option><option value="canceled" @selected($actionItem->status === 'canceled')>Cancelado</option></select>
                                    <input type="date" name="due_date" value="{{ $actionItem->due_date?->format('Y-m-d') }}" class="rounded-md border-gray-300 text-sm">
                                    <input name="delivered_result" value="{{ $actionItem->delivered_result }}" placeholder="Resultado entregue" class="rounded-md border-gray-300 text-sm">
                                    <input name="delay_reason" value="{{ $actionItem->delay_reason }}" placeholder="Motivo do atraso (se houver)" class="rounded-md border-gray-300 text-sm"><input name="expected_result" value="{{ $actionItem->expected_result }}" placeholder="Resultado esperado" class="rounded-md border-gray-300 text-sm"><select name="follow_up_meeting_id" class="rounded-md border-gray-300 text-sm"><option value="">Reunião adicional vinculada</option>@foreach ($additionalMeetings as $additionalMeeting)<option value="{{ $additionalMeeting->id }}" @selected($actionItem->follow_up_meeting_id === $additionalMeeting->id)>{{ $additionalMeeting->title }} · {{ $additionalMeeting->date?->format("d/m/Y") }}</option>@endforeach</select>
                                    <label class="text-sm text-gray-600"><input type="hidden" name="requires_follow_up" value="0"><input type="checkbox" name="requires_follow_up" value="1" @checked($actionItem->requires_follow_up)> Necessita cobrança</label>
                                    <label class="text-sm text-gray-600"><input type="hidden" name="needs_additional_meeting" value="0"><input type="checkbox" name="needs_additional_meeting" value="1" @checked($actionItem->needs_additional_meeting)> Necessita reunião adicional</label>
                                    <button class="rounded-md bg-gray-700 px-3 py-2 text-xs font-semibold uppercase text-white sm:col-span-2">Atualizar</button>
                                </form>
                            @endcan
                        </article>
                    @empty <p class="text-sm text-gray-500">Nenhum encaminhamento cadastrado.</p> @endforelse
                </div>
            </section>

            <section class="hidden rounded-lg bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900">Histórico</h3>
                @php($historyEventLabels = ['created' => 'Agenda criada', 'updated' => 'Dados da agenda atualizados', 'status_changed' => 'Situação atualizada', 'rescheduled' => 'Agenda reagendada', 'canceled' => 'Agenda cancelada'])
                @php($historyFieldLabels = ['date' => 'Data', 'time' => 'Horário de início', 'end_time' => 'Horário de término', 'status' => 'Situação', 'schedule_advanced_at' => 'Antecipada em', 'reschedule_reason' => 'Motivo do reagendamento', 'title' => 'Título', 'location' => 'Local'])
                <ol class="mt-3 space-y-3">
                    @forelse ($meeting->history as $event)
                        <li class="border-l-2 border-teal-600 pl-3">
                            <p class="text-sm font-medium text-gray-800">{{ $historyEventLabels[$event->event] ?? 'Atualização da agenda' }}</p>
                            <p class="text-xs text-gray-500">{{ $event->actor?->name ?? 'Sistema' }} · {{ $event->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
                            @if (is_array($event->changes) && $event->changes !== [])
                                <ul class="mt-2 space-y-1 text-sm text-gray-600">
                                    @foreach ($event->changes as $field => $change)
                                        <li>
                                            <strong>{{ $historyFieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}:</strong>
                                            @if (is_array($change) && (array_key_exists('old', $change) || array_key_exists('new', $change)))
                                                @php($oldValue = $change['old'] ?? null)
                                                @php($newValue = $change['new'] ?? null)
                                                @if ($field === 'schedule_advanced_at')
                                                    {{ $oldValue ? \Illuminate\Support\Carbon::parse($oldValue, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : 'Não informado' }}
                                                @else
                                                    {{ $field === 'status' ? (['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'][$oldValue ?? ''] ?? ($oldValue ?? 'Não informado')) : (is_scalar($oldValue) ? ($oldValue ?? 'Não informado') : 'Atualizado') }}
                                                @endif
                                                <span aria-hidden="true">→</span>
                                                @if ($field === 'schedule_advanced_at')
                                                    {{ $newValue ? \Illuminate\Support\Carbon::parse($newValue, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : 'Não informado' }}
                                                @else
                                                    {{ $field === 'status' ? (['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'][$newValue ?? ''] ?? ($newValue ?? 'Não informado')) : (is_scalar($newValue) ? ($newValue ?? 'Não informado') : 'Atualizado') }}
                                                @endif
                                            @else
                                                {{ is_scalar($change) ? $change : 'Atualizado' }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @empty <li class="text-sm text-gray-500">Sem eventos registrados.</li> @endforelse
                </ol>
            </section>
            <section class="hidden rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">Insights gerados por Inteligência Artificial</h3>
                        <p class="mt-1 text-sm text-gray-600">Registre manualmente as informações da reunião para receber uma análise contextual. Não há gravação nem transcrição automática. As sugestões não alteram decisões ou encaminhamentos.</p>
                        <p class="mt-1 text-xs text-gray-500">Provedor configurado: {{ $aiProvider }} · Modelo: {{ $aiModel }}</p>
                    </div>
                    @unless ($aiConfigured)
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">Configure a chave de API do provedor selecionado no servidor para gerar análises.</p>
                    @endunless
                </div>

                @can('meetings.edit')
                    @if (in_array($meeting->status, ['draft', 'scheduled'], true))
                        <form method="POST" action="{{ route('meetings.analyses.store', $meeting) }}" class="mt-4 space-y-3 rounded-md border border-teal-100 bg-teal-50/40 p-4">
                            @csrf
                            <input type="hidden" name="analysis_type" value="pre_meeting">
                            <label class="block text-sm font-medium text-gray-700">Contexto adicional para a nova análise (opcional)
                                <textarea name="analysis_notes" maxlength="100000" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Acrescente informações novas antes de refazer a análise.">{{ old('analysis_type') === 'pre_meeting' ? old('analysis_notes') : '' }}</textarea>
                            </label>
                            <button @disabled(! $aiConfigured) class="rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-50">Refazer análise antes da reunião</button>
                        </form>
                    @elseif ($meeting->status === 'ongoing')
                        <form method="POST" action="{{ route('meetings.analyses.store', $meeting) }}" class="mt-4 space-y-3 rounded-md border border-teal-100 bg-teal-50/40 p-4">
                            @csrf
                            <input type="hidden" name="analysis_type" value="during_meeting">
                            <label class="block text-sm font-medium text-gray-700">Anotações desta etapa da reunião
                                <textarea name="analysis_notes" required minlength="10" maxlength="100000" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Registre assuntos discutidos, decisões tomadas e questões ainda sem resposta.">{{ old('analysis_type') === 'during_meeting' ? old('analysis_notes') : '' }}</textarea>
                            </label>
                            @error('analysis_notes')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            <button @disabled(! $aiConfigured) class="rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-50">Gerar resumo parcial e alertas</button>
                        </form>
                    @elseif ($meeting->status === 'completed')
                        <form method="POST" action="{{ route('meetings.analyses.store', $meeting) }}" class="mt-4 space-y-3 rounded-md border border-teal-100 bg-teal-50/40 p-4">
                            @csrf
                            <input type="hidden" name="analysis_type" value="post_meeting">
                            <label class="block text-sm font-medium text-gray-700">Anotações e resultado da reunião
                                <textarea name="analysis_notes" required minlength="10" maxlength="100000" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Descreva o que ocorreu, o resultado e fatos relevantes para comparar com o objetivo.">{{ old('analysis_type') === 'post_meeting' ? old('analysis_notes') : '' }}</textarea>
                            </label>
                            @error('analysis_notes')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            <button @disabled(! $aiConfigured) class="rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-50">Analisar efetividade e próximos passos</button>
                        </form>
                    @endif
                    @if ($meeting->status === 'completed')
                        @php($hasOpenActions = $meeting->actionItems->contains(fn ($item) => ! in_array($item->status, ['completed', 'canceled'], true)))
                        @if ($hasOpenActions)<p class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">A análise pós-reunião é preliminar enquanto houver encaminhamentos abertos.</p>@else
                            <form method="POST" action="{{ route('meetings.analyses.store', $meeting) }}" class="mt-3 space-y-3 rounded-md border border-teal-100 bg-teal-50/40 p-4">@csrf<input type="hidden" name="analysis_type" value="follow_up"><label class="block text-sm font-medium text-gray-700">Observações sobre os resultados finais das demandas<textarea name="analysis_notes" required minlength="10" maxlength="100000" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea></label><button @disabled(! $aiConfigured) class="rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:opacity-50">Gerar análise complementar</button></form>
                        @endif
                    @endif
                @endcan

                <div class="mt-5 space-y-4">
                    @foreach ($displayAnalyses as $analysis)
                        <article class="rounded-md border border-gray-200 p-4">
                            <div class="flex flex-wrap justify-between gap-2">
                                <h4 class="font-semibold text-gray-900">
                                    {{ ['pre_meeting' => 'Antes', 'during_meeting' => 'Durante', 'post_meeting' => 'Depois (preliminar)', 'follow_up' => 'Aprendizado complementar'][$analysis->analysis_type] ?? 'Análise' }}
                                    · versão {{ $analysis->prompt_version ? $analysis->version : 'histórica' }}
                                </h4>
                                <p class="text-xs text-gray-500">{{ $analysis->provider }} / {{ $analysis->model }} · {{ $analysis->completed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? $analysis->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · Prompt {{ $analysis->prompt_version ?? 'legado' }}</p>
                            </div>
                            @if ($analysis->status === 'completed' && $analysis->result)
                                @php($aiResult = $analysis->result)
                                @if (filled($aiResult['summary'] ?? null))<p class="mt-3 text-sm font-medium text-gray-800">{{ $aiResult['summary'] }}</p>@endif
                                <details class="mt-4 border-t border-gray-100 pt-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-teal-700">Ver análise completa</summary>
                                @foreach (['findings' => 'Constatações da IA', 'recommendations' => 'Recomendações da IA', 'risks' => 'Riscos', 'opportunities' => 'Oportunidades', 'missing_information' => 'Limitações e informações ausentes', 'historical_basis' => 'Base histórica', 'suggested_next_steps' => 'Próximos passos sugeridos'] as $key => $label)
                                    @if (is_array($aiResult[$key] ?? null) && ! empty($aiResult[$key]))
                                        <div class="mt-3">
                                            <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</h5>
                                            <ul class="mt-1 list-inside list-disc space-y-1 text-sm text-gray-700">
                                                @foreach ($aiResult[$key] as $item)
                                                    @if (is_string($item) && filled($item))
                                                        <li>{{ $item }}</li>
                                                    @elseif ($key === 'recommendations' && is_array($item) && (filled($item['title'] ?? null) || filled($item['detail'] ?? null) || filled($item['justification'] ?? null)))
                                                        <li>
                                                            @if (filled($item['title'] ?? null))<strong>{{ $item['title'] }}</strong>@endif
                                                            @if (filled($item['detail'] ?? null)){{ filled($item['title'] ?? null) ? ': ' : '' }}{{ $item['detail'] }}@endif
                                                            @if (filled($item['justification'] ?? null))<span class="text-gray-500">{{ filled($item['detail'] ?? null) ? ' ' : '' }}{{ $item['justification'] }}</span>@endif
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                                @if (is_numeric($aiResult['confidence'] ?? null))<p class="mt-3 text-xs text-gray-500">Confiança declarada pelo modelo: {{ number_format((float) $aiResult['confidence'] * 100, 0, ',', '.') }}%.</p>@endif
                                @if (is_numeric(data_get($analysis->usage, 'total_tokens')))<p class="mt-1 text-xs text-gray-500">Consumo reportado: {{ data_get($analysis->usage, 'total_tokens') }} tokens.</p>@endif
                                </details>

                                @can('view-costs')
                                    @if ($analysis->provider_cost !== null)<p class="mt-1 text-xs text-gray-500">Custo de IA reportado pelo provedor: {{ number_format((float) $analysis->provider_cost, 6) }} (moeda informada pelo provedor).</p>@endif
                                <details class="mt-3 text-xs text-gray-500"><summary class="cursor-pointer">Dados utilizados pela IA</summary><pre class="mt-2 whitespace-pre-wrap">{{ json_encode($analysis->input_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
                                @else
                                    <p class="mt-3 text-xs text-gray-500">Os dados completos usados pela IA estão ocultos pelas permissões atuais.</p>
                                @endcan
                            @elseif ($analysis->status === 'completed')
                                <p class="mt-3 text-xs text-gray-500">Esta análise foi gerada antes do formato estruturado.</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $analysis->summary }}</p>
                                @if ($analysis->decisions)<ul class="mt-2 list-inside list-disc text-sm text-gray-700">@foreach ($analysis->decisions as $decision)<li>{{ $decision }}</li>@endforeach</ul>@endif
                                @if ($analysis->action_items)<ul class="mt-2 space-y-1 text-sm text-gray-700">@foreach ($analysis->action_items as $action)<li>{{ $action['description'] ?? '' }} · Responsável: {{ $action['responsible_hint'] ?? 'não informado' }} · Prazo: {{ $action['deadline_hint'] ?? 'não informado' }}</li>@endforeach</ul>@endif
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
            @if (($meeting->status === 'completed' || $isPastUnstarted) && $canManageMeetingConclusion && auth()->user()->can('meetings.edit'))
                <div class="flex justify-end border-t border-slate-200 pt-5">
                    <button type="submit" form="meeting-conclusion-form" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                        Salvar conclusão e gerar insights
                    </button>
                </div>
            @endif
            <a href="{{ route('meetings.index') }}" class="text-sm font-medium text-teal-700">Voltar para reuniões</a>
        </div>
    </div>
</x-app-layout>
