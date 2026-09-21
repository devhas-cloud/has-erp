<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
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

class LeadsManagementUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $user;

    private JobTitle $jobTitle;

    private Source $source;

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

        $this->user = User::create([
            'username' => 'staff',
            'email' => 'staff@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        UserAccessControl::create([
            'user_id' => $this->user->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => false,
        ]);

        $this->jobTitle = JobTitle::create(['title_name' => 'Manager', 'status' => 'Active']);
        $this->source = Source::create(['source_name' => 'Website', 'status' => 'Active']);
    }

    private function createLeadWithoutCompany(): Lead
    {
        $contact = AccountContact::create([
            'account_companies_id' => null,
            'full_name' => 'Budi Santoso',
            'salutation' => 'Bapak',
            'email' => 'budi@example.com',
            'mobile' => '81234567890',
            'job_titles_id' => $this->jobTitle->id,
            'divisions_id' => $this->division->id,
            'contact_owner_id' => $this->user->id,
            'lead_status' => 'New',
            'status' => 'Inactive',
        ]);

        return Lead::create([
            'lead_status' => 'New',
            'lead_title' => 'Prospek Air Bersih',
            'account_companies_id' => null,
            'account_contacts_id' => $contact->id,
            'source_id' => $this->source->id,
            'lead_owner_id' => $this->user->id,
            'lead_follow_up_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    private function basePayload(): array
    {
        return [
            'lead_status' => 'New',
            'salutation' => 'Bapak',
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'mobile' => '81234567890',
            'job_titles_id' => $this->jobTitle->id,
            'divisions_id' => $this->division->id,
            'source_id' => $this->source->id,
            'lead_title' => 'Prospek Air Bersih',
            'lead_follow_up_date' => now()->addDays(3)->toDateString(),
        ];
    }

    /**
     * Bug: lead tanpa company (account_companies_id null) di-update tanpa
     * memilih/menyertakan company -> sebelumnya fatal error "Call to a
     * member function update() on null" karena kode langsung memanggil
     * $lead->accountCompany->update() tanpa mengecek null dulu.
     */
    public function test_update_succeeds_without_company_when_lead_has_no_company(): void
    {
        $lead = $this->createLeadWithoutCompany();

        $response = $this->actingAs($this->user)->putJson(
            route('leads-management.update', $lead->id),
            $this->basePayload()
        );

        $response->assertOk();
        $this->assertNull($lead->fresh()->account_companies_id);
        $this->assertNull($lead->fresh()->accountContact->account_companies_id);
    }

    public function test_update_creates_company_when_lead_has_none_and_name_provided(): void
    {
        $lead = $this->createLeadWithoutCompany();

        $payload = array_merge($this->basePayload(), ['company' => 'PT Baru Jaya']);

        $response = $this->actingAs($this->user)->putJson(route('leads-management.update', $lead->id), $payload);

        $response->assertOk();
        $fresh = $lead->fresh();
        $this->assertNotNull($fresh->account_companies_id);
        $this->assertSame('PT Baru Jaya', $fresh->accountCompany->account_name);
        // AccountContact tetap sinkron dengan company yang baru dibuat.
        $this->assertSame($fresh->account_companies_id, $fresh->accountContact->account_companies_id);
    }

    public function test_update_edits_existing_company_in_place(): void
    {
        $lead = $this->createLeadWithoutCompany();
        $company = AccountCompany::create(['account_name' => 'PT Lama', 'status' => 'Active']);
        $lead->update(['account_companies_id' => $company->id]);
        $lead->accountContact->update(['account_companies_id' => $company->id]);

        $payload = array_merge($this->basePayload(), ['company' => 'PT Lama Diperbarui']);

        $response = $this->actingAs($this->user)->putJson(route('leads-management.update', $lead->id), $payload);

        $response->assertOk();
        $this->assertSame('PT Lama Diperbarui', $company->fresh()->account_name);
        $this->assertSame($company->id, $lead->fresh()->account_companies_id);
    }

    public function test_update_switches_to_a_different_existing_company(): void
    {
        $lead = $this->createLeadWithoutCompany();
        $otherCompany = AccountCompany::create(['account_name' => 'PT Lain', 'status' => 'Active']);

        $payload = array_merge($this->basePayload(), ['account_companies_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->putJson(route('leads-management.update', $lead->id), $payload);

        $response->assertOk();
        $this->assertSame($otherCompany->id, $lead->fresh()->account_companies_id);
    }
}
