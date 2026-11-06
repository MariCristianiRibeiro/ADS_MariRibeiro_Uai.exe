<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nova permissão</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('access-profiles.permissions.store') }}" class="space-y-6 rounded bg-white p-6 shadow">
                @csrf
                <p class="text-sm text-gray-600">Cadastre uma permissão para incluí-la nos perfis. Para proteger uma nova funcionalidade, ela também precisa verificar esta permissão no código da aplicação.</p>
                <div>
                    <label for="resource" class="block text-sm font-medium">Módulo</label>
                    <input id="resource" name="resource" value="{{ old('resource') }}" required maxlength="100" pattern="[a-z][a-z0-9_]*" placeholder="ex.: finance" class="mt-1 w-full rounded border-gray-300">
                    <p class="mt-1 text-xs text-gray-500">Use letras minúsculas, números e sublinhado; comece por uma letra.</p>
                    @error('resource')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="action" class="block text-sm font-medium">Ação</label>
                    <input id="action" name="action" value="{{ old('action') }}" required maxlength="100" pattern="[a-z][a-z0-9_]*" placeholder="ex.: view" class="mt-1 w-full rounded border-gray-300">
                    @error('action')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium">Descrição</label>
                    <input id="description" name="description" value="{{ old('description') }}" required maxlength="255" class="mt-1 w-full rounded border-gray-300">
                    @error('description')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-3">
                    <a href="{{ route('access-profiles.index') }}" class="rounded border px-4 py-2">Cancelar</a>
                    <button class="rounded bg-gray-800 px-4 py-2 text-white">Salvar permissão</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
