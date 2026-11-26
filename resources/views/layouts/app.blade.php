<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarCollapsed: false, mobileOpen: false }" class="min-h-screen bg-slate-50">
            @include('layouts.navigation')

            <div class="min-h-screen transition-all duration-300 ease-in-out" :class="sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'">
                <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-md sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                        @click="mobileOpen = true"
                        aria-label="Abrir menu"
                        :aria-expanded="mobileOpen"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 6.75h16M4 12h16M4 17.25h16" stroke-linecap="round" stroke-width="1.8" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 lg:flex"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                        aria-label="Recolher ou expandir menu"
                        :aria-expanded="!sidebarCollapsed"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2" stroke-width="1.6" />
                            <path d="M9 5v14m4.5-9 2 2-2 2" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" />
                        </svg>
                    </button>
                    @isset($header)
                        <div class="app-page-heading min-w-0 flex-1">
                            {{ $header }}
                        </div>
                    @else
                        <div class="flex-1"></div>
                    @endisset

                    <div class="hidden items-center gap-2 text-sm font-medium text-slate-500 sm:flex">
                        <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                        {{ Auth::user()->name }}
                    </div>
                </header>

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
