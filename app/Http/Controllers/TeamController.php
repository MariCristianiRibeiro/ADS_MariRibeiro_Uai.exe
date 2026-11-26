<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('teams.view'), 403);

        $search = trim((string) $request->query('search', ''));

        return view('teams.index', [
            'teams' => Team::query()
                ->with('directorate')
                ->withCount('members')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $term = '%'.mb_strtolower($search).'%';
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereHas('directorate', fn ($directorateQuery) => $directorateQuery->whereRaw('LOWER(name) LIKE ?', [$term]));
                }))
                ->orderBy('directorate_id')
                ->orderBy('name')
                ->get(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasPermission('teams.create'), 403);

        return view('teams.create', [
            'team' => new Team,
            'networkNames' => Directorate::query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teams.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $directorate = $this->findOrCreateDirectorate(trim($validated['network']));
            $teamName = trim($validated['name']);
            $teamCode = $this->uniqueCode(
                Team::query()->where('directorate_id', $directorate->id),
                $teamName,
                'EQUIPE',
            );

            Team::create([
                'directorate_id' => $directorate->id,
                'name' => $teamName,
                'code' => $teamCode,
            ]);
        });

        return redirect()->route('teams.index')->with('success', 'Equipe cadastrada com sucesso.');
    }

    public function edit(Team $team): View
    {
        abort_unless(auth()->user()->hasPermission('teams.manage'), 403);

        $team->load('directorate');

        return view('teams.create', [
            'team' => $team,
            'networkNames' => Directorate::query()
                ->where('active', true)
                ->orWhereKey($team->directorate_id)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('teams.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $team): void {
            $directorate = $this->findOrCreateDirectorate(trim($validated['network']));
            $teamName = trim($validated['name']);
            $code = $team->directorate_id === $directorate->id && $team->name === $teamName
                ? $team->code
                : $this->uniqueCode(
                    Team::query()->where('directorate_id', $directorate->id)->where('id', '<>', $team->id),
                    $teamName,
                    'EQUIPE',
                );

            $team->update([
                'directorate_id' => $directorate->id,
                'name' => $teamName,
                'code' => $code,
            ]);
        });

        return redirect()->route('teams.index')->with('success', 'Equipe atualizada com sucesso.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('teams.manage'), 403);

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Equipe removida com sucesso.');
    }

    private function findOrCreateDirectorate(string $networkName): Directorate
    {
        $directorate = Directorate::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($networkName)])
            ->first();

        if ($directorate !== null) {
            return $directorate;
        }

        return Directorate::create([
            'name' => $networkName,
            'code' => $this->uniqueCode(Directorate::query(), $networkName, 'REDE'),
        ]);
    }

    private function uniqueCode(Builder $query, string $name, string $fallback): string
    {
        $baseCode = Str::upper(Str::slug($name)) ?: $fallback;
        $code = $baseCode;
        $suffix = 2;

        while ((clone $query)->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
