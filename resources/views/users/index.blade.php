<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <div class="mb-6 flex flex-col gap-3 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('users.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nome ou e-mail" class="h-11 w-full min-w-0 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 md:flex-1">
                    <select name="status" class="h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 md:flex-1">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos os status</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Ativos</option>
                        <option value="pending_finance" {{ $statusFilter === 'pending_finance' ? 'selected' : '' }}>Pendentes financeiros</option>
                        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pendentes</option>
                        <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    </select>
                    <button type="submit" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">Buscar</button>
                </form>
                <a href="{{ route('users.create') }}" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-teal-700 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-teal-800">Novo acesso</a>
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
                                            {{ ['active' => 'Ativo', 'pending' => 'Pendente', 'pending_finance' => 'Pendente de configuração financeira', 'inactive' => 'Inativo'][($user->status ?? 'active')] ?? ($user->status ?? 'active') }}
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
            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
