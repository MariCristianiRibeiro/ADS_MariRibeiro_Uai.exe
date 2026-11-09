<?php

namespace App\Http\Controllers;

use App\Services\MeetingAiService;
use App\Services\MeetingDashboardService;
use App\Services\MeetingReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function meetings(MeetingReportService $reports): JsonResponse
    {
        abort_unless(Auth::user()->hasPermission('reports.view'), 403);

        return response()->json($reports->for(Auth::user()));
    }

    public function dashboard(Request $request, MeetingDashboardService $dashboard): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $this->filters($request);
        if (! $request->user()->hasPermission('reports.individuals')) {
            $filters['collaborator'] = null;
            $filters['organizer'] = null;
        }
        $data = $dashboard->data($request->user(), $filters);

        return view('reports.meetings-dashboard', $data + [
            'aiResult' => $request->session()->get('meeting_dashboard_ai_result'),
            'aiError' => $request->session()->get('meeting_dashboard_ai_error'),
            'aiConfigured' => app(MeetingAiService::class)->isConfigured(),
        ]);
    }

    public function analyze(Request $request, MeetingDashboardService $dashboard, MeetingAiService $ai): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $this->filters($request);
        if (! $request->user()->hasPermission('reports.individuals')) {
            $filters['collaborator'] = null;
            $filters['organizer'] = null;
        }
        $data = $dashboard->data($request->user(), $filters);

        try {
            $result = $ai->analyze('dashboard', $data['aiContext']);
            $request->session()->flash('meeting_dashboard_ai_result', $result['result']);
            $request->session()->flash('meeting_dashboard_ai_filters', $filters);
        } catch (RuntimeException $exception) {
            Log::warning('Meeting dashboard AI analysis failed.', ['message' => $exception->getMessage(), 'user_id' => $request->user()->id]);
            $request->session()->flash('meeting_dashboard_ai_error', $exception->getMessage());
        }

        return redirect()->route('reports.meetings.dashboard', $filters);
    }

    public function export(Request $request, MeetingDashboardService $dashboard): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);
        $filters = $this->filters($request);
        if (! $request->user()->hasPermission('reports.individuals')) {
            $filters['collaborator'] = null;
            $filters['organizer'] = null;
        }
        $data = $dashboard->data($request->user(), $filters);
        $financialAccess = $data['canViewCosts'];
        $history = $data['history'];

        return response()->streamDownload(function () use ($history, $financialAccess): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $financialAccess
                ? ['Período', 'Reuniões', 'Horas colaborador', 'Custo apurado (R$)']
                : ['Período', 'Reuniões', 'Horas colaborador'], ';');
            foreach ($history as $row) {
                $values = [$row['period'], $row['meetings'], $row['collaborator_hours']];
                if ($financialAccess) {
                    $values[] = number_format((float) $row['cost'], 2, ',', '');
                }
                fputcsv($output, $values, ';');
            }
            fclose($output);
        }, 'indicadores-reunioes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, string|null> */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'department' => ['nullable', 'integer'], 'directorate' => ['nullable', 'integer'], 'collaborator' => ['nullable', 'integer'],
            'organizer' => ['nullable', 'integer'], 'category' => ['nullable', 'string', 'max:100'],
            'recurrence' => ['nullable', 'string', 'max:50'], 'status' => ['nullable', 'in:scheduled,ongoing,completed,canceled'],
        ]);

        return [
            'date_from' => isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->toDateString() : now()->subMonths(11)->startOfMonth()->toDateString(),
            'date_to' => isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->toDateString() : now()->toDateString(),
            'department' => $validated['department'] ?? null, 'directorate' => $validated['directorate'] ?? null,
            'collaborator' => $validated['collaborator'] ?? null, 'organizer' => $validated['organizer'] ?? null,
            'category' => $validated['category'] ?? null, 'recurrence' => $validated['recurrence'] ?? null,
            'status' => $validated['status'] ?? null,
        ];
    }
}
