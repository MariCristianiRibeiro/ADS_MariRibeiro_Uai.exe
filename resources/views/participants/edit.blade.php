<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar participante
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('participants.update', $participant) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $participant->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $participant->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div><label for="member_user_id" class="block text-sm font-medium text-gray-700">Usuário interno</label><select id="member_user_id" name="member_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Convidado externo, sem acesso</option>@foreach ($members as $member)<option value="{{ $member->id }}" {{ old('member_user_id', $participant->member_user_id) == $member->id ? 'selected' : '' }}>{{ $member->name }} - {{ ['admin' => 'Administrador geral', 'hr' => 'RH', 'executive' => 'Executiva', 'director_commercial' => 'Diretor Comercial', 'director_network' => 'Diretor de Rede', 'manager' => 'Gestor', 'participant' => 'Colaborador'][$member->role] ?? 'Colaborador' }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">O perfil e o acesso vêm do usuário. Não é necessário cadastrar o perfil novamente.</p></div>

                        <div><label for="job_position_id" class="block text-sm font-medium text-gray-700">Cargo</label><select id="job_position_id" name="job_position_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Não atribuído</option>@foreach ($jobPositions as $jobPosition)<option value="{{ $jobPosition->id }}" @selected(old('job_position_id', $participant->memberUser?->job_position_id) == $jobPosition->id)>{{ $jobPosition->name }}{{ $jobPosition->active ? '' : ' (inativo)' }}</option>@endforeach</select>@error('job_position_id')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>

                        @can('manage-users')
                            <div><label for="profile_ids" class="block text-sm font-medium text-gray-700">Perfis de acesso</label><select id="profile_ids" name="profile_ids[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">@foreach ($accessProfiles as $profile)<option value="{{ $profile->id }}" @selected(in_array($profile->id, old('profile_ids', $selectedProfileIds)))>{{ $profile->name }}{{ $profile->active ? '' : ' (inativo)' }}</option>@endforeach</select><label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="clear_profiles" value="1" @checked(old('clear_profiles'))> Remover todos os perfis de acesso</label>@error('profile_ids')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        @endcan

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label for="team_id" class="block text-sm font-medium text-gray-700">Equipe / rede</label>
                                @can('manage-structure')
                                    <a href="{{ route('teams.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Cadastrar equipe</a>
                                @endcan
                            </div>
                            <select id="team_id" name="team_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sem equipe</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}" {{ old('team_id', $participant->memberUser?->team_id) == $team->id ? 'selected' : '' }}>{{ $team->name }} — {{ $team->directorate->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">A equipe selecionada também define a rede do participante. Vincule um usuário interno para salvar a seleção.</p>
                            @error('team_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $participant->phone) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active" {{ old('status', $participant->status) === 'active' ? 'selected' : '' }}>Ativo</option>
                                <option value="pending" {{ old('status', $participant->status) === 'pending' ? 'selected' : '' }}>Pendente</option>
                                <option value="inactive" {{ old('status', $participant->status) === 'inactive' ? 'selected' : '' }}>Inativo</option>
                            </select>
                            @error('status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo financeiro</label>
                            <select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="internal" {{ old('type', $participant->type ?? 'guest') === 'internal' ? 'selected' : '' }}>Interno</option>
                                <option value="external" {{ old('type', $participant->type ?? 'guest') === 'external' ? 'selected' : '' }}>Externo</option>
                                <option value="guest" {{ old('type', $participant->type ?? 'guest') === 'guest' ? 'selected' : '' }}>Convidado</option>
                            </select>
                        </div>

                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700">Valor-hora para cálculo das reuniões (R$)</label>
                            <input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate', $participant->hourly_rate) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500">O valor é multiplicado pelo tempo do cronômetro para calcular o investimento da reunião.</p>
                            @error('hourly_rate') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $participant->notes) }}</textarea>
                            @error('notes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('participants.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">Cancelar</a>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
