<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Lead\LeadController;
use App\Http\Controllers\Inventory\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Public Tracking Portal
Route::get('/track/{token}', [\App\Http\Controllers\PublicTrackingController::class, 'show'])->name('public.tracking');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);

    // Leads
    Route::resource('leads', LeadController::class)->except(['edit']);
    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.updateStatus');
    Route::get('/pipeline', [LeadController::class, 'pipeline'])->name('leads.pipeline');
    Route::post('/leads/{lead}/activity', [LeadController::class, 'addActivity'])->name('leads.activity');
    Route::post('/leads/{lead}/reminder', [LeadController::class, 'addReminder'])->name('leads.reminder');
    Route::post('/reminders/{reminder}/complete', [LeadController::class, 'completeReminder'])->name('reminders.complete');

    // Units / Inventory
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    Route::post('/units/bulk', [UnitController::class, 'bulkStore'])->name('units.bulk');
    Route::post('/units/progress', [\App\Http\Controllers\Inventory\UnitProgressController::class, 'store'])->name('units.progress.store');

    // Unit Types
    Route::post('/unit-types', [\App\Http\Controllers\Inventory\UnitTypeController::class, 'store'])->name('unit-types.store');
    Route::put('/unit-types/{unitType}', [\App\Http\Controllers\Inventory\UnitTypeController::class, 'update'])->name('unit-types.update');
    Route::delete('/unit-types/{unitType}', [\App\Http\Controllers\Inventory\UnitTypeController::class, 'destroy'])->name('unit-types.destroy');

    // Bookings
    Route::get('/bookings', [\App\Http\Controllers\BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [\App\Http\Controllers\BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [\App\Http\Controllers\BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [\App\Http\Controllers\BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/approve', [\App\Http\Controllers\BookingController::class, 'approve'])->name('bookings.approve');
    Route::post('/bookings/{booking}/reject', [\App\Http\Controllers\BookingController::class, 'reject'])->name('bookings.reject');
    Route::post('/bookings/{booking}/cancel', [\App\Http\Controllers\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{booking}/kpr', [\App\Http\Controllers\BookingController::class, 'updateKpr'])->name('bookings.updateKpr');
    Route::get('/bookings/{booking}/spk', [\App\Http\Controllers\SPKController::class, 'download'])->name('bookings.spk.download');
    Route::get('/bookings/{booking}/spk/view', [\App\Http\Controllers\SPKController::class, 'stream'])->name('bookings.spk.stream');
    Route::post('/bookings/{booking}/documents', [\App\Http\Controllers\BookingDocumentController::class, 'store'])->name('bookings.documents.store');
    Route::delete('/booking-documents/{document}', [\App\Http\Controllers\BookingDocumentController::class, 'destroy'])->name('bookings.documents.destroy');

    // WhatsApp Inbox
    Route::get('/whatsapp/inbox', [\App\Http\Controllers\WhatsAppChatController::class, 'index'])->name('whatsapp.inbox');
    Route::get('/whatsapp/chat/{phone}', [\App\Http\Controllers\WhatsAppChatController::class, 'getMessages'])->name('whatsapp.chat.messages');
    Route::post('/whatsapp/send', [\App\Http\Controllers\WhatsAppChatController::class, 'sendMessage'])->name('whatsapp.chat.send');
    Route::post('/whatsapp/leads/{lead}/status', [\App\Http\Controllers\WhatsAppChatController::class, 'updateLeadStatus'])->name('whatsapp.chat.lead-status');
    Route::post('/whatsapp/log-manual-send', [\App\Http\Controllers\WhatsAppChatController::class, 'logManualSend'])->name('whatsapp.chat.log-manual');
    Route::post('/whatsapp/ai-draft', [\App\Http\Controllers\WhatsAppChatController::class, 'generateAiDraft'])->name('whatsapp.chat.ai-draft');
    Route::post('/whatsapp/leads/{lead}/tags', [\App\Http\Controllers\WhatsAppChatController::class, 'updateLeadTags'])->name('whatsapp.chat.lead-tags');
    Route::post('/whatsapp/reminders', [\App\Http\Controllers\WhatsAppChatController::class, 'createReminder'])->name('whatsapp.chat.reminders');

    // Finance - Kas & Pembayaran
    Route::get('/finance', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
    Route::post('/transactions', [\App\Http\Controllers\TransactionController::class, 'store'])->name('transactions.store');
    Route::delete('/transactions/{transaction}', [\App\Http\Controllers\TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::get('/finance/export', [\App\Http\Controllers\FinanceController::class, 'exportCsv'])->name('finance.export');
    Route::get('/finance/create', [\App\Http\Controllers\FinanceController::class, 'create'])->name('finance.create');
    Route::post('/finance', [\App\Http\Controllers\FinanceController::class, 'store'])->name('finance.store');

    // Finance - Pengeluaran Operasional
    Route::get('/finance/expenses', [\App\Http\Controllers\ExpenseController::class, 'index'])->name('finance.expenses.index');
    Route::post('/finance/expenses', [\App\Http\Controllers\ExpenseController::class, 'store'])->name('finance.expenses.store');
    Route::put('/finance/expenses/{expense}', [\App\Http\Controllers\ExpenseController::class, 'update'])->name('finance.expenses.update');
    Route::delete('/finance/expenses/{expense}', [\App\Http\Controllers\ExpenseController::class, 'destroy'])->name('finance.expenses.destroy');

    // Finance - Penggajian (Payroll)
    Route::get('/finance/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('finance.payroll.index');
    Route::post('/finance/payroll/generate', [\App\Http\Controllers\PayrollController::class, 'generate'])->name('finance.payroll.generate');
    Route::post('/finance/payroll/{payroll}/pay', [\App\Http\Controllers\PayrollController::class, 'pay'])->name('finance.payroll.pay');
    Route::post('/finance/payroll/pay-all', [\App\Http\Controllers\PayrollController::class, 'payAll'])->name('finance.payroll.payAll');
    Route::get('/finance/payroll/{payroll}/slip', [\App\Http\Controllers\PayrollController::class, 'slip'])->name('finance.payroll.slip');
    Route::post('/finance/salary', [\App\Http\Controllers\PayrollController::class, 'storeSalary'])->name('finance.salary.store');
    Route::put('/finance/salary/{employeeSalary}', [\App\Http\Controllers\PayrollController::class, 'updateSalary'])->name('finance.salary.update');

    // Finance - RAB (Rencana Anggaran Biaya)
    Route::get('/finance/rab', [\App\Http\Controllers\RabController::class, 'index'])->name('finance.rab.index');
    Route::post('/finance/rab', [\App\Http\Controllers\RabController::class, 'store'])->name('finance.rab.store');
    Route::put('/finance/rab/{rabItem}', [\App\Http\Controllers\RabController::class, 'update'])->name('finance.rab.update');
    Route::delete('/finance/rab/{rabItem}', [\App\Http\Controllers\RabController::class, 'destroy'])->name('finance.rab.destroy');
    Route::post('/finance/rab/{rabItem}/realize', [\App\Http\Controllers\RabController::class, 'realize'])->name('finance.rab.realize');
    Route::post('/finance/rab/duplicate', [\App\Http\Controllers\RabController::class, 'duplicate'])->name('finance.rab.duplicate');
    Route::get('/finance/rab/export', [\App\Http\Controllers\RabController::class, 'exportCsv'])->name('finance.rab.export');

    // Finance - Laporan Keuangan
    Route::get('/finance/reports', [\App\Http\Controllers\FinancialReportController::class, 'index'])->name('finance.reports.index');
    Route::get('/finance/reports/export', [\App\Http\Controllers\FinancialReportController::class, 'export'])->name('finance.reports.export');

    // Finance - Kontrak Subkontraktor
    Route::get('/finance/contracts', [\App\Http\Controllers\ContractorContractController::class, 'index'])->name('finance.contracts.index');
    Route::post('/finance/contracts', [\App\Http\Controllers\ContractorContractController::class, 'store'])->name('finance.contracts.store');
    Route::put('/finance/contracts/{contract}', [\App\Http\Controllers\ContractorContractController::class, 'update'])->name('finance.contracts.update');
    Route::delete('/finance/contracts/{contract}', [\App\Http\Controllers\ContractorContractController::class, 'destroy'])->name('finance.contracts.destroy');
    Route::post('/finance/contracts/termins/{termin}/pay', [\App\Http\Controllers\ContractorContractController::class, 'payTermin'])->name('finance.contracts.payTermin');

    // Finance - Kwitansi & Bukti Pembayaran
    Route::get('/finance/transactions/{transaction}/receipt', [\App\Http\Controllers\TransactionController::class, 'receipt'])->name('finance.transactions.receipt');


    // Users / Staff
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
    
    // Agent Monitoring
    Route::get('/agent-monitoring', [\App\Http\Controllers\AgentMonitorController::class, 'index'])->name('agent-monitoring.index');
    Route::post('/agent-monitoring/{user}/toggle', [\App\Http\Controllers\AgentMonitorController::class, 'toggleAssignment'])->name('agent-monitoring.toggle');
    Route::post('/agent-monitoring/{user}/capacity', [\App\Http\Controllers\AgentMonitorController::class, 'updateCapacity'])->name('agent-monitoring.capacity');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

    // Commissions
    Route::get('/commissions', [\App\Http\Controllers\CommissionController::class, 'index'])->name('commissions.index');
    Route::post('/commissions/{commission}/pay', [\App\Http\Controllers\CommissionController::class, 'pay'])->name('commissions.pay');

    // KPI / Performance
    Route::get('/kpi', [\App\Http\Controllers\KPIController::class, 'index'])->name('kpi.index');
    Route::post('/kpi/target', [\App\Http\Controllers\KPIController::class, 'setTarget'])->name('kpi.setTarget');

    // Marketing Campaigns
    Route::get('/campaigns', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns', [\App\Http\Controllers\CampaignController::class, 'store'])->name('campaigns.store');

    // System Settings
    Route::get('/settings', [\App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/token', [\App\Http\Controllers\SettingController::class, 'generateToken'])->name('settings.token');
    Route::get('/settings/audit-logs', [AuditLogController::class, 'index'])->name('settings.auditLogs');
    Route::post('/settings/partner-banks', [\App\Http\Controllers\PartnerBankController::class, 'store'])->name('settings.partnerBanks.store');
    Route::put('/settings/partner-banks/{partnerBank}', [\App\Http\Controllers\PartnerBankController::class, 'update'])->name('settings.partnerBanks.update');
    Route::delete('/settings/partner-banks/{partnerBank}', [\App\Http\Controllers\PartnerBankController::class, 'destroy'])->name('settings.partnerBanks.destroy');
    
    // Broker Companies
    Route::post('/settings/brokers', [\App\Http\Controllers\BrokerCompanyController::class, 'store'])->name('settings.brokers.store');
    Route::put('/settings/brokers/{broker}', [\App\Http\Controllers\BrokerCompanyController::class, 'update'])->name('settings.brokers.update');
    Route::delete('/settings/brokers/{broker}', [\App\Http\Controllers\BrokerCompanyController::class, 'destroy'])->name('settings.brokers.destroy');

    // Bank Accounts
    Route::post('/settings/bank-accounts', [\App\Http\Controllers\BankAccountController::class, 'store'])->name('settings.bankAccounts.store');
    Route::put('/settings/bank-accounts/{bankAccount}', [\App\Http\Controllers\BankAccountController::class, 'update'])->name('settings.bankAccounts.update');
    Route::delete('/settings/bank-accounts/{bankAccount}', [\App\Http\Controllers\BankAccountController::class, 'destroy'])->name('settings.bankAccounts.destroy');

    // User Guide / Manual
    Route::get('/guide', function () {
        return \Inertia\Inertia::render('Guide/Index');
    })->name('guide.index');

    // Temporary Secret Migration Route (Hapus setelah selesai)
    Route::get('/run-migration-secret', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            return 'Migration run successfully: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
        } catch (\Exception $e) {
            return 'Migration failed: ' . $e->getMessage();
        }
    });

    // Temporary Secret Log Viewer Route (Hapus setelah selesai)
    Route::get('/view-logs-secret', function () {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            return 'Logs directory does not exist.';
        }
        $files = glob($dir . '/*.log');
        if (empty($files)) {
            return 'No log files found in directory. Try visiting /test-log-secret to generate one!';
        }
        
        // Sort files by modification time (most recent first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestFile = $files[0];
        $lines = file($latestFile);
        $lastLines = array_slice($lines, -100);
        
        return 'Showing last 100 lines of latest log file (' . basename($latestFile) . '):<br><pre>' . implode("", $lastLines) . '</pre>';
    });

    // Temporary Secret Test Log Route (Hapus setelah selesai)
    Route::get('/test-log-secret', function () {
        try {
            // Test standard Laravel logging
            \Illuminate\Support\Facades\Log::info("Test Log Triggered Successfully at " . now());
            
            // Test direct file writing
            $directPath = storage_path('logs/direct_test.log');
            file_put_contents($directPath, "Direct File Write Success at " . now() . "\n", FILE_APPEND);
            
            return 'Test log written successfully! Now visit <a href="/view-logs-secret">/view-logs-secret</a> to verify.';
        } catch (\Exception $e) {
            return 'Failed to write log: ' . $e->getMessage();
        }
    });

    // Temporary Secret Cache Clear Route (Hapus setelah selesai)
    Route::get('/clear-cache-secret', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            return 'All caches cleared successfully!';
        } catch (\Exception $e) {
            return 'Failed to clear cache: ' . $e->getMessage();
        }
    });

    // Temporary Secret Env Debug Route (Hapus setelah selesai)
    Route::get('/view-env-secret', function () {
        $logsPath = storage_path('logs');
        $allFiles = is_dir($logsPath) ? scandir($logsPath) : [];
        
        return [
            'app_env' => config('app.env'),
            'log_default_channel' => config('logging.default'),
            'log_stack_channels' => config('logging.channels.stack.channels'),
            'env_log_channel' => env('LOG_CHANNEL'),
            'env_log_stack' => env('LOG_STACK'),
            'log_path' => storage_path('logs/laravel.log'),
            'log_file_exists' => file_exists(storage_path('logs/laravel.log')),
            'storage_dir_writable' => is_writable(storage_path()),
            'logs_dir_writable' => is_writable($logsPath),
            'files_in_logs_dir' => array_values(array_filter($allFiles, function($file) {
                return $file !== '.' && $file !== '..';
            })),
        ];
    });

    // Temporary Secret Token Permission Route (Hapus setelah selesai)
    Route::get('/view-token-permissions-secret', function () {
        $token = \App\Models\Setting::where('key', 'meta_page_access_token')->value('value');
        if (!$token) {
            return 'No token stored in settings.';
        }
        
        $tokenLength = strlen($token);
        $start = substr($token, 0, 15);
        $end = substr($token, -15);
        
        $response = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v19.0/me/permissions", [
            'access_token' => $token,
        ]);
        
        return [
            'length' => $tokenLength,
            'preview' => $start . '...' . $end,
            'api_response' => $response->json(),
        ];
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
