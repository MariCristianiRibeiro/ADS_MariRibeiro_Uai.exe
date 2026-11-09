<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="title" class="block text-sm font-medium text-gray-700">Título <x-info-tip text="Use um título curto e específico para identificar a reunião na agenda e no histórico." /></label>
        <input id="title" name="title" type="text" value="{{ old('title', $meeting->title ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('title') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </div>
    <div>
        @php
            $categoryOptions = ['Planejamento', 'Tomada de decisão', 'Acompanhamento', 'Alinhamento', 'Informativa', 'Outro'];
            $selectedCategory = old('category', $meeting->category ?? '');
        @endphp
        <label for="category" class="block text-sm font-medium text-gray-700">Categoria <x-info-tip text="Classifique a reunião para organizar filtros e comparar reuniões do mesmo tipo." /></label>
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Selecione uma categoria</option>
            @foreach ($categoryOptions as $categoryOption)
                <option value="{{ $categoryOption }}" @selected($selectedCategory === $categoryOption)>{{ $categoryOption }}</option>
            @endforeach
            @if (filled($selectedCategory) && ! in_array($selectedCategory, $categoryOptions, true))
                <option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }} (categoria atual)</option>
            @endif
        </select>
    </div>
    <div>
        <label for="organizer_id" class="block text-sm font-medium text-gray-700">Organizador <x-info-tip text="Pessoa responsável por convocar, conduzir e registrar os próximos passos da reunião." /></label>
        <select id="organizer_id" name="organizer_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($members as $member)
                <option value="{{ $member->id }}" @selected((int) old('organizer_id', $meeting->organizer_id ?? auth()->id()) === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('organizer_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </div>
    <div class="md:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">Contexto <x-info-tip text="Registre antecedentes e informações necessárias para entender o assunto. Não repita aqui a pauta nem o resultado esperado." /></label>
        <textarea id="description" name="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $meeting->description ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="objective" class="block text-sm font-medium text-gray-700">Objetivo <x-info-tip text="Explique por que a reunião precisa acontecer e qual questão deve resolver." /></label>
        <textarea id="objective" name="objective" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('objective', $meeting->objective ?? '') }}</textarea>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3">
            <label for="project_id" class="block text-sm font-medium text-gray-700">Projeto <x-info-tip text="Vincule a reunião a um projeto para facilitar o acompanhamento e os relatórios." /></label>
            <a href="{{ route('projects.create', ['return_to' => 'meeting']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Cadastrar projeto</a>
        </div>
        <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Sem projeto</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((int) old('project_id', $meeting->project_id ?? request('project_id')) === $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="status" class="block text-sm font-medium text-gray-700">Status <x-info-tip text="Estado atual do registro. O cronômetro real deve ser iniciado quando a reunião começar." /></label>
        <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach (['draft' => 'Rascunho', 'scheduled' => 'Agendada', 'ongoing' => 'Em andamento', 'completed' => 'Concluída', 'canceled' => 'Cancelada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $meeting->status ?? 'draft') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="date" class="block text-sm font-medium text-gray-700">Data <x-info-tip text="Data planejada para a reunião." /></label>
        <input id="date" name="date" type="date" value="{{ old('date', isset($meeting) ? $meeting->date?->format('Y-m-d') : '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('date') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="time" class="block text-sm font-medium text-gray-700">Horário inicial <x-info-tip text="Horário previsto de início." /></label>
            <input id="time" name="time" type="time" value="{{ old('time', isset($meeting) ? substr((string) $meeting->time, 0, 5) : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div>
            <label for="end_time" class="block text-sm font-medium text-gray-700">Horário final <x-info-tip text="Horário previsto de término. Se informado, deve ser posterior ao horário inicial." /></label>
            <input id="end_time" name="end_time" type="time" value="{{ old('end_time', isset($meeting) ? substr((string) $meeting->end_time, 0, 5) : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
    </div>
    @error('end_time') <span class="text-sm text-red-600 md:col-span-2">{{ $message }}</span> @enderror
    <div>
        <label for="location" class="block text-sm font-medium text-gray-700">Local ou link <x-info-tip text="Informe a sala ou o link de acesso à reunião." /></label>
        <input id="location" name="location" value="{{ old('location', $meeting->location ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    </div>
    <div>
        <label for="meeting_format" class="block text-sm font-medium text-gray-700">Formato <x-info-tip text="Indique se a reunião será presencial, virtual ou híbrida." /></label>
        <select id="meeting_format" name="meeting_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Não definido</option>
            @foreach (['in_person' => 'Presencial', 'virtual' => 'Virtual', 'hybrid' => 'Híbrido'] as $value => $label)
                <option value="{{ $value }}" @selected(old('meeting_format', $meeting->meeting_format ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="priority" class="block text-sm font-medium text-gray-700">Prioridade <x-info-tip text="Indique a urgência relativa da reunião para ajudar na organização da agenda." /></label>
        <select id="priority" name="priority" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach (['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $meeting->priority ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="recurrence_type" class="block text-sm font-medium text-gray-700">Tipo <x-info-tip text="Escolha única para um encontro isolado ou recorrente para encontros repetidos." /></label>
        <select id="recurrence_type" name="recurrence_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="single" @selected(old('recurrence_type', $meeting->recurrence_type ?? 'single') === 'single')>Única</option>
            <option value="recurring" @selected(old('recurrence_type', $meeting->recurrence_type ?? 'single') === 'recurring')>Recorrente</option>
        </select>
    </div>
    <div>
        <label for="recurrence_frequency" class="block text-sm font-medium text-gray-700">Periodicidade <x-info-tip text="Frequência dos encontros: diária, semanal, mensal ou anual." /></label>
        <select id="recurrence_frequency" name="recurrence_frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Sem recorrência</option>
            @foreach (['daily' => 'Diária', 'weekly' => 'Semanal', 'monthly' => 'Mensal', 'yearly' => 'Anual'] as $value => $label)
                <option value="{{ $value }}" @selected(old('recurrence_frequency', $meeting->recurrence_frequency ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="recurrence_interval" class="block text-sm font-medium text-gray-700">Repetir a cada <x-info-tip text="Número de períodos entre encontros. Por exemplo, 2 com periodicidade semanal significa a cada duas semanas." /></label>
        <input id="recurrence_interval" name="recurrence_interval" type="number" min="1" max="52" value="{{ old('recurrence_interval', $meeting->recurrence_interval ?? 1) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    </div>
    <div>
        <label for="recurrence_ends_at" class="block text-sm font-medium text-gray-700">Recorrência até <x-info-tip text="Última data em que esta reunião recorrente deve acontecer; usada na projeção anual." /></label>
        <input id="recurrence_ends_at" name="recurrence_ends_at" type="date" value="{{ old('recurrence_ends_at', isset($meeting) ? $meeting->recurrence_ends_at?->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    </div>
    <div class="md:col-span-2">
        <label for="agenda" class="block text-sm font-medium text-gray-700">Pauta <x-info-tip text="Liste os assuntos e decisões a tratar. Se possível, indique a ordem e o tempo previsto para cada tópico." /></label>
        <textarea id="agenda" name="agenda" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('agenda', $meeting->agenda ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="expected_outcome" class="block text-sm font-medium text-gray-700">Resultado esperado <x-info-tip text="Descreva o que deve estar decidido, aprovado ou produzido ao final." /></label>
        <textarea id="expected_outcome" name="expected_outcome" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('expected_outcome', $meeting->expected_outcome ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-gray-700">Observações adicionais <x-info-tip text="Registre detalhes operacionais relevantes que não pertencem ao objetivo, contexto ou pauta." /></label>
        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $meeting->notes ?? '') }}</textarea>
    </div>
</div>
