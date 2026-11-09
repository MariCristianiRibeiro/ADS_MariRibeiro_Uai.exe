@php
    $selectedParticipantIds = collect($selectedParticipantIds ?? [])->map(fn ($id) => (int) $id)->all();
    $investmentParticipants = $members->map(fn ($member): array => [
        'id' => $member->id,
        'name' => $member->name,
        'hourlyRate' => (float) ($hourlyRates[$member->id] ?? 0),
        'isSelected' => in_array($member->id, $selectedParticipantIds, true),
    ])->values();
@endphp

<div
    x-data="meetingInvestment(@js($investmentParticipants), @js(old('duration_minutes', ($meeting ?? null)?->duration_minutes ?? 60)))"
    x-on:meeting-participants-changed.window="selectParticipants($event.detail.ids)"
    class="mt-6 rounded-lg border border-indigo-100 bg-indigo-50/50 p-5"
>
    <div class="flex items-center gap-2">
        <h3 class="font-semibold text-gray-900">Investimento previsto</h3>
        <x-info-tip text="Estimativa planejada antes da reunião: soma do valor-hora de cada participante multiplicada pela duração prevista. O cronômetro e o custo real são registrados ao iniciar a reunião." />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 {{ auth()->user()->canViewCosts() ? 'sm:grid-cols-2' : '' }}">
        <div>
            <label for="duration_minutes" class="block text-sm font-medium text-gray-700">Duração prevista (minutos) <x-info-tip text="Tempo planejado para o encontro. As horas colaborador são calculadas multiplicando esta duração pelo número de participantes." /></label>
            <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" required x-model.number="durationMinutes" value="{{ old('duration_minutes', ($meeting ?? null)?->duration_minutes ?? 60) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('duration_minutes') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        @can('view-costs')
            <div class="rounded-md bg-white px-4 py-3">
                <p class="text-sm text-gray-500">Custo previsto (valor-hora × duração)</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="formatCurrency(projectedCost)">R$ 0,00</p>
                <p class="mt-1 text-xs text-gray-500"><span x-text="collaboratorHours.toLocaleString('pt-BR', { maximumFractionDigits: 2 })"></span> horas colaborador</p>
            </div>
        @endcan
    </div>

    @can('view-costs')
        <div class="mt-4">
            <p class="text-sm font-medium text-gray-700">Custo estimado por participante</p>
            <div class="mt-2 space-y-2">
                <template x-for="participant in participants" :key="participant.id">
                    <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 text-sm">
                        <span x-text="participant.name"></span>
                        <span class="tabular-nums text-gray-600" x-text="formatCurrency(participant.hourlyRate) + '/h · ' + formatCurrency(participant.hourlyRate * durationMinutes / 60)"></span>
                    </div>
                </template>
                <p x-show="participants.length === 0" class="text-sm text-gray-500">Selecione participantes para estimar o investimento.</p>
            </div>
        </div>
    @endcan
</div>
