<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::query()->withCount('meetings')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
            'returnToMeeting' => $request->query('return_to') === 'meeting',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:projects,name'],
            'return_to' => ['nullable', 'in:meeting'],
        ]);

        $baseCode = Str::upper(Str::slug($validated['name'])) ?: 'PROJETO';
        $code = $baseCode;
        $suffix = 2;

        while (Project::query()->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        $project = Project::create([
            'name' => $validated['name'],
            'code' => $code,
            'manager_id' => Auth::user()->role === 'manager' ? Auth::id() : null,
        ]);

        if (($validated['return_to'] ?? null) === 'meeting') {
            return redirect()->route('meetings.create', ['project_id' => $project->id])
                ->with('success', 'Projeto cadastrado. Ele já está selecionado para a nova reunião.');
        }

        return redirect()->route('projects.index')->with('success', 'Projeto cadastrado com sucesso.');
    }
}
