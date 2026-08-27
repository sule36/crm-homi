<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\UnitType;
use App\Models\Unit;
use App\Models\Lead;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\RabItem;
use App\Models\ContractorContract;
use App\Models\BrokerCompany;
use App\Models\BankAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('SEEDER DIABORT DI ENVIRONMENT PRODUCTION AGAR DATA TIDAK TERHAPUS!');
            return;
        }

        // ── 0. CLEAN OLD DUMMY DATA ─────────────────────
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Booking::truncate();
        Lead::truncate();
        Unit::truncate();
        UnitType::truncate();
        Project::truncate();
        Transaction::truncate();
        Expense::truncate();
        RabItem::truncate();
        ContractorContract::truncate();
        User::truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('role_has_permissions')->truncate();
        Permission::truncate();
        Role::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Seed Expense Categories
        $this->call(ExpenseCategorySeeder::class);

        // ── 1. PERMISSIONS ──────────────────────────────
        $permissions = [
            // Users & Staff
            'users.view', 'users.create', 'users.edit', 'users.delete',
            // Projects
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
            // Inventory
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
            // Leads
            'leads.view_all', 'leads.view_own', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign',
            // Bookings
            'bookings.view', 'bookings.create', 'bookings.approve', 'bookings.cancel',
            // Payments & Finance
            'payments.view', 'payments.record', 'payments.verify',
            // Reports
            'reports.view', 'reports.export',
            // Documents & SPK
            'documents.generate', 'documents.view',
            // Settings
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── 2. ROLES (RBAC CONFIGURATION) ───────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $projectManager = Role::firstOrCreate(['name' => 'project_manager']);
        $projectManager->givePermissionTo([
            'projects.view', 'projects.create', 'projects.edit',
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
            'leads.view_all', 'leads.create', 'leads.edit', 'leads.assign',
            'bookings.view', 'bookings.create', 'bookings.approve', 'bookings.cancel',
            'payments.view',
            'reports.view', 'reports.export',
            'documents.generate', 'documents.view',
        ]);

        $salesManager = Role::firstOrCreate(['name' => 'sales_manager']);
        $salesManager->givePermissionTo([
            'projects.view',
            'inventory.view',
            'leads.view_all', 'leads.create', 'leads.edit', 'leads.assign',
            'bookings.view', 'bookings.create', 'bookings.approve',
            'reports.view',
            'documents.generate', 'documents.view',
        ]);

        $salesAgent = Role::firstOrCreate(['name' => 'sales_agent']);
        $salesAgent->givePermissionTo([
            'projects.view',
            'inventory.view',
            'leads.view_own', 'leads.create', 'leads.edit',
            'bookings.view', 'bookings.create',
            'documents.generate', 'documents.view',
        ]);

        $finance = Role::firstOrCreate(['name' => 'finance']);
        $finance->givePermissionTo([
            'projects.view',
            'inventory.view',
            'bookings.view',
            'payments.view', 'payments.record', 'payments.verify',
            'reports.view', 'reports.export',
            'documents.view',
        ]);

        $broker = Role::firstOrCreate(['name' => 'broker']);
        $broker->givePermissionTo([
            'inventory.view',
            'leads.view_own', 'leads.create',
            'bookings.view', 'bookings.create',
        ]);

        // ── 3. OFFICIAL STAFF ACCOUNTS ──────────────────
        $admin = User::create([
            'name' => 'Super Admin Homi',
            'email' => 'admin@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567890',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        $manager = User::create([
            'name' => 'Project Manager Alonica',
            'email' => 'manager@alonica.id',
            'password' => bcrypt('password'),
            'phone' => '081234567891',
            'status' => 'active',
        ]);
        $manager->assignRole('project_manager');

        $salesMgr = User::create([
            'name' => 'Sales Manager Alonica',
            'email' => 'sales.manager@alonica.id',
            'password' => bcrypt('password'),
            'phone' => '081234567892',
            'status' => 'active',
        ]);
        $salesMgr->assignRole('sales_manager');

        $agent1 = User::create([
            'name' => 'Sales Representative 1',
            'email' => 'agent1@alonica.id',
            'password' => bcrypt('password'),
            'phone' => '081234567893',
            'status' => 'active',
            'lead_capacity' => 30,
        ]);
        $agent1->assignRole('sales_agent');

        $agent2 = User::create([
            'name' => 'Sales Representative 2',
            'email' => 'agent2@alonica.id',
            'password' => bcrypt('password'),
            'phone' => '081234567894',
            'status' => 'active',
            'lead_capacity' => 30,
        ]);
        $agent2->assignRole('sales_agent');

        $financeUser = User::create([
            'name' => 'Finance & Accounting',
            'email' => 'finance@alonica.id',
            'password' => bcrypt('password'),
            'phone' => '081234567895',
            'status' => 'active',
        ]);
        $financeUser->assignRole('finance');

        // ── 4. REAL PROJECT: ALONICA HILLS ─────────────
        $project = Project::create([
            'name' => 'Alonica Hills',
            'code' => 'ALN',
            'description' => 'Residential cluster di kawasan strategis Cilandak Timur, Jakarta Selatan. Dirancang dengan konsep Modern Living & Timeless Design berkualitas tinggi.',
            'location' => 'Cilandak Timur, Jakarta Selatan',
            'address' => 'Jl. Cilandak KKO / Jl. Margasatwa, Cilandak Timur, Pasar Minggu, Jakarta Selatan',
            'status' => 'active',
            'amenities' => [
                'Exclusive Community',
                'Natural Surroundings',
                'Wide ROW Road 9 Meters',
                'Secure Gated Access (One Gate)',
                'Modern Living Timeless Design',
                'Underground Cable Network',
                'Jogging Track',
                'Public Lighting & Closed Drainage',
                'Smart Home System'
            ],
        ]);

        // Assign users to Alonica Hills project
        $manager->update(['project_id' => $project->id]);
        $salesMgr->update(['project_id' => $project->id]);
        $agent1->update(['project_id' => $project->id]);
        $agent2->update(['project_id' => $project->id]);

        // ── 5. REAL ALONICA HILLS UNIT TYPES ────────────
        $typeStandard = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Standard (LB 198 / LT 105)',
            'code' => 'STD-105',
            'building_area' => 198,
            'land_area' => 105,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 3840921600,
            'current_price' => 3990957600,
            'specs' => [
                'pondasi' => 'Bored Pile',
                'dinding' => 'Bata Merah & Cat Weathershield',
                'atap' => 'Struktur Rangka Baja Ringan & Kaca Tempered Laminated',
                'pintu_jendela' => 'Kusen Alumunium',
                'lantai' => 'Homogenous Tile / Ceramic Tile (Kamar Mandi)',
                'instalasi_air' => 'Sumur Bor + Pompa Air + Tangki Air',
                'septictank' => 'Biofill',
                'listrik' => '4.400 Watt',
                'fitur_tambahan' => 'Rooftop Cabin, 2 Carports, Smart Home'
            ],
        ]);

        $typeHookFront = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Hook Front (LB 198 / LT 105)',
            'code' => 'HOOK-105',
            'building_area' => 198,
            'land_area' => 105,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 3990957600,
            'current_price' => 4140993600,
            'specs' => $typeStandard->specs,
        ]);

        $typeLt136 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Large Garden (LB 198 / LT 136)',
            'code' => 'GRD-136',
            'building_area' => 198,
            'land_area' => 136,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 4691867600,
            'current_price' => 4868253600,
            'specs' => $typeStandard->specs,
        ]);

        $typeLt141 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Large Garden (LB 198 / LT 141)',
            'code' => 'GRD-141',
            'building_area' => 198,
            'land_area' => 141,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 4804917600,
            'current_price' => 4985553600,
            'specs' => $typeStandard->specs,
        ]);

        $typeLt147 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Large Corner (LB 198 / LT 147)',
            'code' => 'CNR-147',
            'building_area' => 198,
            'land_area' => 147,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 4940577600,
            'current_price' => 5126313600,
            'specs' => $typeStandard->specs,
        ]);

        $typeLt170 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Premium Corner (LB 198 / LT 170)',
            'code' => 'CNR-170',
            'building_area' => 198,
            'land_area' => 170,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 5460607600,
            'current_price' => 5665893600,
            'specs' => $typeStandard->specs,
        ]);

        $typeLt253 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Alonica Grand Villa Corner (LB 198 / LT 253)',
            'code' => 'VIL-253',
            'building_area' => 198,
            'land_area' => 253,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'floors' => 3,
            'base_price' => 7337237600,
            'current_price' => 7613073600,
            'specs' => $typeStandard->specs,
        ]);

        // ── 6. SEED ALL 37 REAL ALONICA HILLS UNITS ──────
        // Price list matrix from PT. Serangkai Roden Development
        $unitInventory = [
            // BLOK A
            ['block' => 'A', 'number' => '1', 'type' => $typeHookFront, 'status' => 'available', 'price' => 4140993600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '2', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '3', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '5', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '6', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '7', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '8', 'type' => $typeStandard,  'status' => 'hold',      'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '9', 'type' => $typeStandard,  'status' => 'hold',      'price' => 3990957600, 'facing' => 'Utara'],
            ['block' => 'A', 'number' => '10', 'type' => $typeLt141,    'status' => 'hold',      'price' => 4985553600, 'facing' => 'Utara'],

            // BLOK B
            ['block' => 'B', 'number' => '1', 'type' => $typeHookFront, 'status' => 'sold',      'price' => 4140993600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '2', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '3', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '5', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '6', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '7', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '8', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '9', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '10', 'type' => $typeStandard, 'status' => 'available', 'price' => 3990957600, 'facing' => 'Selatan'],
            ['block' => 'B', 'number' => '11', 'type' => $typeLt136,    'status' => 'available', 'price' => 4868253600, 'facing' => 'Selatan'],

            // BLOK C
            ['block' => 'C', 'number' => '1', 'type' => $typeHookFront, 'status' => 'available', 'price' => 4140993600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '2', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '3', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '5', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '6', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '7', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '8', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '9', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Timur'],
            ['block' => 'C', 'number' => '10', 'type' => $typeLt253,    'status' => 'available', 'price' => 7613073600, 'facing' => 'Timur'],

            // BLOK D
            ['block' => 'D', 'number' => '1', 'type' => $typeLt147,    'status' => 'available', 'price' => 5126313600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '2', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '3', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '5', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '6', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '7', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '8', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '9', 'type' => $typeStandard,  'status' => 'available', 'price' => 3990957600, 'facing' => 'Barat'],
            ['block' => 'D', 'number' => '10', 'type' => $typeLt170,    'status' => 'available', 'price' => 5665893600, 'facing' => 'Barat'],
        ];

        foreach ($unitInventory as $uData) {
            Unit::create([
                'project_id' => $project->id,
                'unit_type_id' => $uData['type']->id,
                'block' => $uData['block'],
                'number' => $uData['number'],
                'status' => $uData['status'],
                'facing_direction' => $uData['facing'],
                'final_price' => $uData['price'],
                'premium_charge' => 0,
            ]);
        }

        // Recalculate project totals
        $project->recalculateUnits();
    }
}
