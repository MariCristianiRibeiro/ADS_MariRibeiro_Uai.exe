<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-teal-700">{{ $meeting->category ?: 'Reunião' }}</p><h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $meeting->title }}</h2></div>
            <a href="{{ route('meetings.edit', $meeting) }}" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white">Editar reunião</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success')) <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div> @endif
            @if ($errors->any()) <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><p class="font-semibold">Revise os campos informados.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

            <section class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                    <h3 class="font-semibold text-gray-900">Planejamento</h3>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs uppercase text-gray-500">Data e horário</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->date?->format('d/m/Y') }} · {{ $meeting->time ? substr($meeting->time, 0, 5) : 'horário não definido' }}{{ $meeting->end_time ? '–'.substr($meeting->end_time, 0, 5) : '' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Status e prioridade</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->status }} · {{ $meeting->priority }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Organizador</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->organizer?->name ?? 'Não definido' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Local e formato</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->location ?? 'Não definido' }} · {{ $meeting->meeting_format ?? 'Formato não definido' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Duração prevista</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->duration_minutes }} minutos</dd></div>
                        <div><dt class="text-xs uppercase text-gray-500">Recorrência</dt><dd class="mt-1 text-sm text-gray-800">{{ $meeting->recurrence_type === 'recurring' ? ($meeting->recurrence_frequency.' · a cada '.($meeting->recurrence_interval ?? 1)) : 'Única' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Objetivo</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->objective ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Pauta</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->agenda ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Resultado esperado</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $meeting->expected_outcome ?: '—' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Participantes</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @forelse ($meeting->meetingParticipants as $participant)
                            <li class="py-3">
                                <p class="font-medium text-gray-800">{{ $participant->user?->name }}</p>
                                <p class="text-xs text-gray-500">{{ str_replace('_', ' ', $participant->role) }} · Convite: {{ $participant->invitation_status }} · Confirmação: {{ $participant->confirmation_status }} · Presença: {{ $participant->attendance_status }}</p>
                                <p class="text-xs text-gray-500">{{ $participant->costSnapshot?->jobPosition?->name ?? $participant->user?->jobPosition?->name ?? 'Cargo não definido' }} · {{ $participant->costSnapshot?->team?->name ?? $participant->user?->team?->name ?? 'Equipe não definida' }}</p>
                                @can('view-costs')<p class="text-xs text-gray-500">Snapshot: R$ {{ number_format((float) ($participant->costSnapshot?->hourly_rate ?? 0), 2, ',', '.') }}/h</p>@endcan
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-500">Nenhum participante vinculado.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Decisões</h3>
                    <ul class="mt-3 space-y-3">
                        @forelse ($meeting->decisions as $decision)
                            <li class="rounded-md bg-gray-50 p-3"><p class="font-medium text-gray-800">{{ $decision->title }}</p><p class="whitespace-pre-line text-sm text-gray-600">{{ $decision->description }}</p><p class="mt-1 text-xs text-gray-500">{{ $decision->decisionMaker?->name ?? 'Responsável não informado' }} · {{ $decision->decided_at?->format('d/m/Y H:i') }}</p></li>
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
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Conclusão</h3>
                    @if ($meeting->conclusion)<p class="mt-2 text-xs text-gray-500">Registrada por {{ $meeting->conclusion->recorder?->name ?? 'usuário removido' }} · {{ $meeting->conclusion->concluded_at?->format('d/m/Y H:i') }}</p>@endif
                    <form method="POST" action="{{ route('meetings.conclusion.save', $meeting) }}" class="mt-3 space-y-3">@csrf @method('PUT')
                        <label class="block text-sm font-medium text-gray-700">Resumo<textarea name="summary" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('summary', $meeting->conclusion?->summary) }}</textarea></label>
                        <label class="block text-sm font-medium text-gray-700">Resultado obtido<textarea name="outcome" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('outcome', $meeting->conclusion?->outcome) }}</textarea></label>
                        @can('meetings.edit')<button class="rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase text-white">Salvar conclusão</button>@endcan
                    </form>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold text-gray-900">Encaminhamentos</h3><span class="text-xs text-gray-500">Originados nesta reunião</span></div>
                <div class="mt-3 space-y-3">
                    @forelse ($meeting->actionItems as $actionItem)
                        <article class="rounded-md border border-gray-200 p-4">
                            <div class="flex flex-wrap justify-between gap-2"><div><h4 class="font-medium text-gray-800">{{ $actionItem->title }}</h4><p class="whitespace-pre-line text-sm text-gray-600">{{ $actionItem->description }}</p></div><span class="text-xs font-semibold text-gray-600">{{ $actionItem->status }} · {{ $actionItem->priority }}</span></div>
                            <p class="mt-2 text-xs text-gray-500">Responsável: {{ $actionItem->responsible?->name ?? 'Não atribuído' }} · Departamento: {{ $actionItem->directorate?->name ?? 'Não definido' }} · Prazo: {{ $actionItem->due_date?->format('d/m/Y') ?? 'Sem prazo' }}</p>
                            @if ($actionItem->dependencies->isNotEmpty())<p class="mt-1 text-xs text-gray-500">Dependências: {{ $actionItem->dependencies->pluck('title')->implode(', ') }}</p>@endif
                            @if ($actionItem->delivered_result)<p class="mt-2 text-sm text-gray-700">Resultado entregue: {{ $actionItem->delivered_result }}</p>@endif
                            @can('meetings.edit')
                                <form method="POST" action="{{ route('meetings.action-items.update', [$meeting, $actionItem]) }}" class="mt-3 grid gap-2 sm:grid-cols-3">@csrf @method('PATCH')
                                    <select name="status" class="rounded-md border-gray-300 text-sm"><option value="open" @selected($actionItem->status === 'open')>Aberto</option><option value="in_progress" @selected($actionItem->status === 'in_progress')>Em andamento</option><option value="blocked" @selected($actionItem->status === 'blocked')>Bloqueado</option><option value="completed" @selected($actionItem->status === 'completed')>Concluído</option><option value="canceled" @selected($actionItem->status === 'canceled')>Cancelado</option></select>
                                    <input type="date" name="due_date" value="{{ $actionItem->due_date?->format('Y-m-d') }}" class="rounded-md border-gray-300 text-sm">
                                    <input name="delivered_result" value="{{ $actionItem->delivered_result }}" placeholder="Resultado entregue" class="rounded-md border-gray-300 text-sm">
                                    <label class="text-sm text-gray-600"><input type="hidden" name="requires_follow_up" value="0"><input type="checkbox" name="requires_follow_up" value="1" @checked($actionItem->requires_follow_up)> Necessita cobrança</label>
                                    <button class="rounded-md bg-gray-700 px-3 py-2 text-xs font-semibold uppercase text-white sm:col-span-2">Atualizar</button>
                                </form>
                            @endcan
                        </article>
                    @empty <p class="text-sm text-gray-500">Nenhum encaminhamento cadastrado.</p> @endforelse
                </div>
                @can('meetings.edit')
                    <form method="POST" action="{{ route('meetings.action-items.store', $meeting) }}" class="mt-5 grid gap-3 border-t pt-4 md:grid-cols-2">@csrf
                        <label class="block text-sm font-medium text-gray-700">Título<input name="title" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                        <label class="block text-sm font-medium text-gray-700">Responsável<select name="responsible_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-gray-700 md:col-span-2">Descrição<textarea name="description" required rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea></label>
                        <label class="block text-sm font-medium text-gray-700">Departamento<select name="directorate_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não definido</option>@foreach ($directorates as $directorate)<option value="{{ $directorate->id }}">{{ $directorate->name }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-gray-700">Prazo<input type="date" name="due_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></label>
                        <label class="block text-sm font-medium text-gray-700">Prioridade<select name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="low">Baixa</option><option value="normal" selected>Normal</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></label>
                        <label class="block text-sm font-medium text-gray-700">Dependências<select name="dependencies[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($meeting->actionItems as $actionItem)<option value="{{ $actionItem->id }}">{{ $actionItem->title }}</option>@endforeach</select></label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_follow_up" value="1"> Necessita cobrança</label>
                        <button class="w-fit rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase text-white">Criar encaminhamento</button>
                    </form>
                @endcan
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900">Histórico</h3>
                <ol class="mt-3 space-y-3">
                    @forelse ($meeting->history as $event)
                        <li class="border-l-2 border-teal-600 pl-3"><p class="text-sm font-medium text-gray-800">{{ str_replace('_', ' ', $event->event) }}</p><p class="text-xs text-gray-500">{{ $event->actor?->name ?? 'Sistema' }} · {{ $event->created_at?->format('d/m/Y H:i:s') }}</p>@if ($event->changes)<pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-xs text-gray-600">{{ json_encode($event->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif</li>
                    @empty <li class="text-sm text-gray-500">Sem eventos registrados.</li> @endforelse
                </ol>
            </section>
            <a href="{{ route('meetings.index') }}" class="text-sm font-medium text-teal-700">Voltar para reuniões</a>
        </div>
    </div>
</x-app-layout>
