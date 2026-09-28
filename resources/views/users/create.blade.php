<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Novo usuário</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg" x-data="userHierarchyForm()">
                <form method="POST" action="{{ route('users.store') }}" class="grid grid-cols-1 gap-6">
                    @csrf
                    <div><label for="name" class="block text-sm font-medium text-gray-700">Nome</label><input id="name" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                    <div><label for="email" class="block text-sm font-medium text-gray-700">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                    <div><label for="password" class="block text-sm font-medium text-gray-700">Senha</label><input id="password" name="password" type="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@error('password')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div><label for="role" class="block text-sm font-medium text-gray-700">Perfil</label><select id="role" name="role" x-model="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="participant">Colaborador</option><option value="manager">Gestor</option><option value="director_network">Diretor de Rede</option><option value="director_commercial">Diretor Comercial</option><option value="executive">Executiva</option><option value="hr">RH</option><option value="admin">Administrador geral</option></select>@error('role')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label for="status" class="block text-sm font-medium text-gray-700">Status</label><select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="active">Ativo</option><option value="pending_finance">Pendente financeiro</option><option value="pending">Pendente</option><option value="inactive">Inativo</option></select></div>
                    </div>

                    <div><label class="block text-sm font-medium text-gray-700">Superiores responsáveis</label><div class="mt-1 grid grid-cols-1 gap-3 md:grid-cols-2"><select name="supervisor_ids[]" class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Superior 1</option>@foreach ($availableSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }} - {{ ['executive' => 'Executiva', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'director' => 'Diretor', 'manager' => 'Gestor'][$supervisor->role] ?? $supervisor->role }}</option>@endforeach</select><select name="supervisor_ids[]" class="block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Superior 2 (opcional)</option>@foreach ($availableSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }} - {{ ['executive' => 'Executiva', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'director' => 'Diretor', 'manager' => 'Gestor'][$supervisor->role] ?? $supervisor->role }}</option>@endforeach</select></div><p class="mt-1 text-xs text-gray-500" x-text="helpText"></p>@error('supervisor_ids')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3"><div><label for="directorate_id" class="block text-sm font-medium text-gray-700">Diretoria</label><select id="directorate_id" name="directorate_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuída</option>@foreach ($directorates as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div><div><label for="team_id" class="block text-sm font-medium text-gray-700">Equipe</label><select id="team_id" name="team_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuída</option>@foreach ($teams as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div><div><label for="job_position_id" class="block text-sm font-medium text-gray-700">Cargo</label><select id="job_position_id" name="job_position_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($jobPositions as $item)<option value="{{ $item->id }}">{{ $item->name }} - R$ {{ number_format($item->hourly_rate, 2, ',', '.') }}/h</option>@endforeach</select></div></div>

                    <div class="flex justify-end gap-3"><a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm">Cancelar</a><button class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Cadastrar usuário</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function userHierarchyForm() {
            return {
                role: 'participant',
                filterSupervisors() {
                    const allowed = { director: ['executive', 'director'], director_commercial: ['executive', 'director'], director_network: ['director_commercial'], manager: ['director_network', 'director'], participant: ['manager', 'director_network', 'director'] }[this.role] || [];
                    Array.from(this.$refs.supervisors.options).forEach(option => {
                        if (!option.dataset.role) return;
                        option.hidden = !allowed.includes(option.dataset.role);
                        option.disabled = option.hidden;
                        if (option.disabled) option.selected = false;
                    });
                },
                get helpText() {
                    return this.role === 'director_commercial' ? 'Diretor Comercial responde à Executiva.' : this.role === 'director_network' ? 'Diretor de Rede responde ao Diretor Comercial.' : this.role === 'manager' ? 'Gestor responde ao Diretor de Rede.' : this.role === 'participant' ? 'Colaborador responde ao Gestor ou Diretor de Rede.' : 'Este perfil não exige superior.';
                }
            };
        }
    </script>
</x-app-layout>
