<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Participant::with(['user.supervisors'])
            ->where('user_id', Auth::id())
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                                $searchTerm = '%' . mb_strtolower($search) . '%';
                                $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                                    ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm])
                                    ->orWhereRaw('LOWER(phone) LIKE ?', [$searchTerm]);
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $participants = $query->get();

        return view('participants.index', [
            'participants' => $participants,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
        ]);
    }

    public function create(): View
    {
        return view('participants.create', ['members' => User::where('status', 'active')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', 'guest')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email'],
            'member_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['user_id'] = Auth::id();

        $validated['role'] = ($validated['member_user_id'] ?? null)
            ? User::findOrFail($validated['member_user_id'])->role
            : null;

        Participant::create($validated);

        return redirect()->route('participants.index')->with('success', 'Participante cadastrado com sucesso.');
    }

    public function edit(Participant $participant): View
    {
        return view('participants.edit', [
            'participant' => $participant,
            'members' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Participant $participant): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', $participant->type ?? 'guest')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email,' . $participant->id],
            'member_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['role'] = ($validated['member_user_id'] ?? null)
            ? User::findOrFail($validated['member_user_id'])->role
            : null;

        $participant->update($validated);

        return redirect()->route('participants.index')->with('success', 'Participante atualizado com sucesso.');
    }

    public function destroy(Participant $participant): RedirectResponse
    {
        $participant->delete();

        return redirect()->route('participants.index')->with('success', 'Participante removido com sucesso.');
    }
}
