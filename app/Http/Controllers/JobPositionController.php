<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobPositionController extends Controller
{
    public function index(Request $request): View
    {
        $positions = JobPosition::withCount('users')->when($request->filled('search'), fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($request->search)).'%']))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('job-positions.index', compact('positions'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasPermission('job_positions.create'), 403);

        return view('job-positions.form', ['position' => new JobPosition]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('job_positions.create'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:job_positions,name'], 'description' => ['nullable', 'string'], 'hourly_rate' => ['required', 'numeric', 'min:0'], 'active' => ['sometimes', 'boolean']]);
        $baseCode = Str::upper(Str::slug($validated['name'])) ?: 'CARGO';
        $code = $baseCode;
        $suffix = 2;

        while (JobPosition::query()->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        JobPosition::create([...$validated, 'code' => $code, 'active' => $validated['active'] ?? true]);

        return redirect()->route('job-positions.index')->with('success', 'Cargo criado com sucesso.');
    }

    public function edit(JobPosition $jobPosition): View
    {
        abort_unless(auth()->user()->hasPermission('job_positions.edit'), 403);

        return view('job-positions.form', ['position' => $jobPosition]);
    }

    public function update(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('job_positions.edit'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('job_positions', 'name')->ignore($jobPosition->id)], 'description' => ['nullable', 'string'], 'hourly_rate' => ['required', 'numeric', 'min:0'], 'active' => ['sometimes', 'boolean']]);
        $jobPosition->update([...$validated, 'active' => $validated['active'] ?? false]);

        return redirect()->route('job-positions.index')->with('success', 'Cargo atualizado com sucesso.');
    }

    public function deactivate(JobPosition $jobPosition): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('job_positions.deactivate'), 403);
        $jobPosition->update(['active' => false]);

        return redirect()->route('job-positions.index')->with('success', 'Cargo inativado com sucesso.');
    }
}
