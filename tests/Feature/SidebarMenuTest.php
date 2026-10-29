<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Module;
use App\Models\ModuleGroup;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Support\SidebarMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'Water',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);
    }

    private function makeUser(string $username, string $role = 'User'): User
    {
        return User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => $role,
        ]);
    }

    private function makeModule(string $code, string $name, string $group, int $sortOrder, ?string $route = null): Module
    {
        return Module::create([
            'module_code' => $code,
            'module_name' => $name,
            'group' => $group,
            'sort_order' => $sortOrder,
            'route_name' => $route,
            'icon' => 'fa fa-circle',
        ]);
    }

    public function test_admin_modules_follow_configured_business_order(): void
    {
        foreach (['PO Supplier Approval', 'Invoice', 'Project', 'Quotation', 'Estimasi PL'] as $i => $name) {
            $this->makeModule('MOD_'.$i, $name, 'Admin', 100, 'mod-'.$i);
        }
        // Urutan bisnis: Quotation -> Estimasi PL -> Project -> Invoice -> PO Supplier Approval.
        Module::where('module_name', 'Quotation')->update(['sort_order' => 10]);
        Module::where('module_name', 'Estimasi PL')->update(['sort_order' => 20]);
        Module::where('module_name', 'Project')->update(['sort_order' => 30]);
        Module::where('module_name', 'Invoice')->update(['sort_order' => 40]);
        Module::where('module_name', 'PO Supplier Approval')->update(['sort_order' => 50]);

        $admin = $this->makeUser('superadmin', 'Admin');

        $names = SidebarMenu::forUser($admin)['Admin']->pluck('module_name')->all();

        $this->assertSame(
            ['Quotation', 'Estimasi PL', 'Project', 'Invoice', 'PO Supplier Approval'],
            $names
        );
    }

    public function test_groups_follow_module_groups_order_and_unknown_groups_go_last(): void
    {
        ModuleGroup::create(['group_name' => 'CRM', 'sort_order' => 20]);
        ModuleGroup::create(['group_name' => 'Admin', 'sort_order' => 40]);

        $this->makeModule('MOD_A', 'Invoice', 'Admin', 10, 'invoice');
        $this->makeModule('MOD_B', 'Leads Management', 'CRM', 10, 'leads-management');
        $this->makeModule('MOD_C', 'Gas Configuration', 'Gas', 10, 'gas-configuration');

        $admin = $this->makeUser('superadmin', 'Admin');

        $this->assertSame(['CRM', 'Admin', 'Gas'], SidebarMenu::forUser($admin)->keys()->all());
    }

    public function test_sidebar_only_contains_modules_user_can_read(): void
    {
        $allowed = $this->makeModule('MOD_A', 'Project', 'Admin', 10, 'project');
        $this->makeModule('MOD_B', 'Invoice', 'Admin', 20, 'invoice');

        $user = $this->makeUser('sales');

        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $allowed->id,
            'can_read' => true,
        ]);

        $names = SidebarMenu::forUser($user)->flatten(1)->pluck('module_name')->all();

        $this->assertSame(['Project'], $names);
        $this->assertSame($allowed->id, Module::firstAccessibleFor($user)->id);
    }

    public function test_first_accessible_module_uses_sidebar_order(): void
    {
        $this->makeModule('MOD_A', 'Invoice', 'Admin', 20, 'invoice');
        $this->makeModule('MOD_B', 'Quotation', 'Admin', 10, 'quotation');
        $this->makeModule('MOD_C', 'Dashboard Achievement', 'Dashboard', 10, 'dashboard-achievement');

        ModuleGroup::create(['group_name' => 'Dashboard', 'sort_order' => 10]);
        ModuleGroup::create(['group_name' => 'Admin', 'sort_order' => 40]);

        $admin = $this->makeUser('superadmin', 'Admin');

        $this->assertSame('MOD_C', Module::firstAccessibleFor($admin)->module_code);
    }
}
