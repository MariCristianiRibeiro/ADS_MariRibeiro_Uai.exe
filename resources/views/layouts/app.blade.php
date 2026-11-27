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

            <div class="{{ request()->routeIs('dashboard') ? 'min-h-[calc(100vh-4rem)]' : 'min-h-screen' }} transition-all duration-300 ease-in-out" :class="sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'">
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
                    <div class="flex min-w-0 items-baseline gap-1">
                        <a href="{{ route('dashboard') }}" aria-label="Uai - início" class="shrink-0 text-2xl font-extrabold tracking-tight text-teal-800">Uai</a>
                        @if (request()->routeIs('dashboard'))
                            <span class="min-w-0 max-w-[14rem] text-[0.65rem] font-normal uppercase leading-tight tracking-wide text-slate-500 sm:max-w-none sm:text-xs">, precisava mesmo?</span>
                        @elseif (request()->routeIs('meetings.*'))
                            <span class="min-w-0 max-w-[14rem] text-[0.65rem] font-normal uppercase leading-tight tracking-wide text-slate-500 sm:max-w-none sm:text-xs">, não poderia ser por e-mail?</span>
                        @endif
                    </div>
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
                    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                        @include('components.management-navigation')
                    </div>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
