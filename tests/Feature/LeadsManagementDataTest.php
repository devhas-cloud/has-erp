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

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'External', 'status' => 'Active']);

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

    /**
     * Tiga lead dengan tanggal dibuat & status berbeda untuk menguji filter/urutan.
     */
    private function seedDatedLeads(): void
    {
        $base = $this->createLead();

        foreach ([
            ['Lead Jan', 'New', '2026-01-15 09:00:00'],
            ['Lead Mar', 'Qualified', '2026-03-31 23:30:00'],
            ['Lead Jul', 'New', '2026-07-01 00:10:00'],
        ] as [$title, $status, $createdAt]) {
            $lead = Lead::create([
                'lead_status' => $status,
                'lead_title' => $title,
                'account_contacts_id' => $base->account_contacts_id,
                'source_id' => $base->source_id,
                'lead_owner_id' => $this->manager->id,
                'lead_follow_up_date' => $base->lead_follow_up_date,
            ]);
            Lead::whereKey($lead->id)->update(['created_at' => $createdAt]);
        }

        $base->delete();
    }

    private function leadTitles(array $query = []): array
    {
        return $this->actingAs($this->manager)
            ->getJson(route('leads-management.data', $query))
            ->assertOk()
            ->json('data.*.lead_title');
    }

    public function test_created_date_range_filter_is_inclusive(): void
    {
        $this->seedDatedLeads();

        $response = $this->actingAs($this->manager)->getJson(route('leads-management.data', [
            'created_from' => '2026-01-01',
            'created_to' => '2026-03-31',
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(['Lead Jan', 'Lead Mar'], $response->json('data.*.lead_title'));
        $this->assertSame(3, $response->json('recordsTotal'));
        $this->assertSame(2, $response->json('recordsFiltered'));

        $this->assertSame(['Lead Jul'], $this->leadTitles(['created_from' => '2026-07-01']));
        $this->assertEqualsCanonicalizing(['Lead Jan'], $this->leadTitles(['created_to' => '2026-01-15']));
    }

    public function test_lead_status_filter_combines_with_created_date(): void
    {
        $this->seedDatedLeads();

        $this->assertEqualsCanonicalizing(['Lead Jan', 'Lead Jul'], $this->leadTitles(['lead_status' => 'New']));
        $this->assertSame(['Lead Mar'], $this->leadTitles(['lead_status' => 'Qualified']));
        $this->assertSame(['Lead Jan'], $this->leadTitles(['lead_status' => 'New', 'created_to' => '2026-06-30']));
        $this->assertCount(3, $this->leadTitles(['lead_status' => '']));
    }

    public function test_created_column_sorts_by_created_at(): void
    {
        $this->seedDatedLeads();

        $this->assertSame(
            ['Lead Jul', 'Lead Mar', 'Lead Jan'],
            $this->leadTitles(['order' => [['column' => 9, 'dir' => 'desc']]])
        );
        $this->assertSame(
            ['Lead Jan', 'Lead Mar', 'Lead Jul'],
            $this->leadTitles(['order' => [['column' => 9, 'dir' => 'asc']]])
        );
    }

    public function test_invalid_created_date_filter_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->getJson(route('leads-management.data', ['created_from' => 'bukan-tanggal']))
            ->assertStatus(422);
    }
}
