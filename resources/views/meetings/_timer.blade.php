@php
    $selectedParticipantIds = collect($selectedParticipantIds ?? [])->map(fn ($id) => (int) $id)->all();
    $timerParticipants = Auth::user()->participants
        ->whereIn('id', $selectedParticipantIds)
        ->map(fn ($participant) => [
            'id' => $participant->id,
            'name' => $participant->name,
            'type' => $participant->type ?? 'guest',
            'salary' => $participant->salary,
            'hourlyRate' => $participant->hourly_rate,
        ])->values();
@endphp

<div
    x-data="meetingTimer(@js($timerParticipants))"
    class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-5"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Uai.exe</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-gray-900" x-text="formatCurrency(liveCost)">R$ 0,00</p>
            <p class="text-sm text-gray-500">Custo acumulado da reunião</p>
        </div>
        <div class="text-left sm:text-right">
            <p class="text-2xl font-semibold tabular-nums text-gray-800" x-text="formatTime()">00:00:00</p>
            <button type="button" @click="isRunning ? pause() : start()" class="mt-2 rounded-md bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                <span x-text="isRunning ? 'Pausar' : 'Iniciar'">Iniciar</span>
            </button>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="duration_minutes" class="block text-sm font-medium text-gray-700">Duração prevista (minutos)</label>
            <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" required x-model.number="durationMinutes" value="{{ old('duration_minutes', $meeting->duration_minutes ?? 60) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('duration_minutes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>
        <div class="rounded-md bg-white px-4 py-3">
            <p class="text-sm text-gray-500">Estimativa antes de iniciar</p>
            <p class="mt-1 text-xl font-semibold text-gray-900" x-text="formatCurrency(projectedCost)">R$ 0,00</p>
        </div>
    </div>

    <div class="mt-5">
        <p class="text-sm font-medium text-gray-700">Participantes ativos</p>
        <div class="mt-2 space-y-2">
            <template x-for="participant in participants" :key="participant.id">
                <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 text-sm" :class="participant.isActive ? '' : 'opacity-50'">
                    <span>
                        <span class="mr-2 inline-block h-2 w-2 rounded-full" :class="participant.isActive ? 'bg-green-500' : 'bg-gray-400'"></span>
                        <span x-text="participant.name"></span>
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="tabular-nums text-gray-600" x-text="formatCurrency(participant.accumulatedCost)"></span>
                        <button type="button" x-show="participant.isActive" @click="remove(participant)" class="text-xs font-semibold text-red-600 hover:text-red-800">Remover</button>
                        <span x-show="!participant.isActive" class="text-xs text-gray-500">Saiu</span>
                    </span>
                </div>
            </template>
            <p x-show="participants.length === 0" class="text-sm text-gray-500">Selecione participantes acima para calcular o custo.</p>
        </div>
    </div>
</div>
