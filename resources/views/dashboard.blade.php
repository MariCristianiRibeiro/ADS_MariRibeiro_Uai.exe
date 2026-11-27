<x-app-layout>
    <div x-data="{ duration: 60, people: 4, rate: 150 }" class="dashboard-shell min-h-[calc(100vh-6rem)] py-2">
        <div class="mx-auto flex min-h-[calc(100vh-12rem)] max-w-7xl flex-col px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex flex-col justify-between gap-3 md:flex-row md:items-end">
                <div>
                    <p class="dashboard-kicker">Cronômetro</p>
                    <h1 class="sr-only">Seu tempo vale muito</h1>
                </div>
                <a href="{{ route('meetings.create') }}" class="dashboard-action dashboard-create-action">+ Agendar reunião</a>
            </div>

            <div class="{{ $dashboardMeetings->isEmpty() ? 'flex flex-1 flex-col gap-6' : '' }}">
            <section class="calculator-panel {{ $dashboardMeetings->isEmpty() ? 'flex-1' : 'mb-6' }}" aria-label="Cronômetro da reunião">
                <div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Agende uma reunião ou consulte as registradas.</p>
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
                                    <p class="mt-2 text-xs text-slate-400">Com base nas presenças confirmadas.</p>
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
                                <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="font-mono text-5xl font-bold tabular-nums tracking-tight text-white sm:text-6xl" aria-live="off" x-text="formatTime()">00:00:00</p>
                                    <div class="flex w-full flex-col gap-2 sm:w-48">
                                        <button type="button" x-show="isRunning || canStart" x-on:click="isRunning ? stop() : start()" x-bind:disabled="processing" class="inline-flex h-12 w-full shrink-0 items-center justify-center rounded-md border border-teal-300/40 bg-teal-700 px-[1.1rem] text-sm font-bold text-white shadow-sm transition hover:bg-teal-600 disabled:cursor-wait disabled:opacity-60">
                                            <span x-text="isRunning ? 'Pausar' : (selectedMeeting?.actualDurationSeconds > 0 ? 'Retomar cronômetro' : 'Iniciar cronômetro')">Iniciar cronômetro</span>
                                        </button>
                                        <button type="button" x-show="canFinish" x-on:click="finish()" x-bind:disabled="processing" class="inline-flex h-12 w-full items-center justify-center rounded-md border border-red-200 bg-white px-[1.1rem] text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-60">
                                            <span x-text="processingAction === 'finish' ? 'Finalizando...' : 'Finalizar'">Finalizar</span>
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-slate-400"><span x-text="attendedCount"></span> <span x-text="attendedCount === 1 ? 'presença contabilizada' : 'presenças contabilizadas'"></span></p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-4">
                            <p x-show="isRunning" class="text-sm font-medium text-green-700">O cronômetro continua após atualizar a página.</p>
                            <p x-show="canClosePast" class="text-sm font-medium text-amber-800">Agenda vencida. Encerre-a com os dados da reunião ou reagende.</p>
                            <p x-show="selectedMeeting?.status === 'completed'" class="text-sm font-medium text-slate-600">Reunião encerrada. Tempo e investimento salvos nos relatórios.</p>
                            <p x-show="error" class="text-sm font-medium text-red-700" x-text="error"></p>
                            <div class="flex flex-col items-stretch gap-3 sm:items-end">
                                <div x-show="canClosePast" class="flex flex-col gap-3 sm:flex-row">
                                    <a x-bind:href="selectedMeeting?.closeoutUrl" class="dashboard-action justify-center">Encerrar agenda</a>
                                    <a x-bind:href="selectedMeeting?.editUrl" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50">Reagendar agenda</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-6 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                        <p class="font-medium text-slate-800">Sem reuniões futuras ou em andamento.</p>
                    </div>
                @endif
            </section>

            <section class="summary-panel {{ $dashboardMeetings->isEmpty() ? 'flex-1' : 'mb-6' }}" aria-labelledby="meeting-investment-title">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="panel-overline text-teal-100">Painel hoje</p>
                        <h2 id="meeting-investment-title" class="mt-2 text-xl font-semibold tracking-tight text-white sm:text-2xl">Investimento em reuniões</h2>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3 md:gap-4">
                    @foreach ($meetingInvestmentPeriods as $investment)
                        <article class="rounded-lg border border-white/10 bg-white/5 p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-xs font-bold uppercase tracking-[0.16em] text-teal-100">{{ $investment['label'] }}</h3>
                                <span class="text-xs font-medium text-teal-100/80">{{ $investment['period'] }}</span>
                            </div>
                            <p class="mt-3 text-4xl font-bold tabular-nums tracking-tight text-white sm:text-5xl">
                                @if ($investment['cost'] !== null)
                                    R$ {{ number_format($investment['cost'], 2, ',', '.') }}
                                @else
                                    <span class="text-2xl text-teal-100">Acesso restrito</span>
                                @endif
                            </p>
                            <p class="mt-2 text-sm text-teal-100/85">Equivale a <span class="font-semibold text-white">{{ number_format($investment['collaborator_hours'], 1, ',', '.') }} h</span> de colaboradores</p>
                        </article>
                    @endforeach
                </div>
                <p class="mt-3 text-right text-xs text-teal-100">Apurado pelas durações registradas e presenças confirmadas</p>

            </section>
            </div>

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

            <div class="hidden">
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

            </div>
        </div>
    </div>
</x-app-layout>
