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
    public function index(): View
    {
        return view('teams.index', [
            'teams' => Team::query()
                ->with('directorate')
                ->withCount('members')
                ->orderBy('directorate_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('teams.create', [
            'networkNames' => Directorate::query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $networkName = trim($validated['network']);
            $directorate = Directorate::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($networkName)])
                ->first();

            if ($directorate === null) {
                $directorateCode = $this->uniqueCode(Directorate::query(), $networkName, 'REDE');
                $directorate = Directorate::create([
                    'name' => $networkName,
                    'code' => $directorateCode,
                ]);
            }

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
