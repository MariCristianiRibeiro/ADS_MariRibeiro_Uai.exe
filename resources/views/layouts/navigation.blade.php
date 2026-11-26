<div>
    <div
        x-cloak
        x-show="mobileOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden"
        @click="mobileOpen = false"
        aria-hidden="true"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-all duration-300 ease-in-out lg:translate-x-0"
        :class="{ 'lg:w-20': sidebarCollapsed, 'lg:w-64': !sidebarCollapsed, 'translate-x-0': mobileOpen, '-translate-x-full': !mobileOpen }"
        aria-label="Menu principal"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-100 px-3 lg:px-4" :class="sidebarCollapsed ? 'lg:justify-center' : ''">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" :class="sidebarCollapsed ? 'lg:hidden' : ''" aria-label="Uai - início">
                <span class="text-2xl font-extrabold tracking-tight text-teal-800">Uai</span>
            </a>
            <button
                type="button"
                class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:flex"
                @click="sidebarCollapsed = !sidebarCollapsed"
                :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
                :aria-expanded="!sidebarCollapsed"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2" stroke-width="1.6" />
                    <path d="M9 5v14m4.5-9 2 2-2 2" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" />
                </svg>
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                @click="mobileOpen = false"
                aria-label="Fechar menu"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-7 overflow-y-auto px-3 py-6">
            <div class="space-y-1">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">Início</p>
                <a
                    href="{{ route('dashboard') }}"
                    @class([
                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-teal-50 text-teal-800' => request()->routeIs('dashboard'),
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('dashboard'),
                    ])
                    :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                    title="Crono"
                    @click="mobileOpen = false"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M3.75 10.5 12 3.75l8.25 6.75v9a.75.75 0 0 1-.75.75h-5.25v-6h-4.5v6H4.5a.75.75 0 0 1-.75-.75v-9Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                    </svg>
                    <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Crono</span>
                </a>
                @can('meetings.view')
                    <a
                        href="{{ route('meetings.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('meetings.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('meetings.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Reuniões"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="4" y="5.5" width="16" height="15" rx="2" stroke-width="1.7" />
                            <path d="M8 3.75v3.5m8-3.5v3.5M4 10h16m-11 4h3m-3 3h6" stroke-linecap="round" stroke-width="1.7" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Reuniões') }}</span>
                    </a>
                @endcan
                @can('projects.view')
                    <a
                        href="{{ route('projects.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('projects.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('projects.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Projetos"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 7.75h6l1.5 2h8.75v8.5a1 1 0 0 1-1 1h-14a1 1 0 0 1-1-1v-9.5a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                            <path d="M4 10h16" stroke-linecap="round" stroke-width="1.7" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Projetos') }}</span>
                    </a>
                @endcan
                @can('reports.view')
                    <a
                        href="{{ route('reports.meetings.dashboard') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('reports.meetings.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('reports.meetings.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Indicadores"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 19.25V12m5 7.25V5m5 14.25v-9m5 9V8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Indicadores</span>
                    </a>
                @endcan
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">Gestão</p>

                @can('participants.view')
                    <a
                        href="{{ route('participants.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('participants.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('participants.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Participantes"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M16 19.25h4.25v-1.5a3.75 3.75 0 0 0-5.6-3.26M16 19.25H8m8 0v-1.5c0-.55-.1-1.08-.29-1.56M8 19.25H3.75v-1.5a3.75 3.75 0 0 1 5.6-3.26M8 19.25v-1.5c0-.55.1-1.08.29-1.56m0 0a4.5 4.5 0 0 1 7.42 0M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Zm4.5 1.5a3 3 0 1 1-5.25-1.98M3.75 9a3 3 0 1 0 5.25-1.98" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Participantes') }}</span>
                    </a>
                @endcan

                @can('manage-users')
                    <a
                        href="{{ route('users.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('users.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('users.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Acesso"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.25" stroke-width="1.6" />
                            <path d="M5.25 19.25a6.75 6.75 0 0 1 13.5 0h-13.5Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Acesso') }}</span>
                    </a>
                @endcan

                @can('job_positions.view')
                    <a
                        href="{{ route('job-positions.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('job-positions.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('job-positions.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Cargos"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="4" y="7" width="16" height="13" rx="2" stroke-width="1.7" />
                            <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-11 5h16m-10 0v2h4v-2" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Cargos</span>
                    </a>
                @endcan

                @can('access_profiles.view')
                    <a
                        href="{{ route('access-profiles.index') }}"
                        @class([
                            'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            'bg-teal-50 text-teal-800' => request()->routeIs('access-profiles.*'),
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('access-profiles.*'),
                        ])
                        :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                        title="Perfis de acesso"
                        @click="mobileOpen = false"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M12 3.75 19.5 7v5.1c0 4.1-3.2 7.8-7.5 8.9-4.3-1.1-7.5-4.8-7.5-8.9V7l7.5-3.25Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                            <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                        </svg>
                        <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Perfis de acesso</span>
                    </a>
                @endcan
            </div>

        </nav>

        <div class="shrink-0 border-t border-slate-100 p-3">
            <a
                href="{{ route('profile.edit') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                title="Perfil"
                @click="mobileOpen = false"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-50 text-sm font-bold text-teal-800">
                    {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                    <span class="block truncate font-semibold text-slate-800">{{ Auth::user()->name }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ __('Perfil e conta') }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 text-slate-400" :class="sidebarCollapsed ? 'lg:hidden' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                </svg>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 transition hover:bg-rose-50 hover:text-rose-700"
                    :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                    title="Sair"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M10 4.75H5.75a1 1 0 0 0-1 1v12.5a1 1 0 0 0 1 1H10m4-3 4-4-4-4m4 4H9" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                    </svg>
                    <span :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ __('Sair') }}</span>
                </button>
            </form>
        </div>
    </aside>
</div>
