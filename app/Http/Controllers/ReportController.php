<?php

namespace App\Http\Controllers;

use App\Services\MeetingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function meetings(MeetingReportService $reports): JsonResponse
    {
        abort_unless(Auth::user()->hasPermission('reports.view'), 403);

        return response()->json($reports->for(Auth::user()));
    }
}
