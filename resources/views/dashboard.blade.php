<x-app-layout>
    <div x-data="{ duration: 60, people: 4, rate: 150 }" class="dashboard-shell min-h-[calc(100vh-4rem)] py-8 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="dashboard-kicker">Central de reuniões</p>
                    <h1 class="dashboard-title">Seu tempo vale muito</h1>
                </div>
                <a href="{{ route('meetings.create') }}" class="dashboard-action">+ Agendar reunião</a>
            </div>

            <section class="calculator-panel mb-6" aria-labelledby="meeting-timer-title">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="panel-overline">Acompanhe em tempo real</p>
                        <h2 id="meeting-timer-title" class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Cronômetro da reunião</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Selecione uma reunião criada por você para contabilizar o tempo e o investimento realizado.</p>
                    </div>
                    <a href="{{ route('meetings.index') }}" class="dashboard-link">Ver reuniões</a>
                </div>

                @if ($dashboardMeetings->isNotEmpty())
                    <div x-data="dashboardMeetingTimer(@js($dashboardMeetings), @js($selectedMeetingId))" class="mt-6">
                        <label for="dashboard-meeting" class="block text-sm font-medium text-slate-700">Reunião</label>
                        <select id="dashboard-meeting" x-model.number="selectedMeetingId" x-on:change="selectMeeting()" x-bind:disabled="isRunning || processing" class="mt-2 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 disabled:bg-slate-100">
                            @foreach ($dashboardMeetings as $meeting)
                                <option value="{{ $meeting['id'] }}">{{ $meeting['title'] }} — {{ $meeting['date'] }}</option>
                            @endforeach
                        </select>

                        <div class="mt-6 grid gap-6 rounded-xl bg-slate-950 p-6 text-white shadow-lg {{ $canViewCosts ? 'sm:grid-cols-2' : '' }} sm:items-center sm:p-8">
                            @if ($canViewCosts)
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-teal-300">Investimento acumulado</p>
                                    <p class="mt-3 text-5xl font-bold tabular-nums tracking-tight text-white sm:text-6xl lg:text-7xl" x-text="formatCurrency(investedValue)">R$ 0,00</p>
                                    <p class="mt-2 text-xs text-slate-400">Calculado pelo valor-hora das presenças confirmadas.</p>
                                </div>
                            @endif
                            <div>
                                <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-teal-300">
                                    <span class="relative flex h-3 w-3">
                                        <span x-show="isRunning" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex h-3 w-3 rounded-full" x-bind:class="isRunning ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                    </span>
                                    <span>Tempo decorrido</span>
                                </div>
                                <p class="mt-3 font-mono text-5xl font-bold tabular-nums tracking-tight text-white sm:text-6xl" aria-live="off" x-text="formatTime()">00:00:00</p>
                                <p class="mt-2 text-sm text-slate-400"><span x-text="attendedCount"></span> participante(s) com presença contabilizada</p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-4">
                            <p x-show="isRunning" class="text-sm font-medium text-green-700">O cronômetro continua contando mesmo se você atualizar a página.</p>
                            <p x-show="canClosePast" class="text-sm font-medium text-amber-800">Esta agenda venceu e não pode ser iniciada com a data antiga. Você pode encerrá-la informando o motivo e os dados de conclusão, ou reagendá-la.</p>
                            <p x-show="selectedMeeting?.status === 'completed'" class="text-sm font-medium text-slate-600">Reunião encerrada. O tempo e o investimento foram salvos para os relatórios.</p>
                            <p x-show="error" class="text-sm font-medium text-red-700" x-text="error"></p>
                            <div class="flex flex-col items-stretch gap-3 sm:items-end">
                                <button type="button" x-show="canStart" x-on:click="start()" x-bind:disabled="processing" class="dashboard-action justify-center disabled:cursor-wait disabled:opacity-60">Iniciar cronômetro</button>
                                <div x-show="canClosePast" class="flex flex-col gap-3 sm:flex-row">
                                    <a x-bind:href="selectedMeeting?.closeoutUrl" class="dashboard-action justify-center">Encerrar agenda</a>
                                    <a x-bind:href="selectedMeeting?.editUrl" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50">Reagendar agenda</a>
                                </div>
                                <button type="button" x-show="isRunning" x-on:click="stop()" x-bind:disabled="processing" class="dashboard-action disabled:cursor-wait disabled:opacity-60">Pausar e salvar tempo</button>
                                <button type="button" x-show="canFinish" x-on:click="finish()" x-bind:disabled="processing" class="inline-flex items-center justify-center rounded-md border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-60">
                                    <span x-text="processing ? 'Finalizando...' : 'Finalizar reunião e encerrar cálculo'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-6 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                        <p class="font-medium text-slate-800">Você não tem reuniões agendadas para o futuro nem reuniões em andamento.</p>
                        <p class="mt-1 text-sm text-slate-500">Agende uma reunião ou consulte os registros já existentes.</p>
                        <a href="{{ route('meetings.create') }}" class="dashboard-action mt-4">Criar reunião</a>
                    </div>
                @endif
            </section>

            @if ($pastMeetings->isNotEmpty())
                <details class="calculator-panel mb-6">
                    <summary class="cursor-pointer font-semibold text-slate-900">
                        Agendas vencidas ({{ $pastMeetings->count() }}) — encerrar ou reagendar
                    </summary>
                    <ul class="mt-4 space-y-3">
                        @foreach ($pastMeetings as $pastMeeting)
                            <li class="flex flex-col justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $pastMeeting->title }}</p>
                                    <p class="mt-1 text-sm text-slate-600">Data planejada: {{ $pastMeeting->date?->format('d/m/Y') }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('meetings.closeout', $pastMeeting) }}" class="dashboard-action justify-center">Encerrar agenda</a>
                                    <a href="{{ route('meetings.edit', $pastMeeting) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50">Reagendar agenda</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <div class="grid gap-6">
                <section class="calculator-panel" aria-labelledby="calculator-title">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="panel-overline">Calculadora rápida</p>
                            <h2 id="calculator-title" class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Custo da reunião</h2>
                        </div>
                    </div>

                    <div class="mt-9 grid gap-5 sm:grid-cols-3">
                        <label class="calculator-field"><span>Duração</span><div class="input-with-suffix"><input x-model.number="duration" type="number" min="1" max="1440" step="5" aria-label="Duração em minutos"><span>min</span></div></label>
                        <label class="calculator-field"><span>Participantes</span><div class="input-with-suffix"><input x-model.number="people" type="number" min="1" max="999" aria-label="Número de participantes"><span>pessoas</span></div></label>
                        <label class="calculator-field"><span>Valor por hora</span><div class="input-with-suffix"><span>R$</span><input x-model.number="rate" type="number" min="0" step="10" aria-label="Valor por hora"></div></label>
                    </div>

                    <div class="mt-9 border-t border-slate-200 pt-7">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div><p class="text-sm font-medium text-slate-500">Investimento estimado</p><p class="mt-1 text-5xl font-semibold tracking-tight text-slate-950" x-text="new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((duration / 60) * people * rate)"></p></div>
                        </div>
                        <div class="mt-7 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-teal-400 via-cyan-500 to-blue-600 transition-all duration-300" :style="`width: ${Math.min(100, Math.max(8, duration / 7.2))}%`"></div></div>
                    </div>
                </section>

                <aside class="summary-panel">
                    <div class="flex items-center justify-between"><p class="panel-overline text-teal-100">Seu painel hoje</p></div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-3 sm:items-end">
                        <div><p class="text-sm text-teal-100">Participantes ativos</p><p class="mt-2 text-6xl font-semibold tracking-tight text-white">{{ $activeParticipantsCount }}</p></div>
                        <div class="border-t border-white/15 pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0"><p class="text-xs text-teal-100">Usuários</p><p class="mt-1 text-2xl font-semibold text-white">{{ $usersCount }}</p></div>
                        <div class="border-t border-white/15 pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0"><p class="text-xs text-teal-100">Participantes</p><p class="mt-1 text-2xl font-semibold text-white">{{ $participantsCount }}</p></div>
                    </div>
                </aside>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6"><p class="text-sm text-slate-500">Pronto para transformar o cálculo em ação?</p><div class="flex gap-3"><a href="{{ route('meetings.index') }}" class="dashboard-link">Ver reuniões</a>@can('manage-users')<a href="{{ route('users.index') }}" class="dashboard-link">Gerenciar participantes</a>@endcan</div></div>
        </div>
    </div>
</x-app-layout>
