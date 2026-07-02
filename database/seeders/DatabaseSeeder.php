<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DivisionsTableSeeder::class,
            RolesTableSeeder::class,
            ModulesTableSeeder::class,
            JobTitlesTableSeeder::class,
            SourcesTableSeeder::class,
            ContactMethodsTableSeeder::class,
            SegmentationsTableSeeder::class,
            BusinessEntitiesTableSeeder::class,
            BusinessValuesTableSeeder::class,
            RoleInProjectsTableSeeder::class,
            InteractionLevelsTableSeeder::class,
            TypesAccountsCompaniesTableSeeder::class,
            AccountTypesTableSeeder::class,
            UsersTableSeeder::class,
            UserAccessControlsTableSeeder::class,
            TaskCategoriesTableSeeder::class,
            AccountCompanySeeder::class,
            AccountContactSeeder::class,
        ]);
    }
}
