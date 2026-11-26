<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $project->name }}</h2>
            <a href="{{ route('projects.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">Voltar aos projetos</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Detalhes do projeto</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase text-gray-500">Código</dt><dd class="mt-1 text-sm text-gray-900">{{ $project->code }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-gray-500">Equipe</dt><dd class="mt-1 text-sm text-gray-900">{{ $project->team?->name ?? 'Não vinculada' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-gray-500">Responsável</dt><dd class="mt-1 text-sm text-gray-900">{{ $project->manager?->name ?? 'Não definido' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="font-semibold text-gray-900">Reuniões vinculadas ({{ $project->meetings->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Reunião</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Data</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($project->meetings as $meeting)
                                <tr class="clickable-table-row" role="link" tabindex="0" onclick="window.location.href='{{ route('meetings.show', $meeting) }}'" onkeydown="if (event.key === 'Enter') window.location.href='{{ route('meetings.show', $meeting) }}'">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $meeting->title }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $meeting->date?->format('d/m/Y') ?? '—' }} {{ $meeting->time ?? '' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ ['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'][$meeting->status] ?? $meeting->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Este projeto ainda não tem reuniões vinculadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
