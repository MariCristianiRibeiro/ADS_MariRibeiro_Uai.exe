<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar usuário
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Perfil</label>
                            <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="participant" {{ old('role', $user->role ?? 'participant') === 'participant' ? 'selected' : '' }}>Participante / Organizador</option>
                                <option value="admin" {{ old('role', $user->role ?? 'participant') === 'admin' ? 'selected' : '' }}>Administrador</option>
                                <option value="hr" {{ old('role', $user->role) === 'hr' ? 'selected' : '' }}>RH</option>
                                <option value="executive" {{ old('role', $user->role) === 'executive' ? 'selected' : '' }}>Executivo</option>
                                <option value="director_commercial" {{ old('role', $user->role) === 'director_commercial' ? 'selected' : '' }}>Diretor Comercial</option>
                                <option value="director_network" {{ old('role', $user->role) === 'director_network' ? 'selected' : '' }}>Diretor de Rede</option>
                                <option value="manager" {{ old('role', $user->role) === 'manager' ? 'selected' : '' }}>Gestor</option>
                            </select>
                            @error('role') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="supervisor_ids" class="block text-sm font-medium text-gray-700">Superiores responsáveis</label>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach ([0 => 'Superior 1', 1 => 'Superior 2 (opcional)'] as $index => $label)
                                    <select name="supervisor_ids[]" class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ $label }}</option>
                                        @foreach ($availableSupervisors as $supervisor)
                                            <option value="{{ $supervisor->id }}" {{ $supervisors->get($index)?->id === $supervisor->id ? 'selected' : '' }}>{{ $supervisor->name }} - {{ ['admin' => 'Administrador geral', 'hr' => 'RH', 'executive' => 'Executiva', 'director' => 'Diretor', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$supervisor->role] ?? $supervisor->role }}</option>
                                        @endforeach
                                    </select>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Diretor: Executivo e/ou Diretor superior, opcional. Gestor: Diretor obrigatório. Colaborador: até dois Diretores. É possível manter até dois vínculos.</p>
                            @error('supervisor_ids') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6"><div><label for="directorate_id" class="block text-sm font-medium text-gray-700">Diretoria</label><select id="directorate_id" name="directorate_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuída</option>@foreach ($directorates as $directorate)<option value="{{ $directorate->id }}" {{ old('directorate_id', $user->directorate_id) == $directorate->id ? 'selected' : '' }}>{{ $directorate->name }}</option>@endforeach</select></div><div><label for="team_id" class="block text-sm font-medium text-gray-700">Equipe</label><select id="team_id" name="team_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuída</option>@foreach ($teams as $team)<option value="{{ $team->id }}" {{ old('team_id', $user->team_id) == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>@endforeach</select></div><div><label for="job_position_id" class="block text-sm font-medium text-gray-700">Cargo</label><select id="job_position_id" name="job_position_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($jobPositions as $jobPosition)<option value="{{ $jobPosition->id }}" {{ old('job_position_id', $user->job_position_id) == $jobPosition->id ? 'selected' : '' }}>{{ $jobPosition->name }} - R$ {{ number_format($jobPosition->hourly_rate, 2, ',', '.') }}/h</option>@endforeach</select></div></div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active" {{ old('status', $user->status ?? 'active') === 'active' ? 'selected' : '' }}>Ativo</option>
                                <option value="pending_finance" {{ old('status', $user->status ?? 'pending_finance') === 'pending_finance' ? 'selected' : '' }}>Pendente de configuração financeira</option>
                                <option value="pending" {{ old('status', $user->status ?? 'pending') === 'pending' ? 'selected' : '' }}>Pendente</option>
                                <option value="inactive" {{ old('status', $user->status ?? 'inactive') === 'inactive' ? 'selected' : '' }}>Inativo</option>
                            </select>
                            @error('status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700">Custo por hora (R$)</label>
                            <input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate', $user->hourly_rate ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('hourly_rate') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">Cancelar</a>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
