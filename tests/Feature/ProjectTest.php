<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_project_via_post(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/projects', [
                'name' => 'Proyek Test Baru',
                'code' => 'PNEW',
                'location' => 'Jakarta Barat',
                'status' => 'active',
                'amenities' => ['Taman', 'Kolam Renang']
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/projects');
        $this->assertDatabaseHas('projects', [
            'name' => 'Proyek Test Baru',
            'code' => 'PNEW',
        ]);
    }
}
