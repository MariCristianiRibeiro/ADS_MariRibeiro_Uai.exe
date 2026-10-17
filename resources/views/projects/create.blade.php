<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Cadastrar projeto</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('projects.store') }}" class="space-y-6">
                    @csrf
                    @if ($returnToMeeting)
                        <input type="hidden" name="return_to" value="meeting">
                    @endif

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nome do projeto</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required autofocus class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">O código interno do projeto será criado automaticamente.</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ $returnToMeeting ? route('meetings.create') : route('projects.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-700">
                            Salvar projeto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
