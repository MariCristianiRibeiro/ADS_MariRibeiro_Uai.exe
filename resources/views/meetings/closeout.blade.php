<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Conclusão da reunião</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $meeting->title }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Revise os campos destacados:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-xl bg-white p-5 shadow-sm sm:p-7">
                <div class="border-b border-gray-100 pb-4">
                    <p class="text-sm text-gray-600">
                        Data planejada:
                        <strong>{{ $meeting->date?->format('d/m/Y') }}</strong>
                        @if ($meeting->time)
                            às <strong>{{ substr($meeting->time, 0, 5) }}</strong>
                        @endif
                    </p>
                    @if ($isPastUnstarted)
                        <p class="mt-3 rounded-md bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                            Esta agenda venceu sem iniciar o cronômetro. Informe o motivo e registre o resultado para encerrá-la.
                        </p>
                    @else
                        <p class="mt-3 text-sm leading-6 text-gray-600">
                            Preencha os dados principais da reunião. Os indicadores adicionais são opcionais.
                        </p>
                    @endif
                </div>

                @if (filled($meeting->objective))
                    <div class="mt-5 rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Objetivo planejado</p>
                        <p class="mt-1 text-sm leading-6 text-gray-800">{{ $meeting->objective }}</p>
                    </div>
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-100 p-4">
                        <p class="text-xs text-gray-500">Duração prevista</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $meeting->duration_minutes ? number_format($meeting->duration_minutes / 60, 1, ',', '.').' h' : 'Não informada' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 p-4">
                        <p class="text-xs text-gray-500">Tempo realizado</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $meeting->actual_duration_seconds !== null ? number_format($meeting->actual_duration_seconds / 3600, 1, ',', '.').' h' : 'Sem registro do cronômetro' }}</p>
                    </div>
                    @if ($costComparison)
                        <div class="rounded-lg border border-gray-100 p-4">
                            <p class="text-xs text-gray-500">Custo previsto</p>
                            <p class="mt-1 font-semibold text-gray-900">R$ {{ number_format($costComparison['estimated'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-4">
                            <p class="text-xs text-gray-500">Custo realizado</p>
                            <p class="mt-1 font-semibold text-gray-900">{{ $costComparison['actual'] === null ? 'Sem registro de tempo real' : 'R$ '.number_format($costComparison['actual'], 2, ',', '.') }}</p>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('meetings.conclusion.save', $meeting) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PUT')

                    @if ($requiresScheduleChangeReason)
                        <label for="schedule_change_reason" class="block text-sm font-medium text-gray-800">
                            {{ $isPastUnstarted ? 'Por que a reunião não aconteceu na data planejada?' : 'Por que a agenda foi antecipada?' }}
                            <textarea
                                id="schedule_change_reason"
                                name="schedule_change_reason"
                                required
                                minlength="10"
                                maxlength="5000"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            >{{ old('schedule_change_reason', $meeting->conclusion?->schedule_change_reason) }}</textarea>
                            @error('schedule_change_reason') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif

                    <label for="summary" class="block text-sm font-medium text-gray-800">
                        Resumo da reunião
                        <textarea
                            id="summary"
                            name="summary"
                            required
                            minlength="10"
                            maxlength="10000"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        >{{ old('summary', $meeting->conclusion?->summary) }}</textarea>
                        @error('summary') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label for="outcome" class="block text-sm font-medium text-gray-800">
                        Qual foi o resultado?
                        <textarea
                            id="outcome"
                            name="outcome"
                            required
                            minlength="10"
                            maxlength="10000"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        >{{ old('outcome', $meeting->conclusion?->outcome) }}</textarea>
                        @error('outcome') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label for="objective_achievement" class="block text-sm font-medium text-gray-800">
                        O objetivo foi alcançado?
                        <select
                            id="objective_achievement"
                            name="objective_achievement"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        >
                            <option value="">Selecione</option>
                            @foreach (['yes' => 'Sim', 'partially' => 'Parcialmente', 'no' => 'Não'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('objective_achievement', $meeting->conclusion?->objective_achievement) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('objective_achievement') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <details class="rounded-md border border-gray-200 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Indicadores adicionais (opcional)</summary>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Decisões tomadas
                                <input type="number" min="0" name="decisions_count" value="{{ old('decisions_count', $meeting->conclusion?->decisions_count ?? 0) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Assuntos concluídos
                                <input type="number" min="0" name="completed_topics_count" value="{{ old('completed_topics_count', $meeting->conclusion?->completed_topics_count ?? 0) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Produtividade (1 a 5)
                                <input type="number" min="1" max="5" name="productivity_score" value="{{ old('productivity_score', $meeting->conclusion?->productivity_score) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Retorno percebido (1 a 5)
                                <input type="number" min="1" max="5" name="investment_return_score" value="{{ old('investment_return_score', $meeting->conclusion?->investment_return_score) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                                Assuntos que ficaram pendentes
                                <textarea name="pending_topics" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('pending_topics', $meeting->conclusion?->pending_topics) }}</textarea>
                            </label>
                            @foreach ([
                                'objective_changed' => 'O objetivo mudou?',
                                'duration_sufficient' => 'A duração foi suficiente?',
                                'time_well_used' => 'O tempo foi bem aproveitado?',
                                'replaceable_by_email' => 'Poderia ser substituída por e-mail?',
                                'needs_follow_up' => 'Será necessário outro encontro?',
                            ] as $field => $label)
                                <label class="block text-sm font-medium text-gray-700">
                                    {{ $label }}
                                    <select name="{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="">Não informado</option>
                                        <option value="1" @selected((string) old($field, $meeting->conclusion?->{$field}) === '1')>Sim</option>
                                        <option value="0" @selected((string) old($field, $meeting->conclusion?->{$field}) === '0')>Não</option>
                                    </select>
                                </label>
                            @endforeach
                            @if ($meeting->meetingParticipants->isNotEmpty())
                                <fieldset class="space-y-3 rounded-md border border-gray-200 p-3 sm:col-span-2">
                                    <legend class="px-1 text-sm font-semibold text-gray-800">Participação (opcional)</legend>
                                    <p class="text-xs text-gray-500">Registre presença e participação para contextualizar os indicadores, sem classificar desempenho individual.</p>
                                    @foreach ($meeting->meetingParticipants as $participant)
                                        <div class="grid gap-3 border-b border-gray-100 py-3 last:border-0 sm:grid-cols-2">
                                            <p class="text-sm font-medium text-gray-700 sm:col-span-2">{{ $participant->user?->name ?? 'Participante' }}</p>
                                            <label class="block text-sm text-gray-700">
                                                Presença
                                                <select name="participation[{{ $participant->id }}][attendance_status]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                    @foreach (['pending' => 'Pendente', 'present' => 'Presente', 'absent' => 'Ausente', 'confirmed' => 'Confirmada'] as $value => $label)
                                                        <option value="{{ $value }}" @selected(old("participation.{$participant->id}.attendance_status", $participant->attendance_status) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            @foreach ([
                                                'participation_effective' => 'Participação efetiva?',
                                                'relevant_contribution' => 'Contribuição relevante?',
                                                'contributed_to_decision' => 'Contribuiu para decisões?',
                                                'participation_necessary' => 'Participação necessária?',
                                            ] as $field => $label)
                                                <label class="block text-sm text-gray-700">
                                                    {{ $label }}
                                                    <select name="participation[{{ $participant->id }}][{{ $field }}]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                        <option value="">Não informado</option>
                                                        <option value="1" @selected((string) old("participation.{$participant->id}.{$field}", $participant->{$field}) === '1')>Sim</option>
                                                        <option value="0" @selected((string) old("participation.{$participant->id}.{$field}", $participant->{$field}) === '0')>Não</option>
                                                    </select>
                                                </label>
                                            @endforeach
                                            <label class="block text-sm text-gray-700">
                                                Autoavaliação opcional (1 a 5)
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="5"
                                                    name="participation[{{ $participant->id }}][self_rating]"
                                                    value="{{ old("participation.{$participant->id}.self_rating", $participant->self_rating) }}"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                                >
                                            </label>
                                        </div>
                                    @endforeach
                                </fieldset>
                            @endif
                        </div>
                    </details>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Voltar ao painel
                        </a>
                        <button class="inline-flex items-center justify-center rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                            Salvar conclusão
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
