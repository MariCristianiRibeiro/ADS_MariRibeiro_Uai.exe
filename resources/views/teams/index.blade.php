<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-100 px-4 py-3 shadow-sm">
            <div class="flex flex-wrap gap-3">
            @can('teams.create')
                <a href="{{ route('teams.create') }}" class="inline-flex items-center justify-center rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-teal-800">
                    Nova equipe
                </a>
            @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
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
                <div class="mb-4 rounded-md border border-green-200 bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6 overflow-hidden rounded-lg bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('teams.index') }}" class="flex flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por equipe ou rede" class="w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 md:flex-1">
                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">Buscar</button>
                </form>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                @if ($teams->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Equipe</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rede</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Participantes</th>
                                    @can('teams.manage')
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ações</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($teams as $team)
                                    <tr>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $team->name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $team->directorate->name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $team->members_count }}</td>
                                        @can('teams.manage')
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                                                <a href="{{ route('teams.edit', $team) }}" class="mr-3 text-teal-700 hover:text-teal-900">Editar</a>
                                                <form action="{{ route('teams.destroy', $team) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="bg-transparent px-0 py-0 text-red-600 hover:text-red-900" onclick="return confirm('Excluir esta equipe? Os participantes e projetos permanecerão cadastrados, sem equipe vinculada.')">Excluir</button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <p class="font-medium text-gray-900">Nenhuma equipe cadastrada.</p>
                        <p class="mt-1 text-sm text-gray-500">Cadastre uma equipe e informe a rede à qual ela pertence.</p>
                        @can('teams.create')
                            <a href="{{ route('teams.create') }}" class="mt-4 inline-flex items-center justify-center rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-teal-800">
                                Cadastrar primeira equipe
                            </a>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
