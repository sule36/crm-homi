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

        // Ensure siteplan image is set
        $project->update([
            'siteplan_image' => 'projects/masterplans/alonica_siteplan.jpg',
            'master_plan_image' => 'projects/masterplans/alonica_siteplan.jpg',
        ]);

        // Coordinates mapping matching the graphic siteplan image columns (Blok D, C, B, A)
        // Image layout: D1-D10 (left column), C1-C10 (middle-left), B1-B11 (middle-right), A1-A10 (right column)
        $coords = [
            // BLOK D (leftmost column)
            'D1'  => ['x' => 61.5, 'y' => 38.5, 'w' => 5.2, 'h' => 4.0],
            'D2'  => ['x' => 61.5, 'y' => 42.5, 'w' => 5.2, 'h' => 4.0],
            'D3'  => ['x' => 61.5, 'y' => 46.5, 'w' => 5.2, 'h' => 4.0],
            'D5'  => ['x' => 61.5, 'y' => 50.5, 'w' => 5.2, 'h' => 4.0],
            'D6'  => ['x' => 61.5, 'y' => 54.5, 'w' => 5.2, 'h' => 4.0],
            'D7'  => ['x' => 61.5, 'y' => 58.5, 'w' => 5.2, 'h' => 4.0],
            'D8'  => ['x' => 61.5, 'y' => 62.5, 'w' => 5.2, 'h' => 4.0],
            'D9'  => ['x' => 61.5, 'y' => 66.5, 'w' => 5.2, 'h' => 4.0],
            'D10' => ['x' => 61.5, 'y' => 70.5, 'w' => 5.2, 'h' => 4.5],

            // BLOK C
            'C1'  => ['x' => 67.2, 'y' => 38.5, 'w' => 5.2, 'h' => 4.0],
            'C2'  => ['x' => 67.2, 'y' => 42.5, 'w' => 5.2, 'h' => 4.0],
            'C3'  => ['x' => 67.2, 'y' => 46.5, 'w' => 5.2, 'h' => 4.0],
            'C5'  => ['x' => 67.2, 'y' => 50.5, 'w' => 5.2, 'h' => 4.0],
            'C6'  => ['x' => 67.2, 'y' => 54.5, 'w' => 5.2, 'h' => 4.0],
            'C7'  => ['x' => 67.2, 'y' => 58.5, 'w' => 5.2, 'h' => 4.0],
            'C8'  => ['x' => 67.2, 'y' => 62.5, 'w' => 5.2, 'h' => 4.0],
            'C9'  => ['x' => 67.2, 'y' => 66.5, 'w' => 5.2, 'h' => 4.0],
            'C10' => ['x' => 67.2, 'y' => 70.5, 'w' => 5.2, 'h' => 4.5],

            // BLOK B
            'B1'  => ['x' => 78.8, 'y' => 33.5, 'w' => 5.2, 'h' => 4.0],
            'B2'  => ['x' => 78.8, 'y' => 38.5, 'w' => 5.2, 'h' => 4.0],
            'B3'  => ['x' => 78.8, 'y' => 42.5, 'w' => 5.2, 'h' => 4.0],
            'B5'  => ['x' => 78.8, 'y' => 46.5, 'w' => 5.2, 'h' => 4.0],
            'B6'  => ['x' => 78.8, 'y' => 50.5, 'w' => 5.2, 'h' => 4.0],
            'B7'  => ['x' => 78.8, 'y' => 54.5, 'w' => 5.2, 'h' => 4.0],
            'B8'  => ['x' => 78.8, 'y' => 58.5, 'w' => 5.2, 'h' => 4.0],
            'B9'  => ['x' => 78.8, 'y' => 62.5, 'w' => 5.2, 'h' => 4.0],
            'B10' => ['x' => 78.8, 'y' => 66.5, 'w' => 5.2, 'h' => 4.0],
            'B11' => ['x' => 78.8, 'y' => 70.5, 'w' => 5.2, 'h' => 4.5],

            // BLOK A (rightmost column)
            'A1'  => ['x' => 84.5, 'y' => 38.5, 'w' => 5.2, 'h' => 4.0],
            'A2'  => ['x' => 84.5, 'y' => 42.5, 'w' => 5.2, 'h' => 4.0],
            'A3'  => ['x' => 84.5, 'y' => 46.5, 'w' => 5.2, 'h' => 4.0],
            'A5'  => ['x' => 84.5, 'y' => 50.5, 'w' => 5.2, 'h' => 4.0],
            'A6'  => ['x' => 84.5, 'y' => 54.5, 'w' => 5.2, 'h' => 4.0],
            'A7'  => ['x' => 84.5, 'y' => 58.5, 'w' => 5.2, 'h' => 4.0],
            'A8'  => ['x' => 84.5, 'y' => 62.5, 'w' => 5.2, 'h' => 4.0],
            'A9'  => ['x' => 84.5, 'y' => 66.5, 'w' => 5.2, 'h' => 4.0],
            'A10' => ['x' => 84.5, 'y' => 70.5, 'w' => 5.2, 'h' => 4.5],
        ];

        foreach ($coords as $unitCode => $c) {
            $block = substr($unitCode, 0, 1);
            $number = substr($unitCode, 1);
            Unit::where('project_id', $project->id)
                ->where('block', $block)
                ->where('number', $number)
                ->update(['siteplan_coordinates' => $c]);
        }
    }

    public function down(): void
    {
    }
};
