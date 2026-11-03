@php
    $participantOptions = $members->map(fn ($member) => [
        'id' => $member->id,
        'name' => $member->name,
        'role' => ['executive' => 'Executiva', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$member->role] ?? $member->role,
        'attendanceStatus' => old('attendance.' . $member->id, ($meeting ?? null)?->users?->firstWhere('id', $member->id)?->pivot?->attendance_status ?? 'pending'),
    ])->values();
@endphp

<div
    x-data="{
        options: @js($participantOptions),
        selectedIds: @js(array_values(array_map('intval', $selectedMemberIds))),
        search: '',
        get selected() { return this.options.filter((option) => this.selectedIds.includes(option.id)); },
        get results() {
            const term = this.search.trim().toLocaleLowerCase();
            return this.options.filter((option) => !this.selectedIds.includes(option.id) && (!term || option.name.toLocaleLowerCase().includes(term) || option.role.toLocaleLowerCase().includes(term))).slice(0, 8);
        },
        add(id) { this.selectedIds.push(id); this.search = ''; this.notify(); },
        remove(id) { this.selectedIds = this.selectedIds.filter((selectedId) => selectedId !== id); this.notify(); },
        notify() { this.$dispatch('meeting-participants-changed', { ids: this.selectedIds }); },
    }"
    x-init="notify()"
>
    <label for="participant_search" class="block text-sm font-medium text-gray-700">Participantes</label>
    <input id="participant_search" type="search" x-model="search" autocomplete="off" placeholder="Pesquisar por nome ou função" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

    <div x-show="search.trim().length > 0" x-cloak class="mt-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-sm">
        <template x-for="option in results" :key="option.id">
            <button type="button" @click="add(option.id)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50">
                <span x-text="option.name"></span><span class="text-xs text-gray-500" x-text="option.role"></span>
            </button>
        </template>
        <p x-show="results.length === 0" class="px-3 py-2 text-sm text-gray-500">Nenhum participante encontrado.</p>
    </div>

    <p class="mt-2 text-xs text-gray-500">Pesquise e selecione quem foi convidado. Remova da lista quem não participará. Administrador geral não aparece nesta lista.</p>
    @error('participants') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    @error('participants.*') <span class="text-sm text-red-600">Selecione participantes válidos.</span> @enderror

    <div class="mt-3 space-y-2">
        <template x-for="option in selected" :key="option.id">
            <div class="rounded-md border border-gray-200 bg-white p-3">
                <input type="hidden" name="participants[]" :value="option.id">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-sm font-medium text-gray-800" x-text="option.name"></p><p class="text-xs text-gray-500" x-text="option.role"></p></div>
                    <button type="button" @click="remove(option.id)" class="text-sm font-medium text-red-600 hover:text-red-800">Remover</button>
                </div>
                <label class="mt-3 block text-xs font-medium text-gray-600" :for="'attendance_' + option.id">Presença</label>
                <select :id="'attendance_' + option.id" :name="'attendance[' + option.id + ']'" x-model="option.attendanceStatus" @change="$dispatch('meeting-attendance-changed', { id: option.id, status: option.attendanceStatus })" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="pending">Aguardando confirmação</option>
                    <option value="confirmed">Presença confirmada</option>
                    <option value="absent">Não comparecerá</option>
                </select>
            </div>
        </template>
        <p x-show="selectedIds.length === 0" class="text-sm text-gray-500">Nenhum participante selecionado.</p>
    </div>
</div>
