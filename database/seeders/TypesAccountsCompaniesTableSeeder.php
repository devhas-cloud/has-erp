<?php

namespace Database\Seeders;

use App\Models\TypesAccountsCompany;
use Illuminate\Database\Seeder;

class TypesAccountsCompaniesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['type_name' => 'PT', 'description' => 'Perseroan Terbatas', 'status' => 'Active'],
            ['type_name' => 'CV', 'description' => 'Commanditaire Vennootschap', 'status' => 'Active'],
            ['type_name' => 'UV', 'description' => 'Universitas', 'status' => 'Active'],
         ];

        foreach ($types as $type) {
            TypesAccountsCompany::firstOrCreate(
                ['type_name' => $type['type_name']],
                $type
            );
        }
    }
}
