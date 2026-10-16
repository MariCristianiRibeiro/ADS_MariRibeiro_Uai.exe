<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nova reunião
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('meetings.store') }}">
                    @csrf
                    @php $selectedMemberIds = collect(old('participants', []))->map(fn ($id) => (int) $id)->all(); @endphp

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Título</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('title') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="project_id" class="block text-sm font-medium text-gray-700">Projeto</label>
                            <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Sem projeto</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }} ({{ $project->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-700">Data</label>
                                <input id="date" name="date" type="date" value="{{ old('date') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="time" class="block text-sm font-medium text-gray-700">Hora</label>
                                <input id="time" name="time" type="time" value="{{ old('time') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('time') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>

                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Local</label>
                            <input id="location" name="location" type="text" value="{{ old('location') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('location') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="scheduled" {{ old('status') === 'scheduled' ? 'selected' : '' }}>Agendada</option>
                                <option value="ongoing" {{ old('status') === 'ongoing' ? 'selected' : '' }}>Em andamento</option>
                                <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Concluída</option>
                                <option value="canceled" {{ old('status') === 'canceled' ? 'selected' : '' }}>Cancelada</option>
                            </select>
                            @error('status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="participants" class="block text-sm font-medium text-gray-700">Usuários participantes</label>
                            <select id="participants" name="participants[]" multiple x-on:change="$dispatch('meeting-participants-changed', { ids: Array.from($event.target.selectedOptions, option => Number(option.value)) })" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}" {{ in_array($member->id, $selectedMemberIds, true) ? 'selected' : '' }}>
                                        {{ $member->name }} - {{ ['executive' => 'Executiva', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$member->role] ?? $member->role }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Selecione usuários cadastrados. Administrador geral não participa de agendas.</p>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            @error('notes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        @include('meetings._timer', ['selectedParticipantIds' => $selectedMemberIds])
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('meetings.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">Cancelar</a>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
