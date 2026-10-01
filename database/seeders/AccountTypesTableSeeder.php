<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Nilai dari export Salesforce (Account.Account_Type__c).
        $types = [
            ['type_name' => 'New', 'status' => 'Active'],
            ['type_name' => 'Existing', 'status' => 'Active'],
            ['type_name' => 'Prospect', 'status' => 'Active'],
            ['type_name' => 'Dormant', 'status' => 'Active'],
            ['type_name' => 'Supplier', 'status' => 'Active'],
            ['type_name' => 'Partner', 'status' => 'Active'],
        ];

        foreach ($types as $type) {
            AccountType::firstOrCreate(
                ['type_name' => $type['type_name']],
                $type + ['description' => $type['type_name']]
            );
        }
    }
}
