<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/AuditLogs', [
            'logs' => AuditLog::with('user')->latest()->paginate(50)
        ]);
    }
}
