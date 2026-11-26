@if (request()->routeIs('participants.*', 'users.*', 'teams.*', 'job-positions.*', 'access-profiles.*', 'meetings.*', 'projects.*', 'reports.meetings.*'))
    <nav class="flex gap-2 border-b border-gray-200" aria-label="Menu atual">
        @if (request()->routeIs('participants.*', 'teams.*'))
            <a href="{{ route('participants.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => request()->routeIs('participants.*'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! request()->routeIs('participants.*')]) @if (request()->routeIs('participants.*')) aria-current="page" @endif>Participantes</a>
            <a href="{{ route('teams.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => request()->routeIs('teams.*'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! request()->routeIs('teams.*')]) @if (request()->routeIs('teams.*')) aria-current="page" @endif>Equipes</a>
        @elseif (request()->routeIs('meetings.*'))
            <a href="{{ route('meetings.index') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Reuniões</a>
        @elseif (request()->routeIs('projects.*'))
            <a href="{{ route('projects.index') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Projetos</a>
        @elseif (request()->routeIs('reports.meetings.*'))
            <a href="{{ route('reports.meetings.dashboard') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Indicadores</a>
        @elseif (request()->routeIs('users.*'))
            <a href="{{ route('users.index') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Acesso</a>
        @elseif (request()->routeIs('teams.*'))
            <a href="{{ route('teams.index') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Equipes</a>
        @elseif (request()->routeIs('job-positions.*'))
            <a href="{{ route('job-positions.index') }}" class="border-b-2 border-teal-600 px-4 py-3 text-sm font-semibold text-teal-800" aria-current="page">Cargos</a>
        @elseif (request()->routeIs('access-profiles.*'))
            <a href="{{ route('access-profiles.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => ! request()->routeIs('access-profiles.audit'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => request()->routeIs('access-profiles.audit')]) @if (! request()->routeIs('access-profiles.audit')) aria-current="page" @endif>Perfis de acesso</a>
            @can('access_profiles.administer')
                <a href="{{ route('access-profiles.audit') }}" @class(['border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-800' => request()->routeIs('access-profiles.audit'), 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! request()->routeIs('access-profiles.audit')]) @if (request()->routeIs('access-profiles.audit')) aria-current="page" @endif>Histórico de acessos</a>
            @endcan
        @endif
    </nav>
@endif
