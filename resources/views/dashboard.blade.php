<x-app-layout>
    <div x-data="{ duration: 60, people: 4, rate: 150 }" class="dashboard-shell min-h-[calc(100vh-4rem)] py-8 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="dashboard-kicker">Central de reuniões</p>
                    <h1 class="dashboard-title">Quanto custa uma conversa bem conduzida?</h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600">Faça uma estimativa rápida do investimento da sua próxima reunião e decida com mais clareza.</p>
                </div>
                <a href="{{ route('meetings.create') }}" class="dashboard-action">+ Agendar reunião</a>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                <section class="calculator-panel" aria-labelledby="calculator-title">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="panel-overline">Calculadora rápida</p>
                            <h2 id="calculator-title" class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Custo da reunião</h2>
                        </div>
                        <div class="calculator-mark" aria-hidden="true">∑</div>
                    </div>

                    <div class="mt-9 grid gap-5 sm:grid-cols-3">
                        <label class="calculator-field"><span>Duração</span><div class="input-with-suffix"><input x-model.number="duration" type="number" min="1" max="1440" step="5" aria-label="Duração em minutos"><span>min</span></div></label>
                        <label class="calculator-field"><span>Participantes</span><div class="input-with-suffix"><input x-model.number="people" type="number" min="1" max="999" aria-label="Número de participantes"><span>pessoas</span></div></label>
                        <label class="calculator-field"><span>Valor por hora</span><div class="input-with-suffix"><span>R$</span><input x-model.number="rate" type="number" min="0" step="10" aria-label="Valor por hora"></div></label>
                    </div>

                    <div class="mt-9 border-t border-slate-200 pt-7">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div><p class="text-sm font-medium text-slate-500">Investimento estimado</p><p class="mt-1 text-5xl font-semibold tracking-tight text-slate-950" x-text="new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((duration / 60) * people * rate)"></p></div>
                            <p class="rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-800"><span x-text="(duration / 60).toFixed(1).replace('.', ',')"></span>h × <span x-text="people"></span> pessoas</p>
                        </div>
                        <div class="mt-7 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-teal-400 via-cyan-500 to-blue-600 transition-all duration-300" :style="`width: ${Math.min(100, Math.max(8, duration / 7.2))}%`"></div></div>
                    </div>
                </section>

                <aside class="summary-panel">
                    <div class="flex items-center justify-between"><p class="panel-overline text-teal-100">Seu painel hoje</p><span class="live-dot">ao vivo</span></div>
                    <div class="mt-10"><p class="text-sm text-teal-100">Participantes ativos</p><p class="mt-2 text-6xl font-semibold tracking-tight text-white">{{ $activeParticipantsCount }}</p></div>
                    <div class="mt-10 grid grid-cols-2 gap-4 border-t border-white/15 pt-5"><div><p class="text-xs text-teal-100">Usuários</p><p class="mt-1 text-2xl font-semibold text-white">{{ $usersCount }}</p></div><div><p class="text-xs text-teal-100">Participantes</p><p class="mt-1 text-2xl font-semibold text-white">{{ $participantsCount }}</p></div></div>
                </aside>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6"><p class="text-sm text-slate-500">Pronto para transformar o cálculo em ação?</p><div class="flex gap-3"><a href="{{ route('meetings.index') }}" class="dashboard-link">Ver reuniões</a>@can('manage-users')<a href="{{ route('users.index') }}" class="dashboard-link">Gerenciar participantes</a>@endcan</div></div>
        </div>
    </div>
</x-app-layout>
