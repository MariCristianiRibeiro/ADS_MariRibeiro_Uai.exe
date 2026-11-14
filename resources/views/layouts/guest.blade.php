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
    <body class="font-sans text-slate-900 antialiased">
        <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-12 sm:px-6">
            <div class="pointer-events-none absolute -left-32 -top-32 h-80 w-80 rounded-full bg-teal-100/70 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 -right-24 h-96 w-96 rounded-full bg-cyan-100/60 blur-3xl"></div>

            <div class="relative w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-3 text-center">
                    <a href="/" aria-label="Uai - início">
                        <x-application-logo class="text-5xl text-teal-700" />
                    </a>
                    <p class="text-sm font-medium tracking-wide text-slate-500">seu tempo vale muito</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-400">Organize sua rotina com mais leveza.</p>
            </div>
        </main>
    </body>
</html>
