<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\Module;
use App\Models\RoleInProject;
use App\Models\Source;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Division $salesDivision;

    private User $creator;

    private User $assignee;

    private User $salesUser;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'WATER', 'description' => 'Water', 'type' => 'External', 'status' => 'Active']);
        $this->salesDivision = Division::create(['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        $module = Module::create([
            'module_code' => 'MOD_CONTACT_MANAGEMENT',
            'module_name' => 'Contact Management',
            'route_name' => 'contact-management',
            'icon' => 'fa fa-address-book',
            'group' => 'CRM',
        ]);

        $this->creator = $this->makeUser('creator', $module, ['can_create' => true, 'can_update' => true]);
        $this->assignee = $this->makeUser('assignee', $module, []);
        $this->salesUser = $this->makeUser('salesrep', $module, ['can_create' => true, 'can_update' => true], $this->salesDivision);
        $this->assignee->update(['division_id' => $this->salesDivision->id]);

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);
        $jobTitle = JobTitle::create(['title_name' => 'Manager', 'status' => 'Active']);
        $source = Source::create(['source_name' => 'Referral', 'status' => 'Active']);
        $contactMethod = ContactMethod::create(['method_name' => 'Email', 'status' => 'Active']);
        $roleInProject = RoleInProject::create(['role_name' => 'Decision Maker', 'status' => 'Active']);

        $this->payload = [
            'salutation' => 'Bapak',
            'full_name' => 'Budi Santoso',
            'account_companies_id' => $company->id,
            'email' => 'budi@example.com',
            'mobile' => '81234567890',
            'job_titles_id' => $jobTitle->id,
            'sources_id' => $source->id,
            'divisions_id' => $this->division->id,
            'contact_methods_id' => $contactMethod->id,
            'role_in_projects_id' => $roleInProject->id,
        ];
    }

    private function makeUser(string $username, Module $module, array $access, ?Division $division = null): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => ($division ?? $this->division)->id,
            'role' => 'User',
        ]);

        UserAccessControl::create(array_merge([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ], $access));

        return $user;
    }

    /**
     * assigned_to_id default ke pembuat kontak kalau tidak dikirim sama
     * sekali — field ini disembunyikan di form untuk user divisi Sales,
     * jadi nilai submit-nya memang selalu kosong untuk kasus itu.
     */
    public function test_store_defaults_assigned_to_id_to_creator_when_not_provided(): void
    {
        $response = $this->actingAs($this->creator)->postJson(route('contact-management.store'), $this->payload);

        $response->assertOk();
        $contact = AccountContact::firstOrFail();
        $this->assertSame($this->creator->id, $contact->assigned_to_id);
    }

    public function test_store_persists_assigned_to_id(): void
    {
        $payload = array_merge($this->payload, ['assigned_to_id' => $this->assignee->id]);

        $response = $this->actingAs($this->creator)->postJson(route('contact-management.store'), $payload);

        $response->assertOk();
        $contact = AccountContact::firstOrFail();
        $this->assertSame($this->assignee->id, $contact->assigned_to_id);
    }

    public function test_store_rejects_invalid_assigned_to_id(): void
    {
        $payload = array_merge($this->payload, ['assigned_to_id' => 999999]);

        $this->actingAs($this->creator)->postJson(route('contact-management.store'), $payload)->assertStatus(422);
        $this->assertSame(0, AccountContact::count());
    }

    public function test_update_persists_assigned_to_id_change(): void
    {
        $contact = AccountContact::create(array_merge($this->payload, ['contact_owner_id' => $this->creator->id, 'status' => 'Active']));

        $response = $this->actingAs($this->creator)->putJson(
            route('contact-management.update', $contact->id),
            array_merge($this->payload, ['email' => $contact->email, 'mobile' => $contact->mobile, 'assigned_to_id' => $this->assignee->id])
        );

        $response->assertOk();
        $this->assertSame($this->assignee->id, $contact->fresh()->assigned_to_id);
    }

    /**
     * Edit response harus membawa assigned_to_id mentah — cukup untuk
     * memilih <option> yang sudah dirender statis di form (bukan Select2
     * ajax lagi, jadi tidak perlu label terpisah dari relasi).
     */
    public function test_edit_response_includes_assigned_to_id(): void
    {
        $contact = AccountContact::create(array_merge($this->payload, [
            'contact_owner_id' => $this->creator->id,
            'assigned_to_id' => $this->assignee->id,
            'status' => 'Active',
        ]));

        $response = $this->actingAs($this->creator)->getJson(route('contact-management.edit', $contact->id));

        $response->assertOk();
        $this->assertSame($this->assignee->id, $response->json('data.assigned_to_id'));
    }

    public function test_data_endpoint_includes_assigned_to_name_column(): void
    {
        AccountContact::create(array_merge($this->payload, [
            'contact_owner_id' => $this->creator->id,
            'assigned_to_id' => $this->assignee->id,
            'status' => 'Active',
        ]));

        $response = $this->actingAs($this->creator)->getJson(route('contact-management.data'));

        $response->assertOk();
        $this->assertSame('assignee', $response->json('data.0.assigned_to_name'));
    }

    public function test_data_endpoint_shows_dash_when_unassigned(): void
    {
        AccountContact::create(array_merge($this->payload, ['contact_owner_id' => $this->creator->id, 'status' => 'Active']));

        $response = $this->actingAs($this->creator)->getJson(route('contact-management.data'));

        $response->assertOk();
        $this->assertSame('—', $response->json('data.0.assigned_to_name'));
    }

    public function test_index_and_show_pages_render(): void
    {
        $contact = AccountContact::create(array_merge($this->payload, [
            'contact_owner_id' => $this->creator->id,
            'assigned_to_id' => $this->assignee->id,
            'status' => 'Active',
        ]));

        $this->actingAs($this->creator)->get(route('contact-management.index'))->assertOk();
        $this->actingAs($this->creator)->get(route('contact-management.show', $contact->id))
            ->assertOk()
            ->assertSee('Assigned To')
            ->assertSee('assignee');
    }

    /**
     * Picker "Assigned To" dirender sebagai <option> statis (bukan ajax) dan
     * hanya boleh berisi user divisi Sales — "assignee"/"salesrep" (Sales)
     * harus muncul, "creator" (WATER) tidak boleh muncul sebagai pilihan.
     */
    public function test_assigned_to_options_only_include_sales_division_users(): void
    {
        $response = $this->actingAs($this->creator)->get(route('contact-management.index'));

        $response->assertOk();
        $html = $response->getContent();
        $selectHtml = substr($html, strpos($html, 'id="contact-assigned-to"'), 500);

        $this->assertStringContainsString('>assignee<', $selectHtml);
        $this->assertStringContainsString('>salesrep<', $selectHtml);
        $this->assertStringNotContainsString('>creator<', $selectHtml);
    }

    /**
     * Field Assigned To disembunyikan di form untuk user yang divisinya
     * Sales, tapi tetap muncul untuk user divisi lain.
     */
    public function test_assigned_to_field_hidden_for_sales_division_user(): void
    {
        $response = $this->actingAs($this->salesUser)->get(route('contact-management.index'));

        $response->assertOk();
        $html = $response->getContent();
        $group = substr($html, strpos($html, 'id="contact-assigned-to-group"'), 80);
        $this->assertStringContainsString('style="display:none"', $group);
    }

    public function test_assigned_to_field_visible_for_non_sales_division_user(): void
    {
        $response = $this->actingAs($this->creator)->get(route('contact-management.index'));

        $response->assertOk();
        $html = $response->getContent();
        $group = substr($html, strpos($html, 'id="contact-assigned-to-group"'), 80);
        $this->assertStringNotContainsString('style="display:none"', $group);
    }

}
