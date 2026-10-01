<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInvoice;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaaSInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = CompanyInvoice::with('company')
            ->when($request->search, function ($q, $s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhereHas('company', fn($cq) => $cq->where('name', 'like', "%{$s}%"));
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_invoiced' => CompanyInvoice::sum('amount'),
            'total_paid' => CompanyInvoice::where('status', 'paid')->sum('amount'),
            'total_pending' => CompanyInvoice::where('status', 'unpaid')->sum('amount'),
            'unpaid_count' => CompanyInvoice::where('status', 'unpaid')->count(),
        ];

        return Inertia::render('SuperAdmin/Invoices/Index', [
            'invoices' => $invoices,
            'companies' => Company::where('status', 'active')->get(['id', 'name', 'subscription_plan']),
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'plan' => 'required|in:starter,pro,enterprise',
            'amount' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $invoice = CompanyInvoice::create([
            ...$validated,
            'invoice_number' => CompanyInvoice::generateInvoiceNumber(),
            'status' => 'unpaid',
        ]);

        AuditLog::record('saas_invoice_created', $invoice, null, $invoice->toArray());

        return back()->with('success', "Invoice SaaS {$invoice->invoice_number} berhasil diterbitkan untuk developer.");
    }

    public function markPaid(Request $request, CompanyInvoice $invoice)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|max:50',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $invoice->update([
            'status' => 'paid',
            'payment_method' => $validated['payment_method'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'notes' => $validated['notes'] ?? $invoice->notes,
        ]);

        // Perpanjang masa aktif developer dan pastikan status active
        $company = $invoice->company;
        if ($company) {
            $currentExpires = $company->expires_at && $company->expires_at->isFuture()
                ? $company->expires_at
                : now();

            $newExpires = $currentExpires->copy()->addMonth();
            $company->update([
                'status' => 'active',
                'expires_at' => $newExpires,
                'subscription_plan' => $invoice->plan,
            ]);
        }

        AuditLog::record('saas_invoice_paid', $invoice, null, [
            'invoice' => $invoice->invoice_number,
            'company' => $company?->name,
            'amount' => $invoice->amount,
        ]);

        return back()->with('success', "Pembayaran tagihan {$invoice->invoice_number} berhasil dikonfirmasi. Akun developer aktif hingga " . ($newExpires?->format('d M Y') ?? '1 bulan ke depan') . ".");
    }

    public function destroy(CompanyInvoice $invoice)
    {
        $invoice->delete();
        return back()->with('success', "Invoice tagihan SaaS berhasil dihapus.");
    }
}
