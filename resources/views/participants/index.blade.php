<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6 flex flex-col gap-3 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('participants.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nome, e-mail ou telefone" class="h-11 w-full min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    <select name="status" class="h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 md:flex-1">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Ativos</option>
                        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pendentes</option>
                        <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inativos</option>
                    </select>
                    <button type="submit" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">
                        Buscar
                    </button>
                </form>
                @can('participants.create')
                    <a href="{{ route('participants.create') }}" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-teal-700 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-teal-800">Novo participante</a>
                @endcan
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">E-mail</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Equipe / rede</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Superior responsável</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($participants as $participant)
                                <tr class="clickable-table-row" role="link" tabindex="0" onclick="if (!event.target.closest('a, button, form')) window.location.href='{{ route('participants.edit', $participant) }}'" onkeydown="if (event.key === 'Enter') window.location.href='{{ route('participants.edit', $participant) }}'">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $participant->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $participant->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $participant->phone ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if ($participant->memberUser?->team)
                                            {{ $participant->memberUser->team->name }} — {{ $participant->memberUser->team->directorate?->name }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $participant->user?->supervisors->pluck('name')->implode(', ') ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($participant->status === 'active') bg-green-100 text-green-800
                                            @elseif($participant->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ['active' => 'Ativo', 'pending' => 'Pendente', 'inactive' => 'Inativo'][$participant->status] ?? $participant->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @can('participants.edit')
                                            <a href="{{ route('participants.edit', $participant) }}" class="text-teal-600 hover:text-teal-900 mr-3">Editar</a>
                                        @endcan
                                        @can('participants.delete')
                                            <form action="{{ route('participants.destroy', $participant) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="bg-transparent px-0 py-0 text-red-600 hover:text-red-900" onclick="return confirm('Deseja excluir este participante?')">Excluir</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum participante cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $participants->links() }}</div>
        </div>
    </div>
</x-app-layout>
