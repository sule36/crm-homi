<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Material Bangunan', 'code' => 'MAT', 'icon' => '🧱', 'color' => '#f59e0b', 'description' => 'Semen, besi, pasir, batu bata, kayu, dll', 'sort_order' => 1],
            ['name' => 'Upah Tukang / Mandor', 'code' => 'UPH', 'icon' => '👷', 'color' => '#3b82f6', 'description' => 'Biaya tenaga kerja harian/borongan', 'sort_order' => 2],
            ['name' => 'Perizinan & Legal', 'code' => 'LGL', 'icon' => '📜', 'color' => '#8b5cf6', 'description' => 'IMB, sertifikat, notaris, BPHTB, AJB', 'sort_order' => 3],
            ['name' => 'Marketing & Promosi', 'code' => 'MKT', 'icon' => '📢', 'color' => '#ec4899', 'description' => 'Iklan digital, brosur, spanduk, event', 'sort_order' => 4],
            ['name' => 'Operasional Kantor', 'code' => 'OPR', 'icon' => '🏢', 'color' => '#06b6d4', 'description' => 'ATK, sewa kantor, perlengkapan', 'sort_order' => 5],
            ['name' => 'Transportasi', 'code' => 'TRP', 'icon' => '🚗', 'color' => '#84cc16', 'description' => 'BBM, tol, parkir, sewa kendaraan', 'sort_order' => 6],
            ['name' => 'Utilitas', 'code' => 'UTL', 'icon' => '⚡', 'color' => '#f97316', 'description' => 'Listrik, air, internet, telepon', 'sort_order' => 7],
            ['name' => 'Alat & Mesin', 'code' => 'ALT', 'icon' => '🔧', 'color' => '#64748b', 'description' => 'Sewa/beli alat berat, perkakas', 'sort_order' => 8],
            ['name' => 'Subkontraktor', 'code' => 'SUB', 'icon' => '🤝', 'color' => '#14b8a6', 'description' => 'Pekerjaan yang disubkontrakkan', 'sort_order' => 9],
            ['name' => 'Lain-lain', 'code' => 'LLL', 'icon' => '📦', 'color' => '#a1a1aa', 'description' => 'Pengeluaran yang tidak termasuk kategori di atas', 'sort_order' => 10],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::updateOrCreate(
                ['code' => $cat['code']],
                $cat
            );
        }
    }
}
