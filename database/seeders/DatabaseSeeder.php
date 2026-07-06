<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\UnitType;
use App\Models\Unit;
use App\Models\Lead;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. PERMISSIONS ──────────────────────────────
        $permissions = [
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete',
            // Projects
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
            // Inventory
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
            // Leads
            'leads.view_all', 'leads.view_own', 'leads.create', 'leads.edit', 'leads.delete', 'leads.assign',
            // Bookings
            'bookings.view', 'bookings.create', 'bookings.approve', 'bookings.cancel',
            // Payments
            'payments.view', 'payments.record', 'payments.verify',
            // Reports
            'reports.view', 'reports.export',
            // Documents
            'documents.generate', 'documents.view',
            // Settings
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── 2. ROLES ────────────────────────────────────
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

        // ── 3. DEMO USERS ───────────────────────────────
        $admin = User::create([
            'name' => 'Admin Homi',
            'email' => 'admin@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567890',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        $manager = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567891',
            'status' => 'active',
        ]);
        $manager->assignRole('project_manager');

        $salesMgr = User::create([
            'name' => 'Dewi Rahayu',
            'email' => 'dewi@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567892',
            'status' => 'active',
        ]);
        $salesMgr->assignRole('sales_manager');

        $agent1 = User::create([
            'name' => 'Andi Pratama',
            'email' => 'andi@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567893',
            'status' => 'active',
            'lead_capacity' => 25,
        ]);
        $agent1->assignRole('sales_agent');

        $agent2 = User::create([
            'name' => 'Sari Wulandari',
            'email' => 'sari@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567894',
            'status' => 'active',
            'lead_capacity' => 20,
        ]);
        $agent2->assignRole('sales_agent');

        $financeUser = User::create([
            'name' => 'Rini Finansia',
            'email' => 'rini@homi.id',
            'password' => bcrypt('password'),
            'phone' => '081234567895',
            'status' => 'active',
        ]);
        $financeUser->assignRole('finance');

        // ── 4. DEMO PROJECT ─────────────────────────────
        $project = Project::create([
            'name' => 'Citraland Grand View',
            'code' => 'CGV',
            'description' => 'Hunian mewah berkonsep smart home di kawasan premium Jakarta Selatan.',
            'location' => 'Jakarta Selatan',
            'address' => 'Jl. TB Simatupang No. 88, Cilandak',
            'status' => 'active',
            'amenities' => ['Kolam Renang', 'Gym Center', 'Jogging Track', 'Club House', 'Taman Bermain'],
        ]);

        // Assign manager & agents to project
        $manager->update(['project_id' => $project->id]);
        $agent1->update(['project_id' => $project->id]);
        $agent2->update(['project_id' => $project->id]);

        // ── 5. DEMO UNIT TYPES ──────────────────────────
        $type36 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Type 36/72',
            'code' => 'T36',
            'building_area' => 36,
            'land_area' => 72,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'floors' => 1,
            'base_price' => 850000000,
            'current_price' => 895000000,
            'specs' => ['pondasi' => 'Batu Kali', 'dinding' => 'Bata Merah', 'atap' => 'Baja Ringan'],
        ]);

        $type45 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Type 45/90',
            'code' => 'T45',
            'building_area' => 45,
            'land_area' => 90,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'floors' => 1,
            'base_price' => 1200000000,
            'current_price' => 1275000000,
            'specs' => ['pondasi' => 'Batu Kali', 'dinding' => 'Bata Merah', 'atap' => 'Genteng Beton'],
        ]);

        $type70 = UnitType::create([
            'project_id' => $project->id,
            'name' => 'Type 70/120',
            'code' => 'T70',
            'building_area' => 70,
            'land_area' => 120,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'floors' => 2,
            'base_price' => 1800000000,
            'current_price' => 1950000000,
            'specs' => ['pondasi' => 'Tiang Pancang', 'dinding' => 'Bata Ringan', 'atap' => 'Genteng Keramik'],
        ]);

        // ── 6. DEMO UNITS ───────────────────────────────
        $unitStatuses = ['available', 'available', 'available', 'booked', 'sold'];

        foreach (['A', 'B', 'C'] as $block) {
            for ($i = 1; $i <= 8; $i++) {
                $type = match (true) {
                    $i <= 3 => $type36,
                    $i <= 6 => $type45,
                    default => $type70,
                };

                $status = $unitStatuses[array_rand($unitStatuses)];

                Unit::create([
                    'project_id' => $project->id,
                    'unit_type_id' => $type->id,
                    'block' => $block,
                    'number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                    'status' => $status,
                    'facing_direction' => ['Utara', 'Selatan', 'Timur', 'Barat'][array_rand(['Utara', 'Selatan', 'Timur', 'Barat'])],
                    'final_price' => $type->current_price + ($i === 1 || $i === 8 ? 50000000 : 0), // corner premium
                    'premium_charge' => $i === 1 || $i === 8 ? 50000000 : 0,
                ]);
            }
        }

        // Update project counts
        $project->recalculateUnits();

        // ── 7. DEMO LEADS ───────────────────────────────
        $leadNames = [
            ['Rina Maharani', '081300000001', 'new'],
            ['Hendra Wijaya', '081300000002', 'contacted'],
            ['Putri Ayu', '081300000003', 'visited'],
            ['Bambang Suryadi', '081300000004', 'negotiation'],
            ['Lina Permata', '081300000005', 'booking'],
            ['Tono Setiawan', '081300000006', 'new'],
            ['Maya Sari', '081300000007', 'contacted'],
            ['Joko Purnomo', '081300000008', 'lost'],
        ];

        foreach ($leadNames as [$name, $phone, $status]) {
            Lead::create([
                'project_id' => $project->id,
                'assigned_to' => [$agent1->id, $agent2->id][array_rand([$agent1->id, $agent2->id])],
                'name' => $name,
                'phone' => $phone,
                'source' => ['facebook', 'instagram', 'walk_in', 'referral', 'website'][array_rand(['facebook', 'instagram', 'walk_in', 'referral', 'website'])],
                'status' => $status,
                'score' => match ($status) {
                    'new' => 5,
                    'contacted' => 20,
                    'visited' => 45,
                    'negotiation' => 65,
                    'booking' => 85,
                    'lost' => 0,
                    default => 10,
                },
                'lost_reason' => $status === 'lost' ? 'Budget tidak mencukupi' : null,
            ]);
        }
    }
}
