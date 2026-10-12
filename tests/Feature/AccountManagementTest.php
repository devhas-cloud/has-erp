<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\Division;
use App\Models\Module;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\TaskRole;
use App\Models\TypesAccountsCompany;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        $module = Module::create([
            'module_code' => 'MOD_ACCOUNT_MANAGEMENT',
            'module_name' => 'Account Management',
            'route_name' => 'accounts-management',
            'icon' => 'fa fa-building',
            'group' => 'CRM',
        ]);

        $this->user = User::create([
            'username' => 'accountmanager',
            'email' => 'accountmanager@has.com',
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

        $this->payload = [
            'account_name' => 'PT Sumber Rejeki',
            'types_accounts_companies_id' => TypesAccountsCompany::create(['type_name' => 'Customer', 'status' => 'Active'])->id,
            'sources_id' => Source::create(['source_name' => 'Website', 'status' => 'Active'])->id,
            'segmentation_id' => Segmentation::create(['segmentation_name' => 'End User', 'status' => 'Active'])->id,
            'business_entities_id' => BusinessEntity::create(['entity_name' => 'HAS', 'status' => 'Active'])->id,
            'business_values_id' => BusinessValue::create(['value_name' => 'High', 'status' => 'Active'])->id,
        ];
    }

    public function test_index_renders_union_address_autocomplete(): void
    {
        AccountCompany::create(array_merge($this->payload, [
            'address_billing_city' => 'Jakarta',
            'address_shipping_city' => 'Bandung',
            'address_billing_province' => 'DKI Jakarta',
            'address_shipping_province' => 'Jawa Barat',
            'address_billing_country' => 'Indonesia',
            'status' => 'Active',
        ]));

        $response = $this->actingAs($this->user)->get(route('accounts-management.index'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="account-city-suggestions"', $html);
        $this->assertStringContainsString('id="account-province-suggestions"', $html);
        $this->assertStringContainsString('id="account-country-suggestions"', $html);
        // Nilai shipping-only harus ikut masuk (union billing + shipping).
        $this->assertStringContainsString('<option value="Bandung">', $html);
        $this->assertStringContainsString('<option value="Jawa Barat">', $html);
    }

    public function test_store_persists_uploaded_icon_and_exposes_full_url(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->postJson(route('accounts-management.store'), array_merge($this->payload, [
            'icon' => UploadedFile::fake()->image('logo.png'),
        ]));

        $response->assertOk();
        $account = AccountCompany::firstOrFail();
        $this->assertNotNull($account->icon);
        Storage::disk('public')->assertExists($account->icon);
        $this->assertStringContainsString('storage/'.$account->icon, $account->icon_url);
    }

    public function test_sales_staff_hides_rows_of_contacts_owned_by_others(): void
    {
        $staff = TaskRole::create(['role_name' => 'Staff', 'hierarchy_level' => 40, 'is_global_delegator' => false]);
        $this->user->update(['task_role_id' => $staff->id]);

        $company = AccountCompany::create(array_merge($this->payload, ['status' => 'Active']));

        $otherUser = User::create([
            'username' => 'salesother',
            'email' => 'salesother@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->user->division_id,
            'role' => 'User',
        ]);

        AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Kontak Milik Sendiri',
            'email' => 'sendiri@example.com',
            'contact_owner_id' => $this->user->id,
            'status' => 'Active',
        ]);
        AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Kontak Milik Orang Lain',
            'email' => 'oranglain@example.com',
            'contact_owner_id' => $otherUser->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)->get(route('accounts-management.show', $company->id));

        // Kartu semua tampil; rows (email) hanya untuk kontak milik sendiri.
        $response->assertOk();
        $response->assertSee('Add Contact');
        $response->assertSee('accountContactModal');
        $response->assertSee('Kontak Milik Sendiri');
        $response->assertSee('Kontak Milik Orang Lain');
        $response->assertSee('sendiri@example.com');
        $response->assertDontSee('oranglain@example.com');
    }

    public function test_sales_manager_sees_all_contact_rows(): void
    {
        $manager = TaskRole::create(['role_name' => 'Manager', 'hierarchy_level' => 18, 'is_global_delegator' => false]);
        $this->user->update(['task_role_id' => $manager->id]);

        $company = AccountCompany::create(array_merge($this->payload, ['status' => 'Active']));

        $otherUser = User::create([
            'username' => 'salesother',
            'email' => 'salesother@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->user->division_id,
            'role' => 'User',
        ]);

        AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Kontak A',
            'email' => 'a@example.com',
            'contact_owner_id' => $this->user->id,
            'status' => 'Active',
        ]);
        AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Kontak B',
            'email' => 'b@example.com',
            'contact_owner_id' => $otherUser->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)->get(route('accounts-management.show', $company->id));

        $response->assertOk();
        $response->assertSee('a@example.com');
        $response->assertSee('b@example.com');
    }
}
