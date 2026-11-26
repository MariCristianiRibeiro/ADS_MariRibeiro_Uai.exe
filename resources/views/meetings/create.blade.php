<x-app-layout>

<div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="max-w-4xl bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('meetings.store') }}">
                    @csrf
                    @error('preflight_token')<p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $message }}</p>@enderror
                    @php $selectedMemberIds = collect(old('participants', []))->map(fn ($id) => (int) $id)->all(); @endphp
                    <div class="space-y-6">
                        @include('meetings._meeting-fields')
                        @include('meetings._participant-picker', ['selectedMemberIds' => $selectedMemberIds])
                        @include('meetings._meeting-investment', ['selectedParticipantIds' => $selectedMemberIds])
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('meetings.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm">Cancelar</a>
                        <button type="submit" formaction="{{ route('meetings.preview') }}" class="rounded-md bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-teal-800">Analisar investimento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
