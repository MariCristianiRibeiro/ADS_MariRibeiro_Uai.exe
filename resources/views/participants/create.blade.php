<x-app-layout>
<div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="max-w-2xl overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('participants.store') }}">
                    @csrf

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Senha de acesso</label>
                            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            @error('password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            <p class="mt-1 text-xs text-gray-500">Será criada uma conta de acesso para este participante.</p>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label for="team_id" class="block text-sm font-medium text-gray-700">Equipe / rede</label>
                                @can('manage-structure')
                                    <a href="{{ route('teams.create') }}" class="text-sm font-medium text-teal-600 hover:text-teal-800">+ Cadastrar equipe</a>
                                @endcan
                            </div>
                            <select id="team_id" name="team_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">Sem equipe</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>{{ $team->name }} — {{ $team->directorate->name }}</option>
                                @endforeach
                            </select>
                            @error('team_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div><label for="job_position_id" class="block text-sm font-medium text-gray-700">Cargo</label><select id="job_position_id" name="job_position_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($jobPositions as $jobPosition)<option value="{{ $jobPosition->id }}" @selected(old('job_position_id') == $jobPosition->id)>{{ $jobPosition->name }}</option>@endforeach</select>@error('job_position_id')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>

                        @can('manage-users')
                            <div><label for="profile_ids" class="block text-sm font-medium text-gray-700">Perfil de acesso</label><select id="profile_ids" name="profile_ids[]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="" disabled @selected(count(old('profile_ids', [])) === 0)>Selecione o perfil de acesso</option>@foreach ($accessProfiles as $profile)<option value="{{ $profile->id }}" @selected(in_array($profile->id, old('profile_ids', [])))>{{ $profile->name }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">Selecione um perfil predefinido. Somente perfis ativos podem ser atribuídos.</p>@error('profile_ids')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        @endcan

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="active">Ativo</option>
                                <option value="pending">Pendente</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            @error('status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo financeiro</label>
                            <select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="internal">Interno</option>
                                <option value="external">Externo</option>
                                <option value="guest" selected>Convidado</option>
                            </select>
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('notes') }}</textarea>
                            @error('notes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('participants.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-red-600 uppercase tracking-widest shadow-sm hover:bg-gray-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">Cancelar</a>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-teal-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-800 focus:bg-teal-800 active:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
