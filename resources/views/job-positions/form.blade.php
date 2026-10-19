<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $position->exists ? 'Editar cargo' : 'Novo cargo' }}</h2></x-slot>
    <div class="py-6"><div class="mx-auto max-w-2xl sm:px-6 lg:px-8"><form method="POST" action="{{ $position->exists ? route('job-positions.update', $position) : route('job-positions.store') }}" class="space-y-6 rounded bg-white p-6 shadow">@csrf @if ($position->exists) @method('PUT') @endif
        <div><label class="block text-sm font-medium">Nome</label><input name="name" required maxlength="255" value="{{ old('name', $position->name) }}" class="mt-1 w-full rounded border-gray-300">@error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="block text-sm font-medium">Descrição</label><textarea name="description" class="mt-1 w-full rounded border-gray-300">{{ old('description', $position->description) }}</textarea>@error('description')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="block text-sm font-medium">Valor-hora (R$)</label><input name="hourly_rate" type="number" min="0" step="0.01" required value="{{ old('hourly_rate', $position->hourly_rate) }}" class="mt-1 w-full rounded border-gray-300">@error('hourly_rate')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        @if ($position->exists)<label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active', $position->active))> Cargo ativo</label>@endif
        <div class="flex justify-end gap-3"><a href="{{ route('job-positions.index') }}" class="rounded border px-4 py-2">Cancelar</a><button class="rounded bg-gray-800 px-4 py-2 text-white">Salvar</button></div>
    </form></div></div>
</x-app-layout>
