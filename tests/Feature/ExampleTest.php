<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }

    /**
     * Test that guide page requires authentication.
     */
    public function test_guide_page_requires_authentication(): void
    {
        $response = $this->get('/guide');

        $response->assertRedirect('/login');
    }

    /**
     * Test that guide page renders successfully for logged in users.
     */
    public function test_guide_page_renders_successfully_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/guide');

        $response->assertStatus(200);
    }
}
