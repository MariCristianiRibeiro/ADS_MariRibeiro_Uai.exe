<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <span>Participantes</span>
            <a href="{{ route('users.create') }}" class="ml-4 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Novo participante</a>
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-6">
                <form method="GET" action="{{ route('users.index') }}" class="flex flex-col md:flex-row gap-3">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nome ou e-mail" class="w-full md:flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="status" class="w-full md:w-56 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos os status</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Ativos</option>
                        <option value="pending_finance" {{ $statusFilter === 'pending_finance' ? 'selected' : '' }}>Pendentes financeiros</option>
                        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pendentes</option>
                        <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    </select>
                    <select name="role" class="w-full md:w-56 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all" {{ $roleFilter === 'all' ? 'selected' : '' }}>Todos os perfis</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado em</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ['admin' => 'Administrador geral', 'hr' => 'RH', 'executive' => 'Executiva', 'director' => 'Diretor', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$user->role] ?? 'Colaborador' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if(($user->status ?? 'active') === 'active') bg-green-100 text-green-800
                                            @elseif(($user->status ?? 'pending') === 'pending' || ($user->status ?? 'pending') === 'pending_finance') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ $user->status ?? 'active' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum usuário cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
