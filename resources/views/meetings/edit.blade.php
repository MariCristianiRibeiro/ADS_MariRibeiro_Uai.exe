<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar reunião</h2></x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('meetings.update', $meeting) }}">
                    @csrf
                    @method('PUT')
                    @php $selectedMemberIds = collect(old('participants', $meeting->users->modelKeys()))->map(fn ($id) => (int) $id)->all(); @endphp
                    <div class="space-y-6">
                        @include('meetings._meeting-fields')
                        @include('meetings._participant-picker', ['selectedMemberIds' => $selectedMemberIds])
                        @include('meetings._timer', ['selectedParticipantIds' => $selectedMemberIds])
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('meetings.show', $meeting) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm">Voltar</a>
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
