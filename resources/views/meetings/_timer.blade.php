@php
    $selectedParticipantIds = collect($selectedParticipantIds ?? [])->map(fn ($id) => (int) $id)->all();
    $timerParticipants = $members
        ->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'hourlyRate' => (float) ($member->jobPosition?->hourly_rate ?? $member->hourly_rate ?? 0),
            'isSelected' => in_array($member->id, $selectedParticipantIds, true),
            'attendanceStatus' => old('attendance.' . $member->id, ($meeting ?? null)?->users?->firstWhere('id', $member->id)?->pivot?->attendance_status ?? 'pending'),
        ])->values();
@endphp

<div
    x-data="meetingTimer(@js($timerParticipants), @js(($meeting ?? null)?->actual_duration_seconds), @js(old('duration_minutes', ($meeting ?? null)?->duration_minutes ?? 60)), @js(($meeting ?? null) === null))"
    x-on:meeting-participants-changed.window="selectParticipants($event.detail.ids)"
    x-on:meeting-attendance-changed.window="setAttendanceStatus($event.detail.id, $event.detail.status)"
    class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-5"
>
    <input type="hidden" name="actual_duration_seconds" x-bind:value="hasStarted ? elapsedSeconds : ''">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Custo pelo valor-hora</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-gray-900" x-text="formatCurrency(liveCost)">R$ 0,00</p>
            <p class="text-sm text-gray-500">{{ ($meeting ?? null) === null ? 'Custo estimado pelo tempo corrido' : 'Custo real acumulado das presenças confirmadas' }}</p>
        </div>
        <div class="text-left sm:text-right">
            <p class="text-sm text-gray-500">Tempo realizado</p>
            <p class="text-2xl font-semibold tabular-nums text-gray-800" x-text="formatTime()">00:00:00</p>
            <button type="button" x-show="!isRunning" @click="start()" class="mt-2 rounded-md bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                Iniciar cronômetro
            </button>
            <p x-show="isRunning" class="mt-2 text-sm font-medium text-green-700">Cronômetro em andamento, sem pausas.</p>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="duration_minutes" class="block text-sm font-medium text-gray-700">Duração prevista (minutos)</label>
            <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" required x-model.number="durationMinutes" value="{{ old('duration_minutes', ($meeting ?? null)?->duration_minutes ?? 60) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('duration_minutes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>
        <div class="rounded-md bg-white px-4 py-3">
            <p class="text-sm text-gray-500">Custo previsto (valor-hora × tempo previsto)</p>
            <p class="mt-1 text-xl font-semibold text-gray-900" x-text="formatCurrency(projectedCost)">R$ 0,00</p>
        </div>
    </div>

    <div class="mt-5">
        <p class="text-sm font-medium text-gray-700">Valor-hora dos participantes</p>
        <div class="mt-2 space-y-2">
            <template x-for="participant in participants" :key="participant.id">
                <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 text-sm">
                    <span>
                        <span x-text="participant.name"></span>
                        <span class="text-xs text-gray-500" x-text="participant.attendanceStatus === 'confirmed' ? '· presença confirmada' : participant.attendanceStatus === 'absent' ? '· ausência registrada' : '· aguardando confirmação'"></span>
                    </span>
                    <span class="tabular-nums text-gray-600" x-text="formatCurrency(hourlyRate(participant)) + '/h'"></span>
                </div>
            </template>
            <p x-show="participants.length === 0" class="text-sm text-gray-500">Selecione os participantes para estimar o custo por hora.</p>
        </div>
    </div>
</div>
