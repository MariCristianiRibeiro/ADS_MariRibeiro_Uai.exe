<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-100 px-4 py-3 shadow-sm">
            <h2 class="font-bold text-xl text-black leading-tight">
                Reuniões
            </h2>
            <a href="{{ route('meetings.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Nova reunião
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 border border-green-200 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-6">
                <form method="GET" action="{{ route('meetings.index') }}" class="flex flex-col md:flex-row gap-3">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por título, local ou observações" class="w-full md:w-2/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="status" class="w-full md:w-1/4 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>Agendadas</option>
                        <option value="ongoing" {{ $statusFilter === 'ongoing' ? 'selected' : '' }}>Em andamento</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Concluídas</option>
                        <option value="canceled" {{ $statusFilter === 'canceled' ? 'selected' : '' }}>Canceladas</option>
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Filtrar</button>
                </form>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participantes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($meetings as $meeting)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $meeting->title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->date?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->time ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $meeting->location ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($meeting->participants->isNotEmpty())
                                            {{ $meeting->participants->pluck('name')->implode(', ') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($meeting->status === 'scheduled') bg-blue-100 text-blue-800
                                            @elseif($meeting->status === 'ongoing') bg-yellow-100 text-yellow-800
                                            @elseif($meeting->status === 'completed') bg-green-100 text-green-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ $meeting->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('meetings.edit', $meeting) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</a>
                                        <form action="{{ route('meetings.destroy', $meeting) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Deseja excluir esta reunião?')">Excluir</button>
                                        </form>
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
            </div>
        </div>
    </div>
</x-app-layout>
