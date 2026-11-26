<x-app-layout>
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6 flex flex-col gap-3 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('projects.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 md:flex-row">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nome ou código do projeto" class="h-11 w-full min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    <button type="submit" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">Buscar</button>
                </form>
                <a href="{{ route('projects.create') }}" class="inline-flex h-11 min-h-[2.65rem] shrink-0 items-center justify-center whitespace-nowrap rounded-md bg-teal-700 px-4 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-teal-800">Novo projeto</a>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                @if ($projects->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Projeto</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reuniões vinculadas</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($projects as $project)
                                    <tr class="clickable-table-row" role="link" tabindex="0" onclick="if (!event.target.closest('a, button, form')) window.location.href='{{ route('projects.show', $project) }}'" onkeydown="if (event.key === 'Enter') window.location.href='{{ route('projects.show', $project) }}'">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $project->name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $project->meetings_count }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm"><a href="{{ route('projects.show', $project) }}" class="text-teal-700 hover:text-teal-900">Detalhes</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <p class="font-medium text-gray-900">{{ $search !== '' ? 'Nenhum projeto encontrado.' : 'Nenhum projeto cadastrado.' }}</p>
                        @if ($search === '')
                            <p class="mt-1 text-sm text-gray-500">Cadastre um projeto para agrupar reuniões e consultar o tempo investido por projeto.</p>
                            <a href="{{ route('projects.create') }}" class="mt-4 inline-flex items-center justify-center rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-teal-800">
                                Cadastrar primeiro projeto
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
