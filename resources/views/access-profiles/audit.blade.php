<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Histórico de acessos</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4"><a href="{{ route('access-profiles.index') }}" class="text-indigo-700">Voltar aos perfis</a></div>
            <div class="overflow-x-auto rounded bg-white shadow">
                <table class="w-full">
                    <thead><tr class="border-b text-left"><th class="p-4">Data</th><th class="p-4">Responsável</th><th class="p-4">Ação</th><th class="p-4">Alvo</th></tr></thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr class="border-b">
                                <td class="p-4 whitespace-nowrap">{{ $audit->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="p-4">{{ $audit->actor?->name ?? data_get($audit->details, 'actor.name', 'Conta removida') }}</td>
                                <td class="p-4">
                                    @switch($audit->event)
                                        @case('profile_assigned') Perfil atribuído @break
                                        @case('profile_removed') Perfil removido @break
                                        @case('permission_granted') Permissão concedida @break
                                        @case('permission_revoked') Permissão retirada @break
                                        @case('profile_created') Perfil criado @break
                                        @case('profile_updated') Perfil atualizado @break
                                        @case('profile_deactivated') Perfil inativado @break
                                        @case('permission_created') Permissão cadastrada @break
                                        @default {{ $audit->event }}
                                    @endswitch
                                </td>
                                <td class="p-4">
                                    @if ($audit->subject)
                                        {{ $audit->subject->name }} — {{ $audit->accessProfile?->name ?? data_get($audit->details, 'profile_name', 'Perfil removido') }}
                                    @elseif ($audit->permission)
                                        {{ $audit->accessProfile?->name }} — {{ $audit->permission->name }}
                                    @else
                                        {{ $audit->accessProfile?->name ?? data_get($audit->details, 'profile_name', '—') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4">Nenhuma alteração de acesso registrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $audits->links() }}</div>
        </div>
    </div>
</x-app-layout>
