<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GeneralLedger;
use App\Models\EmployeeSalary;
use App\Models\Payroll;
use App\Models\RabItem;
use App\Models\RabRealization;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\BankAccount;
use App\Models\ContractorContract;
use App\Models\ContractorTermin;
use App\Models\Transaction;
use App\Models\PaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Project $project;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        $this->project = Project::create([
            'name' => 'Homi Residence Test',
            'code' => 'HRT',
            'location' => 'Test Location',
            'status' => 'active',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Material Bangunan',
            'code' => 'MAT',
            'icon' => '🧱',
            'color' => '#f59e0b',
        ]);
    }

    public function test_expense_crud_and_general_ledger_auto_recording()
    {
        // 1. Create Bank Account first
        $bank = BankAccount::create([
            'name' => 'BCA Operasional',
            'bank_name' => 'BCA',
            'account_number' => '12345678',
            'account_holder' => 'Homi Developer',
            'initial_balance' => 10000000,
            'current_balance' => 10000000,
        ]);

        // 2. Create Expense linked to Bank
        $response = $this->post(route('finance.expenses.store'), [
            'project_id' => $this->project->id,
            'expense_category_id' => $this->category->id,
            'description' => 'Pembelian semen 100 sak',
            'amount' => 5000000,
            'expense_date' => '2026-07-06',
            'payment_method' => 'transfer',
            'vendor_name' => 'Toko Bangunan Sejahtera',
            'bank_account_id' => $bank->id,
            'ppn_amount' => 550000,
            'pph_amount' => 100000,
        ]);

        $response->assertStatus(302);
        
        $this->assertDatabaseHas('expenses', [
            'description' => 'Pembelian semen 100 sak',
            'amount' => 5000000,
            'bank_account_id' => $bank->id,
            'ppn_amount' => 550000,
            'pph_amount' => 100000,
        ]);

        $expense = Expense::first();

        // Check if General Ledger entry was automatically created
        $this->assertDatabaseHas('general_ledger', [
            'type' => 'expense',
            'category' => 'operational_expense',
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'project_id' => $this->project->id,
            'bank_account_id' => $bank->id,
            'debit' => 5000000,
        ]);

        // Bank balance should be updated: initial (10,000,000) - expense (5,000,000) = 5,000,000
        $this->assertEquals(5000000, $bank->fresh()->current_balance);

        // Delete Expense
        $response = $this->delete(route('finance.expenses.destroy', $expense->id));
        $response->assertStatus(302);

        // Bank balance should restore to initial: 10,000,000
        $this->assertEquals(10000000, $bank->fresh()->current_balance);
    }

    public function test_bank_account_crud()
    {
        $response = $this->post(route('settings.bankAccounts.store'), [
            'name' => 'Mandiri Utama',
            'bank_name' => 'Mandiri',
            'account_number' => '987654321',
            'account_holder' => 'Developer Homi',
            'initial_balance' => 20000000,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Mandiri Utama',
            'initial_balance' => 20000000,
            'current_balance' => 20000000,
        ]);

        $bank = BankAccount::first();

        $response = $this->put(route('settings.bankAccounts.update', $bank->id), [
            'name' => 'Mandiri Operasional',
            'bank_name' => 'Mandiri',
            'account_number' => '987654321',
            'account_holder' => 'Developer Homi',
            'initial_balance' => 25000000,
        ]);

        $response->assertStatus(302);
        $this->assertEquals('Mandiri Operasional', $bank->fresh()->name);
        $this->assertEquals(25000000, $bank->fresh()->current_balance);

        $response = $this->delete(route('settings.bankAccounts.destroy', $bank->id));
        $response->assertStatus(302);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $bank->id]);
    }

    public function test_contractor_contracts_and_termin_payment()
    {
        // 1. Create Rab Item first
        $rabItem = RabItem::create([
            'project_id' => $this->project->id,
            'category' => 'struktur',
            'description' => 'Pekerjaan Sloof Beton',
            'unit' => 'm3',
            'volume' => 20,
            'unit_price' => 500000,
            'total_price' => 10000000,
        ]);

        // 2. Create Bank Account
        $bank = BankAccount::create([
            'name' => 'BCA Homi',
            'initial_balance' => 50000000,
            'current_balance' => 50000000,
        ]);

        // 3. Create Contractor Contract and Termins
        $response = $this->post(route('finance.contracts.store'), [
            'project_id' => $this->project->id,
            'contractor_name' => 'CV Mandiri Perkasa',
            'contract_number' => '001/SPK/HRT/2026',
            'description' => 'Pembuatan Sloof & Kolom Tipe 36',
            'total_amount' => 10000000,
            'termins' => [
                [
                    'label' => 'Termin 1 / DP 30%',
                    'percentage' => 30.00,
                    'amount' => 3000000,
                    'due_date' => '2026-07-10',
                    'rab_item_id' => $rabItem->id,
                ],
                [
                    'label' => 'Termin 2 / Lunas 70%',
                    'percentage' => 70.00,
                    'amount' => 7000000,
                    'due_date' => '2026-08-10',
                    'rab_item_id' => $rabItem->id,
                ]
            ]
        ]);

        $response->assertStatus(302);
        
        $this->assertDatabaseHas('contractor_contracts', [
            'contract_number' => '001/SPK/HRT/2026',
            'total_amount' => 10000000,
        ]);

        $this->assertDatabaseHas('contractor_termins', [
            'label' => 'Termin 1 / DP 30%',
            'amount' => 3000000,
            'status' => 'pending',
        ]);

        $termin1 = ContractorTermin::first();

        // 4. Pay Termin 1
        $response = $this->post(route('finance.contracts.payTermin', $termin1->id), [
            'bank_account_id' => $bank->id,
            'paid_date' => '2026-07-06',
            'notes' => 'Pembayaran termin pertama DP',
        ]);

        $response->assertStatus(302);

        // Check if Termin is marked as Paid
        $this->assertEquals('paid', $termin1->fresh()->status);
        $this->assertNotNull($termin1->fresh()->expense_id);

        // Bank balance should decrease by 3,000,000 -> 47,000,000
        $this->assertEquals(47000000, $bank->fresh()->current_balance);

        // Associated Expense must be created
        $expense = Expense::find($termin1->fresh()->expense_id);
        $this->assertNotNull($expense);
        $this->assertEquals(3000000, $expense->amount);

        // RAB realization should record the payment
        $this->assertDatabaseHas('rab_realizations', [
            'rab_item_id' => $rabItem->id,
            'expense_id' => $expense->id,
            'amount' => 3000000,
        ]);

        // General Ledger should have recorded the expense
        $this->assertDatabaseHas('general_ledger', [
            'type' => 'expense',
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'debit' => 3000000,
            'bank_account_id' => $bank->id,
        ]);
    }

    public function test_customer_receipt_printing()
    {
        // 1. Setup required data for booking transaction
        $lead = Lead::create([
            'name' => 'Ahmad Roni',
            'phone' => '0812345678',
            'status' => 'new',
        ]);

        $unitType = UnitType::create([
            'project_id' => $this->project->id,
            'name' => 'Tipe 36',
            'price' => 300000000,
        ]);

        $unit = Unit::create([
            'project_id' => $this->project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '01',
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'project_id' => $this->project->id,
            'booking_number' => 'B-001',
            'spk_number' => 'SPK-001',
            'booking_amount' => 5000000,
            'status' => 'approved',
            'kpr_status' => 'none',
            'booked_by' => $this->admin->id,
            'booking_date' => '2026-07-06',
        ]);

        $schedule = PaymentSchedule::create([
            'booking_id' => $booking->id,
            'label' => 'Booking Fee',
            'amount' => 5000000,
            'due_date' => '2026-07-06',
            'status' => 'upcoming',
            'installment_number' => 1,
        ]);

        $bank = BankAccount::create([
            'name' => 'BCA Mandiri',
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        // 2. Create Transaction
        $tx = Transaction::create([
            'booking_id' => $booking->id,
            'payment_schedule_id' => $schedule->id,
            'amount' => 5000000,
            'payment_method' => 'transfer',
            'bank_account_id' => $bank->id,
            'notes' => 'Pembayaran booking fee Ahmad Roni',
            'recorded_by' => $this->admin->id,
        ]);

        // Check that Bank Account balance is updated to 5,000,000
        $this->assertEquals(5000000, $bank->fresh()->current_balance);

        // 3. Request print receipt view
        $response = $this->get(route('finance.transactions.receipt', $tx->id));
        $response->assertStatus(200);
        
        // Assert we see spelled out text for 5,000,000: "Lima Juta"
        $response->assertSee('Lima Juta');
    }
}
