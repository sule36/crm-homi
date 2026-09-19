<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Unit;
use App\Models\UnitType;

return new class extends Migration
{
    public function up(): void
    {
        // Update Unit Types base & current price
        $types = [
            'STD-105'  => 3840921600,
            'HOOK-105' => 3990957600,
            'CNR-131'  => 4621879523,
            'GRD-136'  => 4691867600,
            'GRD-141'  => 4804917600,
            'CNR-170'  => 5460607600,
            'VIL-215'  => 6499699523,
            'VIL-253'  => 7337237600,
            'HK-170'   => 5862666059,
        ];

        foreach ($types as $code => $price) {
            UnitType::where('code', $code)->update([
                'base_price' => $price,
                'current_price' => $price,
            ]);
        }

        // Update Unit prices safely without touching any other tables
        $unitPrices = [
            // BLOK A
            ['A', '1', 3990957600],
            ['A', '2', 3840921600],
            ['A', '3', 3840921600],
            ['A', '5', 3840921600],
            ['A', '6', 3840921600],
            ['A', '7', 3840921600],
            ['A', '8', 3840921600],
            ['A', '9', 3840921600],
            ['A', '10', 4804917600],

            // BLOK B
            ['B', '1', 5862666059],
            ['B', '2', 3840921600],
            ['B', '3', 3840921600],
            ['B', '5', 3840921600],
            ['B', '6', 3840921600],
            ['B', '7', 3840921600],
            ['B', '8', 3840921600],
            ['B', '9', 3840921600],
            ['B', '10', 3840921600],
            ['B', '11', 4691867600],

            // BLOK C
            ['C', '1', 6499699523],
            ['C', '2', 3840921600],
            ['C', '3', 3840921600],
            ['C', '5', 3840921600],
            ['C', '6', 3840921600],
            ['C', '7', 3840921600],
            ['C', '8', 3840921600],
            ['C', '9', 3840921600],
            ['C', '10', 7337237600],

            // BLOK D
            ['D', '1', 4621879523],
            ['D', '2', 3840921600],
            ['D', '3', 3840921600],
            ['D', '5', 3840921600],
            ['D', '6', 3840921600],
            ['D', '7', 3840921600],
            ['D', '8', 3840921600],
            ['D', '9', 3840921600],
            ['D', '10', 5460607600],
        ];

        foreach ($unitPrices as $item) {
            Unit::where('block', $item[0])
                ->where('number', (string)$item[1])
                ->update(['final_price' => $item[2]]);
        }
    }

    public function down(): void
    {
    }
};
