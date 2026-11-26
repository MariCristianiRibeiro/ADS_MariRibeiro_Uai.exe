<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-100 px-4 py-3 shadow-sm">
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-teal-800">Nova conta</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <nav class="mb-6 flex gap-2 border-b border-gray-200" aria-label="Gestão de participantes">
                @can('manage-users')
                @endcan
                @can('participants.view')
                    <a href="{{ route('participants.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => request()->routeIs('participants.*'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! request()->routeIs('participants.*')]) @if(request()->routeIs('participants.*')) aria-current="page" @endif>Participantes</a>
                @endcan
                @can('manage-structure')
                    <a href="{{ route('teams.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => request()->routeIs('teams.*'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! request()->routeIs('teams.*')]) @if(request()->routeIs('teams.*')) aria-current="page" @endif>Equipes</a>
                @endcan
            </nav>
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->has('user'))
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                    {{ $errors->first('user') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-6">
                <form method="GET" action="{{ route('users.index') }}" class="flex flex-col md:flex-row gap-3">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nome ou e-mail" class="w-full md:flex-1 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    <select name="status" class="w-full md:w-56 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos os status</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Ativos</option>
                        <option value="pending_finance" {{ $statusFilter === 'pending_finance' ? 'selected' : '' }}>Pendentes financeiros</option>
                        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pendentes</option>
                        <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    </select>
                    <select name="role" class="w-full md:w-56 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="all" {{ $roleFilter === 'all' ? 'selected' : '' }}>Todos os perfis organizacionais</option>
                        <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Administrador geral</option>
                        <option value="hr" {{ $roleFilter === 'hr' ? 'selected' : '' }}>RH</option>
                        <option value="executive" {{ $roleFilter === 'executive' ? 'selected' : '' }}>Executivos</option>
                        <option value="director_commercial" {{ $roleFilter === 'director_commercial' ? 'selected' : '' }}>Diretores Comerciais</option>
                        <option value="director_network" {{ $roleFilter === 'director_network' ? 'selected' : '' }}>Diretores de Rede</option>
                        <option value="manager" {{ $roleFilter === 'manager' ? 'selected' : '' }}>Gestores</option>
                        <option value="participant" {{ $roleFilter === 'participant' ? 'selected' : '' }}>Colaboradores</option>
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Filtrar</button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">E-mail</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Papel organizacional</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Superior responsável 1</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Superior responsável 2</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado em</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($users as $user)
                                <tr class="clickable-table-row" role="link" tabindex="0" onclick="if (!event.target.closest('a, button, form')) window.location.href='{{ route('users.edit', $user) }}'" onkeydown="if (event.key === 'Enter') window.location.href='{{ route('users.edit', $user) }}'">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ['admin' => 'Administrador geral', 'hr' => 'RH', 'executive' => 'Executiva', 'director' => 'Diretor', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$user->role] ?? 'Colaborador' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->supervisors->get(0)?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->supervisors->get(1)?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if(($user->status ?? 'active') === 'active') bg-green-100 text-green-800
                                            @elseif(($user->status ?? 'pending') === 'pending' || ($user->status ?? 'pending') === 'pending_finance') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ $user->status ?? 'active' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('users.edit', $user) }}" class="text-teal-600 hover:text-teal-900">Editar</a>
                                        @if (! $user->is(auth()->user()))
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="ml-3 inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="bg-transparent px-0 py-0 text-red-600 hover:text-red-900" onclick="return confirm('Excluir esta conta? Contas vinculadas a reuniões ou históricos não podem ser removidas.')">Excluir</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum usuário cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
