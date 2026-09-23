<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Project;
use App\Models\Unit;

return new class extends Migration
{
    public function up(): void
    {
        $project = Project::where('name', 'like', '%Alonica%')->first();
        if (!$project) return;

        // Ensure siteplan image is set to the reliable public asset
        $project->update([
            'siteplan_image' => 'projects/masterplans/alonica_siteplan.jpg',
            'master_plan_image' => 'projects/masterplans/alonica_siteplan.jpg',
        ]);

        // Precision pixel-calibrated coordinates based on 984x1024 masterplan image:
        // Columns:
        // D (X: 606-678, w: 72px): dot placed on left (facing road) X=622 (63.21%)
        // C (X: 678-752, w: 74px): dot placed on right (facing road) X=736 (74.80%)
        // B (X: 778-845, w: 67px): dot placed on left (facing road) X=791.5 (80.44%), matching B1 circle
        // A (X: 845-914, w: 69px): dot placed on right (facing road) X=899.5 (91.41%), matching A1, A2, A10 circles
        //
        // Rows:
        // Row 0 (B1 only): Y: 360-392 (dot Y=377.0 -> 36.82%)
        // Row 1 (A1, B2, C1, D1): Y: 393-425 (dot Y=408.0 -> 39.84%)
        // Row 2 (A2, B3, C2, D2): Y: 425-457 (dot Y=441.0 -> 43.07%)
        // Row 3 (A3, B5, C3, D3): Y: 457-489 (dot Y=473.5 -> 46.24%)
        // Row 4 (A5, B6, C5, D5): Y: 489-522 (dot Y=506.0 -> 49.41%)
        // Row 5 (A6, B7, C6, D6): Y: 522-555 (dot Y=539.0 -> 52.64%)
        // Row 6 (A7, B8, C7, D7): Y: 555-587 (dot Y=571.5 -> 55.81%)
        // Row 7 (A8, B9, C8, D8): Y: 587-620 (dot Y=604.0 -> 58.98%)
        // Row 8 (A9, B10, C9, D9): Y: 620-653 (dot Y=637.0 -> 62.21%)
        // Row 9 (A10, B11, C10, D10): Y: 653-686 (dot Y=669.5 -> 65.38%)

        $coords = [
            // BLOK D (10 units, skips 4)
            'D1'  => ['x' => 61.59, 'y' => 38.38, 'w' => 7.32, 'h' => 3.13, 'dot_x' => 63.21, 'dot_y' => 39.84],
            'D2'  => ['x' => 61.59, 'y' => 41.50, 'w' => 7.32, 'h' => 3.13, 'dot_x' => 63.21, 'dot_y' => 43.07],
            'D3'  => ['x' => 61.59, 'y' => 44.63, 'w' => 7.32, 'h' => 3.13, 'dot_x' => 63.21, 'dot_y' => 46.24],
            'D5'  => ['x' => 61.59, 'y' => 47.75, 'w' => 7.32, 'h' => 3.22, 'dot_x' => 63.21, 'dot_y' => 49.41],
            'D6'  => ['x' => 61.59, 'y' => 50.98, 'w' => 7.32, 'h' => 3.22, 'dot_x' => 63.21, 'dot_y' => 52.64],
            'D7'  => ['x' => 61.59, 'y' => 54.20, 'w' => 7.32, 'h' => 3.13, 'dot_x' => 63.21, 'dot_y' => 55.81],
            'D8'  => ['x' => 61.59, 'y' => 57.32, 'w' => 7.32, 'h' => 3.22, 'dot_x' => 63.21, 'dot_y' => 58.98],
            'D9'  => ['x' => 61.59, 'y' => 60.55, 'w' => 7.32, 'h' => 3.22, 'dot_x' => 63.21, 'dot_y' => 62.21],
            'D10' => ['x' => 61.59, 'y' => 63.77, 'w' => 7.32, 'h' => 3.22, 'dot_x' => 63.21, 'dot_y' => 65.38],

            // BLOK C (10 units, skips 4)
            'C1'  => ['x' => 68.90, 'y' => 38.38, 'w' => 7.52, 'h' => 3.13, 'dot_x' => 74.80, 'dot_y' => 39.84],
            'C2'  => ['x' => 68.90, 'y' => 41.50, 'w' => 7.52, 'h' => 3.13, 'dot_x' => 74.80, 'dot_y' => 43.07],
            'C3'  => ['x' => 68.90, 'y' => 44.63, 'w' => 7.52, 'h' => 3.13, 'dot_x' => 74.80, 'dot_y' => 46.24],
            'C5'  => ['x' => 68.90, 'y' => 47.75, 'w' => 7.52, 'h' => 3.22, 'dot_x' => 74.80, 'dot_y' => 49.41],
            'C6'  => ['x' => 68.90, 'y' => 50.98, 'w' => 7.52, 'h' => 3.22, 'dot_x' => 74.80, 'dot_y' => 52.64],
            'C7'  => ['x' => 68.90, 'y' => 54.20, 'w' => 7.52, 'h' => 3.13, 'dot_x' => 74.80, 'dot_y' => 55.81],
            'C8'  => ['x' => 68.90, 'y' => 57.32, 'w' => 7.52, 'h' => 3.22, 'dot_x' => 74.80, 'dot_y' => 58.98],
            'C9'  => ['x' => 68.90, 'y' => 60.55, 'w' => 7.52, 'h' => 3.22, 'dot_x' => 74.80, 'dot_y' => 62.21],
            'C10' => ['x' => 68.90, 'y' => 63.77, 'w' => 7.52, 'h' => 3.22, 'dot_x' => 74.80, 'dot_y' => 65.38],

            // BLOK B (11 units, skips 4)
            'B1'  => ['x' => 79.07, 'y' => 35.16, 'w' => 6.81, 'h' => 3.13, 'dot_x' => 80.44, 'dot_y' => 36.82],
            'B2'  => ['x' => 79.07, 'y' => 38.38, 'w' => 6.81, 'h' => 3.13, 'dot_x' => 80.44, 'dot_y' => 39.84],
            'B3'  => ['x' => 79.07, 'y' => 41.50, 'w' => 6.81, 'h' => 3.13, 'dot_x' => 80.44, 'dot_y' => 43.07],
            'B5'  => ['x' => 79.07, 'y' => 44.63, 'w' => 6.81, 'h' => 3.13, 'dot_x' => 80.44, 'dot_y' => 46.24],
            'B6'  => ['x' => 79.07, 'y' => 47.75, 'w' => 6.81, 'h' => 3.22, 'dot_x' => 80.44, 'dot_y' => 49.41],
            'B7'  => ['x' => 79.07, 'y' => 50.98, 'w' => 6.81, 'h' => 3.22, 'dot_x' => 80.44, 'dot_y' => 52.64],
            'B8'  => ['x' => 79.07, 'y' => 54.20, 'w' => 6.81, 'h' => 3.13, 'dot_x' => 80.44, 'dot_y' => 55.81],
            'B9'  => ['x' => 79.07, 'y' => 57.32, 'w' => 6.81, 'h' => 3.22, 'dot_x' => 80.44, 'dot_y' => 58.98],
            'B10' => ['x' => 79.07, 'y' => 60.55, 'w' => 6.81, 'h' => 3.22, 'dot_x' => 80.44, 'dot_y' => 62.21],
            'B11' => ['x' => 79.07, 'y' => 63.77, 'w' => 6.81, 'h' => 3.22, 'dot_x' => 80.44, 'dot_y' => 65.38],

            // BLOK A (10 units, skips 4)
            'A1'  => ['x' => 85.87, 'y' => 38.38, 'w' => 7.01, 'h' => 3.13, 'dot_x' => 91.41, 'dot_y' => 39.84],
            'A2'  => ['x' => 85.87, 'y' => 41.50, 'w' => 7.01, 'h' => 3.13, 'dot_x' => 91.41, 'dot_y' => 43.07],
            'A3'  => ['x' => 85.87, 'y' => 44.63, 'w' => 7.01, 'h' => 3.13, 'dot_x' => 91.41, 'dot_y' => 46.24],
            'A5'  => ['x' => 85.87, 'y' => 47.75, 'w' => 7.01, 'h' => 3.22, 'dot_x' => 91.41, 'dot_y' => 49.41],
            'A6'  => ['x' => 85.87, 'y' => 50.98, 'w' => 7.01, 'h' => 3.22, 'dot_x' => 91.41, 'dot_y' => 52.64],
            'A7'  => ['x' => 85.87, 'y' => 54.20, 'w' => 7.01, 'h' => 3.13, 'dot_x' => 91.41, 'dot_y' => 55.81],
            'A8'  => ['x' => 85.87, 'y' => 57.32, 'w' => 7.01, 'h' => 3.22, 'dot_x' => 91.41, 'dot_y' => 58.98],
            'A9'  => ['x' => 85.87, 'y' => 60.55, 'w' => 7.01, 'h' => 3.22, 'dot_x' => 91.41, 'dot_y' => 62.21],
            'A10' => ['x' => 85.87, 'y' => 63.77, 'w' => 7.01, 'h' => 3.22, 'dot_x' => 91.41, 'dot_y' => 65.38],
        ];

        foreach ($coords as $unitKey => $pos) {
            $block = substr($unitKey, 0, 1);
            $number = substr($unitKey, 1);

            Unit::where('project_id', $project->id)
                ->where('block', $block)
                ->where('number', $number)
                ->update(['siteplan_coordinates' => $pos]);
        }
    }

    public function down(): void
    {
    }
};
