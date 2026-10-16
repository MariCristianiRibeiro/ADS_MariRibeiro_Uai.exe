<fieldset class="rounded-md border border-gray-200 p-4">
    <legend class="px-2 text-sm font-medium text-gray-700">Chamada de presença</legend>
    <p class="mb-3 text-xs text-gray-500">Registre se cada participante confirmou presença ou informou ausência.</p>
    @forelse ($members->whereIn('id', $selectedMemberIds) as $member)
        @php
            $savedStatus = ($meeting ?? null)?->users?->firstWhere('id', $member->id)?->pivot?->attendance_status ?? 'pending';
            $attendanceStatus = old('attendance.' . $member->id, $savedStatus);
        @endphp
        <div class="mb-3 grid grid-cols-1 items-center gap-2 sm:grid-cols-2">
            <label for="attendance_{{ $member->id }}" class="text-sm text-gray-700">{{ $member->name }}</label>
            <select id="attendance_{{ $member->id }}" name="attendance[{{ $member->id }}]" x-on:change="$dispatch('meeting-attendance-changed', { id: {{ $member->id }}, status: $event.target.value })" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="pending" {{ $attendanceStatus === 'pending' ? 'selected' : '' }}>Aguardando confirmação</option>
                <option value="confirmed" {{ $attendanceStatus === 'confirmed' ? 'selected' : '' }}>Presença confirmada</option>
                <option value="absent" {{ $attendanceStatus === 'absent' ? 'selected' : '' }}>Não comparecerá</option>
            </select>
        </div>
    @empty
        <p class="text-sm text-gray-500">Selecione participantes para fazer a chamada.</p>
    @endforelse
</fieldset>
