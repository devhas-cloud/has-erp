<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourcesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Union unik dari Account.AccountSource + Contact.Contact_Source__c
        // + Contact.Contact_Account_Source__c (export Salesforce) — 16 nilai.
        Source::query()->delete();

        $sources = [
            ['source_name' => 'Customer Event'],
            ['source_name' => 'Database App'],
            ['source_name' => 'Email HAS'],
            ['source_name' => 'Email Principal'],
            ['source_name' => 'Employee Referral'],
            ['source_name' => 'External Referral'],
            ['source_name' => 'LinkedIn Ad'],
            ['source_name' => 'Marketing'],
            ['source_name' => 'Organic Search'],
            ['source_name' => 'Organic Social Media'],
            ['source_name' => 'Other'],
            ['source_name' => 'Partner'],
            ['source_name' => 'Trade Show'],
            ['source_name' => 'Webinar'],
            ['source_name' => 'Website'],
            ['source_name' => 'Whatsapp'],
        ];

        foreach ($sources as $source) {
            Source::create($source + ['description' => $source['source_name'], 'status' => 'Active']);
        }
    }
}
