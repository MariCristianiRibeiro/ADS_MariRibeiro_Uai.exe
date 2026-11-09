<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-indigo-600">Prévia antes da confirmação</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $meetingData['title'] }}</h2>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Valores estimados com os custos hora cadastrados</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            @can('view-costs')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([['Custo estimado', 'R$ '.number_format($analysis['total'], 2, ',', '.')], ['Horas colaborador', number_format($analysis['collaborator_hours'], 2, ',', '.').' h'], ['Duração da reunião', number_format($analysis['meeting_hours'], 2, ',', '.').' h'], ['Custo por hora de reunião', 'R$ '.number_format($analysis['cost_per_meeting_hour'], 2, ',', '.')]] as [$label, $value])
                    <article class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</p>
                    </article>
                @endforeach
            </section>
            @endcan

            @can('view-costs')
            <section class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Investimento por participante</h3>
                    <p class="mt-1 text-sm text-gray-500">Horas colaborador somam o tempo de cada pessoa; duração é o tempo de calendário da reunião.</p>
                    <div class="mt-4 divide-y divide-gray-100">
                        @forelse ($analysis['participants'] as $participant)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div><p class="font-medium text-gray-800">{{ $participant['name'] }}</p><p class="text-xs text-gray-500">{{ $participant['position'] }} · {{ $participant['department'] }} · Papel: {{ $participant['meeting_role'] }} · R$ {{ number_format($participant['hourly_rate'], 2, ',', '.') }}/h</p></div>
                                <div class="text-right"><p class="font-semibold text-gray-900">R$ {{ number_format($participant['cost'], 2, ',', '.') }}</p><p class="text-xs text-gray-500">{{ number_format($participant['collaborator_hours'], 2, ',', '.') }} h</p></div>
                            </div>
                        @empty
                            <p class="py-4 text-sm text-gray-500">Nenhum participante selecionado; o custo estimado é zero.</p>
                        @endforelse
                    </div>
                    <h4 class="mt-5 text-sm font-semibold text-gray-700">Custo por departamento</h4>
                    <div class="mt-2 space-y-1">
                        @forelse ($analysis['by_department'] as $department => $cost)
                            <div class="flex justify-between text-sm"><span class="text-gray-600">{{ $department }}</span><span class="font-medium">R$ {{ number_format($cost, 2, ',', '.') }}</span></div>
                        @empty
                            <p class="text-sm text-gray-500">Sem valores por departamento.</p>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Economia potencial e recorrência</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-md bg-emerald-50 p-4"><p class="text-xs text-emerald-800">Redução de 25% na duração</p><p class="mt-1 text-lg font-semibold text-emerald-900">R$ {{ number_format($analysis['duration_savings'], 2, ',', '.') }}</p><p class="text-xs text-emerald-800">{{ number_format($analysis['duration_savings_hours'], 2, ',', '.') }} horas colaborador</p></div>
                        <div class="rounded-md bg-emerald-50 p-4"><p class="text-xs text-emerald-800">Redução de um participante</p><p class="mt-1 text-lg font-semibold text-emerald-900">R$ {{ number_format($analysis['participant_savings'], 2, ',', '.') }}</p><p class="text-xs text-emerald-800">por encontro, estimativa conservadora</p></div>
                    </div>
                    <div class="mt-4 rounded-md border border-gray-200 p-4">
                        <p class="text-sm font-medium text-gray-800">Projeção da recorrência</p>
                        @if (($meetingData['recurrence_type'] ?? 'single') === 'recurring')
                            @php
                                $frequencyLabel = ['daily' => 'diária', 'weekly' => 'semanal', 'monthly' => 'mensal', 'yearly' => 'anual'][$meetingData['recurrence_frequency'] ?? ''] ?? 'não definida';
                            @endphp
                            <p class="mt-1 text-sm text-gray-600">{{ $frequencyLabel }}, a cada {{ $meetingData['recurrence_interval'] ?? 1 }} período(s) · média de {{ number_format($analysis['monthly_occurrences'], 2, ',', '.') }} encontro(s)/mês · R$ {{ number_format($analysis['monthly_cost'], 2, ',', '.') }}/mês · R$ {{ number_format($analysis['annual_cost'], 2, ',', '.') }} no próximo ano</p>
                        @else
                            <p class="mt-1 text-sm text-gray-600">Reunião única; sem projeção recorrente.</p>
                        @endif
                    </div>
                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-gray-800">Impacto na agenda desta semana</h4>
                        <p class="mt-1 text-sm text-gray-600">{{ $analysis['weekly_agenda']->count() }} reunião(ões) e {{ number_format($analysis['weekly_agenda_hours'], 2, ',', '.') }} horas de agenda nesta semana já incluem pelo menos um participante selecionado.</p>
                        @foreach ($analysis['weekly_agenda']->take(4) as $scheduled)
                            <p class="mt-1 text-xs text-gray-500">{{ $scheduled->date?->format('d/m/Y') }} · {{ $scheduled->title }}</p>
                        @endforeach
                    </div>
                </article>
            </section>
            @endcan

            <section class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Histórico relacionado</h3>
                    @forelse ($analysis['similar_meetings'] as $similar)
                        <div class="mt-4 border-l-2 border-indigo-200 pl-3">
                            <p class="font-medium text-gray-800">{{ $similar->title }} <span class="font-normal text-gray-500">· {{ $similar->date?->format('d/m/Y') }}</span></p>
                            <p class="mt-1 text-sm text-gray-600">Conclusão: {{ $similar->conclusion?->outcome ?: $similar->conclusion?->summary ?: 'Sem conclusão registrada.' }}</p>
                            <p class="text-xs text-gray-500">{{ $similar->actionItems->where('status', 'completed')->count() }} de {{ $similar->actionItems->count() }} encaminhamento(s) concluído(s).</p>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-500">Não há reuniões concluídas semelhantes encontradas por título, objetivo ou categoria.</p>
                    @endforelse
                    <h4 class="mt-5 text-sm font-semibold text-gray-800">Demandas pendentes sobre o tema</h4>
                    @forelse ($analysis['pending_items'] as $item)
                        <p class="mt-2 text-sm text-gray-600">{{ $item->title }} · {{ $item->status }} <span class="text-xs text-gray-400">({{ $item->meeting?->title }})</span></p>
                    @empty
                        <p class="mt-1 text-sm text-gray-500">Nenhuma demanda pendente relacionada foi localizada.</p>
                    @endforelse
                </article>

                <article class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Insights gerados por Inteligência Artificial</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        @if ($analysis['ai_generated'])
                            {{ strtoupper($analysis['ai_metadata']['provider']) }} / {{ $analysis['ai_metadata']['model'] }} · Prompt {{ $analysis['ai_metadata']['prompt_version'] }}. Os indicadores financeiros são calculados pelo sistema.
                        @else
                            Análise de IA indisponível: {{ $analysis['ai_error'] }} Nenhuma recomendação automática foi apresentada. Os indicadores financeiros continuam calculados pelo sistema.
                        @endif
                    </p>
                    @if ($analysis['ai_result'])
                        @php($aiResult = $analysis['ai_result'])
                        <div class="mt-3 space-y-3">
                            <p class="text-sm font-medium text-gray-800">{{ $aiResult['summary'] }}</p>
                            @foreach (['findings' => 'Constatações', 'recommendations' => 'Recomendações', 'risks' => 'Riscos', 'opportunities' => 'Oportunidades', 'missing_information' => 'Informações ausentes', 'historical_basis' => 'Base histórica', 'suggested_next_steps' => 'Próximos passos sugeridos'] as $key => $label)
                                @if (! empty($aiResult[$key]))
                                    <div>
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</h4>
                                        <ul class="mt-1 list-inside list-disc space-y-1 text-sm text-gray-700">
                                            @foreach ($aiResult[$key] as $item)
                                                <li>@if (is_array($item))<strong>{{ $item['title'] }}</strong>: {{ $item['detail'] }} <span class="text-gray-500">{{ $item['justification'] }}</span>@else{{ $item }}@endif</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                            <p class="text-xs text-gray-500">Confiança informada pelo modelo: {{ number_format((float) $aiResult['confidence'] * 100, 0, ',', '.') }}% · Gerada em {{ now()->format('d/m/Y H:i') }}.</p>
                            <details class="text-xs text-gray-500"><summary class="cursor-pointer">Dados utilizados</summary><pre class="mt-2 whitespace-pre-wrap">{{ json_encode($analysis['ai_input_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
                            @if (filled($analysis['ai_metadata']['usage']['total_tokens'] ?? null))
                                <p class="text-xs text-gray-500">Consumo registrado pelo provedor: {{ $analysis['ai_metadata']['usage']['total_tokens'] }} tokens totais.</p>
                            @endif
                            @if (is_numeric($analysis['ai_metadata']['usage']['cost'] ?? null))
                                <p class="text-xs text-gray-500">Custo de IA informado pelo provedor: {{ number_format((float) $analysis['ai_metadata']['usage']['cost'], 6) }} (moeda informada pelo provedor).</p>
                            @endif
                        </div>
                    @endif
                </article>
            </section>

            <section class="flex flex-wrap justify-end gap-3 rounded-lg bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('meetings.adjust') }}">
                    @csrf
                    @foreach ($meetingData as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $nestedKey => $nestedValue)
                                @if (is_array($nestedValue))
                                    @foreach ($nestedValue as $itemValue)<input type="hidden" name="{{ $key }}[{{ $nestedKey }}][]" value="{{ $itemValue }}">@endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}[{{ $nestedKey }}]" value="{{ $nestedValue }}">
                                @endif
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Ajustar agenda</button>
                </form>
                <form method="POST" action="{{ route('meetings.store') }}">
                    @csrf
                    <input type="hidden" name="preflight_token" value="{{ $preflightToken }}">
                    @foreach ($meetingData as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $nestedKey => $nestedValue)
                                @if (is_array($nestedValue))
                                    @foreach ($nestedValue as $itemValue)<input type="hidden" name="{{ $key }}[{{ $nestedKey }}][]" value="{{ $itemValue }}">@endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}[{{ $nestedKey }}]" value="{{ $nestedValue }}">
                                @endif
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit" class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Confirmar reunião</button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
