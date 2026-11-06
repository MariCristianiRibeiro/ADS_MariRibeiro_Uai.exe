<?php

namespace App\Http\Controllers;

use App\Models\AccessControlAudit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessControlAuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('access_profiles.administer'), 403);

        $audits = AccessControlAudit::query()
            ->with(['actor', 'subject', 'accessProfile', 'permission'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(50);

        return view('access-profiles.audit', compact('audits'));
    }
}
