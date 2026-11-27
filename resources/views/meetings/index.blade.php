<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6 flex flex-col gap-3 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('meetings.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por título, local ou observações" class="h-11 w-full min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    <select name="status" class="h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 md:flex-1">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="draft" {{ $statusFilter === 'draft' ? 'selected' : '' }}>Rascunhos</option>
                        <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>Agendadas</option>
                        <option value="ongoing" {{ $statusFilter === 'ongoing' ? 'selected' : '' }}>Em andamento</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Concluídas</option>
                        <option value="canceled" {{ $statusFilter === 'canceled' ? 'selected' : '' }}>Canceladas</option>
                    </select>
                    <button type="submit" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">Buscar</button>
                </form>
                <a href="{{ route('meetings.create') }}" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-teal-700 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-teal-800">Nova reunião</a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Local</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tempo previsto / real</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($meetings as $meeting)
                                <tr
                                    class="cursor-pointer transition-colors hover:bg-gray-200 focus-visible:bg-gray-200"
                                    role="link"
                                    tabindex="0"
                                    onclick="if (!event.target.closest('a, button, form')) window.location.href='{{ route('meetings.show', $meeting) }}'"
                                    onkeydown="if (event.key === 'Enter') window.location.href='{{ route('meetings.show', $meeting) }}'"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $meeting->title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->date?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->time ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->location ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <div>Previsto: {{ number_format($meeting->duration_minutes / 60, 2, ',', '.') }} h</div>
                                        <div>Real: {{ $meeting->actual_duration_seconds === null ? 'Não registrado' : number_format($meeting->actual_duration_seconds / 3600, 2, ',', '.') . ' h' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($meeting->status === 'draft') bg-gray-100 text-gray-800
                                            @elseif($meeting->status === 'scheduled') bg-blue-100 text-blue-800
                                            @elseif($meeting->status === 'ongoing') bg-yellow-100 text-yellow-800
                                            @elseif($meeting->status === 'completed') bg-green-100 text-green-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'][$meeting->status] ?? $meeting->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('meetings.show', $meeting) }}" class="text-teal-700 hover:text-teal-900 mr-3">Detalhes</a>
                                        <a href="{{ route('meetings.edit', $meeting) }}" class="text-teal-600 hover:text-teal-900 mr-3">Editar</a>
                                        @can('meetings.edit')
                                        @if (! in_array($meeting->status, ['completed', 'canceled'], true))
                                        <form action="{{ route('meetings.cancel', $meeting) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="bg-transparent px-0 py-0 text-red-600 hover:text-red-800" onclick="return confirm('Deseja cancelar esta reunião?')">Cancelar</button>
                                        </form>
                                        @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Nenhuma reunião cadastrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $meetings->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
