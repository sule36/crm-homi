<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvoice;
use App\Models\Project;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user->company_id) {
            return redirect()->route('super-admin.companies.index');
        }

        $company = Company::with(['invoices' => fn($q) => $q->latest()])->findOrFail($user->company_id);

        $usage = [
            'users_count' => User::where('company_id', $company->id)->count(),
            'users_limit' => $company->max_users,
            'projects_count' => Project::where('company_id', $company->id)->count(),
            'projects_limit' => $company->max_projects,
            'is_near_user_limit' => User::where('company_id', $company->id)->count() >= ($company->max_users * 0.8),
            'is_near_project_limit' => Project::where('company_id', $company->id)->count() >= ($company->max_projects * 0.8),
        ];

        return Inertia::render('Subscription/Index', [
            'company' => $company,
            'usage' => $usage,
            'invoices' => $company->invoices,
        ]);
    }

    public function uploadPaymentProof(Request $request, CompanyInvoice $invoice)
    {
        $user = auth()->user();
        if ($invoice->company_id !== $user->company_id) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'payment_proof' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $proofPath = $request->file('payment_proof')->store('saas_payment_proofs', 'public');

        $invoice->update([
            'payment_proof' => $proofPath,
            'payment_method' => $request->payment_method ?: 'Bank Transfer',
            'notes' => $request->notes ?: $invoice->notes,
        ]);

        AuditLog::record('saas_payment_proof_uploaded', $invoice, null, [
            'invoice' => $invoice->invoice_number,
            'proof' => $proofPath,
        ]);

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Tim pengelola platform SaaS akan segera memverifikasi pembayaran Anda.');
    }
}
