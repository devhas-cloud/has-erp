<?php

namespace Tests\Feature;

use App\Models\AccountContact;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\Module;
use App\Models\Source;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsManagementDataTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $manager;

    private User $assignee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'WATER', 'description' => 'Water', 'type' => 'External', 'status' => 'Active']);

        $module = Module::create([
            'module_code' => 'MOD_LEADS_MANAGEMENT',
            'module_name' => 'Leads Management',
            'route_name' => 'leads-management',
            'icon' => 'fa fa-bullhorn',
            'group' => 'CRM',
        ]);

        $this->manager = User::create([
            'username' => 'manager',
            'email' => 'manager@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
        UserAccessControl::create([
            'user_id' => $this->manager->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => false,
        ]);

        $this->assignee = User::create([
            'username' => 'salesrep',
            'email' => 'salesrep@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
    }

    private function createLead(?int $assignedTo = null): Lead
    {
        $jobTitle = JobTitle::create(['title_name' => 'Manager', 'status' => 'Active']);
        $source = Source::create(['source_name' => 'Website', 'status' => 'Active']);

        $contact = AccountContact::create([
            'full_name' => 'Budi Santoso',
            'salutation' => 'Bapak',
            'email' => 'budi@example.com',
            'mobile' => '81234567890',
            'job_titles_id' => $jobTitle->id,
            'divisions_id' => $this->division->id,
            'contact_owner_id' => $this->manager->id,
            'lead_status' => 'New',
            'status' => 'Inactive',
        ]);

        return Lead::create([
            'lead_status' => 'New',
            'lead_title' => 'Prospek Air Bersih',
            'account_contacts_id' => $contact->id,
            'source_id' => $source->id,
            'lead_owner_id' => $this->manager->id,
            'assigned_to' => $assignedTo,
            'lead_follow_up_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    public function test_data_endpoint_includes_assigned_to_name_column(): void
    {
        $this->createLead($this->assignee->id);

        $response = $this->actingAs($this->manager)->getJson(route('leads-management.data'));

        $response->assertOk();
        $this->assertSame('salesrep', $response->json('data.0.assigned_to_name'));
    }

    public function test_data_endpoint_shows_dash_when_unassigned(): void
    {
        $this->createLead(null);

        $response = $this->actingAs($this->manager)->getJson(route('leads-management.data'));

        $response->assertOk();
        $this->assertSame('—', $response->json('data.0.assigned_to_name'));
    }

    public function test_index_page_renders_assigned_to_column_header(): void
    {
        $response = $this->actingAs($this->manager)->get(route('leads-management.index'));

        $response->assertOk();
        $response->assertSee('Assigned To');
    }

}
