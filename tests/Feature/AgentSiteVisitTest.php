<?php

namespace Tests\Feature;

use App\Models\AgentSiteVisit;
use App\Models\BrokerCompany;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectDutySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSiteVisitTest extends TestCase
{
    public function test_can_record_agent_site_visit()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $dutyUser = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'inhouse',
        ]);

        $visitingAgent = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'agency_agent',
            'name' => 'Agent Brighton Test',
        ]);

        // Create duty schedule for today
        $schedule = ProjectDutySchedule::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'user_id' => $dutyUser->id,
            'duty_date' => now()->toDateString(),
            'shift' => 'full_day',
            'status' => 'active',
        ]);

        $response = $this->actingAs($dutyUser)->post('/agent-visits', [
            'project_id' => $project->id,
            'visit_date' => now()->toDateString(),
            'visit_time' => '14:30',
            'agent_id' => $visitingAgent->id,
            'purpose' => 'konten',
            'visitor_count' => 2,
            'notes' => 'Bikin video unit 36 untuk TikTok',
            'documentation_url' => 'https://instagram.com/reel/12345',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('agent_site_visits', [
            'project_id' => $project->id,
            'agent_name' => 'Agent Brighton Test',
            'recorded_by' => $dutyUser->id,
            'duty_schedule_id' => $schedule->id,
            'purpose' => 'konten',
            'visitor_count' => 2,
        ]);
    }

    public function test_can_record_manual_guest_agent_visit()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $dutyUser = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'inhouse',
        ]);

        $response = $this->actingAs($dutyUser)->post('/agent-visits', [
            'project_id' => $project->id,
            'visit_date' => now()->toDateString(),
            'visit_time' => '10:15',
            'agent_name' => 'Rudy Hartono',
            'agent_phone' => '0812999888',
            'agency_name' => 'ERA Star',
            'purpose' => 'pk',
            'visitor_count' => 3,
            'notes' => 'Penjelasan spesifikasi dan price list',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('agent_site_visits', [
            'project_id' => $project->id,
            'agent_name' => 'Rudy Hartono',
            'agency_name' => 'ERA Star',
            'purpose' => 'pk',
            'visitor_count' => 3,
        ]);
    }

    public function test_can_update_agent_site_visit()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $dutyUser = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'inhouse',
        ]);

        $visit = AgentSiteVisit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'recorded_by' => $dutyUser->id,
            'agent_name' => 'Budi',
            'visit_date' => now()->toDateString(),
            'visit_time' => '11:00',
            'purpose' => 'pk',
            'visitor_count' => 1,
            'notes' => 'Catatan awal',
        ]);

        $response = $this->actingAs($dutyUser)->put("/agent-visits/{$visit->id}", [
            'visit_date' => now()->toDateString(),
            'visit_time' => '11:30',
            'agent_name' => 'Budi Santoso',
            'agent_phone' => '08111222333',
            'agency_name' => 'Brighton',
            'purpose' => 'konten',
            'visitor_count' => 2,
            'notes' => 'Update catatan konten',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('agent_site_visits', [
            'id' => $visit->id,
            'agent_name' => 'Budi Santoso',
            'purpose' => 'konten',
            'visitor_count' => 2,
        ]);
    }

    public function test_can_delete_agent_site_visit()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $dutyUser = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'inhouse',
        ]);

        $visit = AgentSiteVisit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'recorded_by' => $dutyUser->id,
            'agent_name' => 'Hapus Saya',
            'visit_date' => now()->toDateString(),
            'visit_time' => '15:00',
            'purpose' => 'pk',
        ]);

        $response = $this->actingAs($dutyUser)->delete("/agent-visits/{$visit->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('agent_site_visits', [
            'id' => $visit->id,
        ]);
    }

    public function test_traffic_analytics_api_endpoint()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->get("/agent-visits/report?project_id={$project->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'today_visits_count',
            'week_visits_count',
            'month_visits_count',
            'monthly_purposes',
            'top_agents',
            'top_agencies',
            'daily_trend',
        ]);
    }

    public function test_duty_schedules_index_loads_with_visits_and_analytics()
    {
        $company = Company::firstOrCreate(['id' => 1], ['name' => 'PT Test']);
        $project = Project::firstOrCreate(['id' => 1], [
            'name' => 'Alonica',
            'code' => 'ALN',
            'location' => 'Bogor',
            'company_id' => $company->id
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'agent_type' => 'inhouse',
        ]);

        $response = $this->actingAs($user)->get("/duty-schedules?project_id={$project->id}&tab=visits");
        $response->assertOk();
    }
}
