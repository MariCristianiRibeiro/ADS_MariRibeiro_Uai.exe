<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Cadastrar equipe</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('teams.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nome da equipe</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required autofocus class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="network" class="block text-sm font-medium text-gray-700">Rede</label>
                        <input id="network" name="network" type="text" value="{{ old('network') }}" maxlength="255" required list="existing-networks" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <datalist id="existing-networks">
                            @foreach ($networkNames as $networkName)
                                <option value="{{ $networkName }}"></option>
                            @endforeach
                        </datalist>
                        @error('network')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Informe uma rede existente ou digite o nome para cadastrá-la automaticamente.</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('teams.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-700">
                            Salvar equipe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
