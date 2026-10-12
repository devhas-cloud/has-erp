<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Division;
use App\Models\Lead;
use App\Models\Module;
use App\Models\Source;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        $module = Module::create([
            'module_code' => 'MOD_LEADS_MANAGEMENT',
            'module_name' => 'Leads Management',
            'route_name' => 'leads-management',
            'icon' => 'fa fa-bullhorn',
            'group' => 'CRM',
        ]);

        $this->user = User::create([
            'username' => 'leadsmanager',
            'email' => 'leadsmanager@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $division->id,
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
    }

    public function test_index_renders_address_autocomplete_suggestions(): void
    {
        AccountCompany::create([
            'account_name' => 'PT Maju Jaya',
            'account_owner_id' => $this->user->id,
            'address_billing_city' => 'Jakarta',
            'address_shipping_city' => 'Bandung',
            'address_billing_province' => 'DKI Jakarta',
            'address_shipping_province' => 'Jawa Barat',
            'address_billing_country' => 'Indonesia',
            'address_shipping_country' => 'Indonesia',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)->get(route('leads-management.index'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="lead-city-suggestions"', $html);
        $this->assertStringContainsString('id="lead-province-suggestions"', $html);
        $this->assertStringContainsString('id="lead-country-suggestions"', $html);
        // Nilai shipping-only harus ikut masuk (union billing + shipping).
        $this->assertStringContainsString('<option value="Bandung">', $html);
        $this->assertStringContainsString('<option value="Jawa Barat">', $html);
    }

    public function test_modal_address_fields_have_list_attributes(): void
    {
        $response = $this->actingAs($this->user)->get(route('leads-management.index'));

        $html = $response->getContent();
        $this->assertStringContainsString('id="lead-addr-city"', $html);
        $this->assertStringContainsString('list="lead-city-suggestions"', $html);
        $this->assertStringContainsString('list="lead-province-suggestions"', $html);
        $this->assertStringContainsString('list="lead-country-suggestions"', $html);
    }

    public function test_show_renders_qualified_modal_autocomplete(): void
    {
        $company = AccountCompany::create([
            'account_name' => 'PT Maju Jaya',
            'account_owner_id' => $this->user->id,
            'address_billing_city' => 'Jakarta',
            'address_shipping_city' => 'Bandung',
            'address_billing_province' => 'DKI Jakarta',
            'address_shipping_province' => 'Jawa Barat',
            'address_billing_country' => 'Indonesia',
            'address_shipping_country' => 'Indonesia',
            'status' => 'Active',
        ]);

        $contact = AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Test Person',
            'contact_owner_id' => $this->user->id,
            'status' => 'Active',
        ]);

        $source = Source::create(['source_name' => 'Website', 'status' => 'Active']);

        $lead = Lead::create([
            'lead_title' => 'Test Lead',
            'lead_status' => 'New',
            'account_contacts_id' => $contact->id,
            'account_companies_id' => $company->id,
            'source_id' => $source->id,
            'lead_owner_id' => $this->user->id,
            'lead_follow_up_date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('leads-management.show', $lead->id));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="qual-city-suggestions"', $html);
        $this->assertStringContainsString('id="qual-province-suggestions"', $html);
        $this->assertStringContainsString('id="qual-country-suggestions"', $html);
        // Nilai shipping-only ikut masuk (union billing + shipping).
        $this->assertStringContainsString('<option value="Bandung">', $html);
        $this->assertStringContainsString('list="qual-city-suggestions"', $html);
    }

    public function test_data_endpoint_returns_icon_urls(): void
    {
        $company = AccountCompany::create([
            'account_name' => 'PT Maju Jaya',
            'account_owner_id' => $this->user->id,
            'icon' => 'account-icons/logo.png',
            'status' => 'Active',
        ]);

        $contact = AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Test Person',
            'icon' => 'avatars/contact.png',
            'contact_owner_id' => $this->user->id,
            'status' => 'Active',
        ]);

        $source = Source::create(['source_name' => 'Website', 'status' => 'Active']);

        Lead::create([
            'lead_title' => 'Test Lead',
            'lead_status' => 'New',
            'account_contacts_id' => $contact->id,
            'account_companies_id' => $company->id,
            'source_id' => $source->id,
            'lead_owner_id' => $this->user->id,
            'lead_follow_up_date' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson(route('leads-management.data'));

        $response->assertOk();
        $this->assertStringContainsString('storage/avatars/contact.png', $response->json('data.0.icon'));
        $this->assertStringContainsString('storage/account-icons/logo.png', $response->json('data.0.company_icon'));
    }
}