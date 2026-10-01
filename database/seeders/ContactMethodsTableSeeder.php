<?php

namespace Database\Seeders;

use App\Models\ContactMethod;
use Illuminate\Database\Seeder;

class ContactMethodsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Nilai dari export Salesforce (Contact.Preferred_Contact_Method__c).
        $methods = [
            ['method_name' => 'Phone', 'status' => 'Active'],
            ['method_name' => 'Email', 'status' => 'Active'],
            ['method_name' => 'WhatsApp', 'status' => 'Active'],
            ['method_name' => 'Meeting', 'status' => 'Active'],
            ['method_name' => 'Video Call', 'status' => 'Active'],
            ['method_name' => 'Other', 'status' => 'Active'],
            ['method_name' => 'Zoom/Conference', 'status' => 'Active'],
        ];

        foreach ($methods as $method) {
            ContactMethod::firstOrCreate(
                ['method_name' => $method['method_name']],
                $method + ['description' => $method['method_name']]
            );
        }
    }
}
