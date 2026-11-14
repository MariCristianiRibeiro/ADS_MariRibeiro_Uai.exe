<x-guest-layout>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-teal-700">Bem-vindo ao Uai</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Acesse sua conta</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Entre para continuar de onde parou.</p>
    </div>

    <x-auth-session-status class="mb-5 rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-800" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <x-input-label for="email" :value="__('E-mail')" class="text-sm font-semibold text-slate-700" />
            <x-text-input id="email" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-none transition placeholder:text-slate-400 focus:border-teal-600 focus:bg-white focus:ring-4 focus:ring-teal-600/10" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="voce@exemplo.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="space-y-2">
            <x-input-label for="password" :value="__('Senha')" class="text-sm font-semibold text-slate-700" />
            <x-text-input id="password" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm shadow-none transition focus:border-teal-600 focus:bg-white focus:ring-4 focus:ring-teal-600/10"
                          type="password"
                          name="password"
                          required autocomplete="current-password" placeholder="Sua senha" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-teal-700 shadow-sm focus:ring-teal-600" name="remember">
                <span class="text-sm text-slate-600">{{ __('Lembrar-me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-teal-700 transition hover:text-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2" href="{{ route('password.request') }}">
                    {{ __('Esqueci minha senha') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center rounded-xl border-0 bg-teal-700 px-4 py-3 text-sm font-semibold normal-case tracking-normal shadow-sm hover:bg-teal-800 focus:bg-teal-800 focus:ring-teal-600 active:bg-teal-900">
            {{ __('Entrar') }}
        </x-primary-button>

        @if (Route::has('register'))
            <p class="pt-1 text-center text-sm text-slate-500">
                Ainda não tem conta?
                <a class="font-semibold text-teal-700 transition hover:text-teal-900" href="{{ route('register') }}">
                    {{ __('Criar conta') }}
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>
