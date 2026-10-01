<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Divisi Internal (HAS) + Divisi External (ER/GA + dari export Salesforce Contact.Division__c, 32 nilai).
        // Nama boleh sama antara Internal & External — dibedakan oleh kolom `type`.
        $divisions = [
            ['division_name' => 'Admin', 'description' => 'Administrasi', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'WATER', 'description' => 'Water Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'IMS', 'description' => 'IMS Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'PD', 'description' => 'PD Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Marketing', 'description' => 'Marketing Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Sales', 'description' => 'Sales Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Finance', 'description' => 'Finance Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Enviro', 'description' => 'Enviro Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'IH', 'description' => 'IH Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Gas', 'description' => 'Gas Management', 'type' => 'Internal', 'status' => 'Active'],

            ['division_name' => 'ER', 'description' => 'ER Management', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'GA', 'description' => 'GA Management', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Administration', 'description' => 'Administration', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Business Development', 'description' => 'Business Development', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Calibration Lab', 'description' => 'Calibration Lab', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Compliance and Regulatory Affairs', 'description' => 'Compliance and Regulatory Affairs', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Consulting/Research', 'description' => 'Consulting/Research', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Drill and Blasting', 'description' => 'Drill and Blasting', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Engineering', 'description' => 'Engineering', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Environmental', 'description' => 'Environmental', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Environmental Health and Safety (EHS)', 'description' => 'Environmental Health and Safety (EHS)', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Environmental Lab', 'description' => 'Environmental Lab', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Finance', 'description' => 'Finance', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'HSE (Health, Safety, Environment)', 'description' => 'HSE (Health, Safety, Environment)', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Hydrology/Water Treatment', 'description' => 'Hydrology/Water Treatment', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Industrial Hygiene', 'description' => 'Industrial Hygiene', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Laboratory', 'description' => 'Laboratory', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Maintenance', 'description' => 'Maintenance', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Marketing', 'description' => 'Marketing', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Mining', 'description' => 'Mining', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Operations', 'description' => 'Operations', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Operations and Maintenance (O&M)', 'description' => 'Operations and Maintenance (O&M)', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Others', 'description' => 'Others', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Procurement/Supply Chain', 'description' => 'Procurement/Supply Chain', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Project Management', 'description' => 'Project Management', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Purchasing/Procurement', 'description' => 'Purchasing/Procurement', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Quality Assurance (QA)', 'description' => 'Quality Assurance (QA)', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Quality Control (QC)', 'description' => 'Quality Control (QC)', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Sales/Marketing', 'description' => 'Sales/Marketing', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Sampling', 'description' => 'Sampling', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Sustainability', 'description' => 'Sustainability', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Utility', 'description' => 'Utility', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'Water', 'description' => 'Water', 'type' => 'External', 'status' => 'Active'],
        ];

        foreach ($divisions as $division) {
            Division::firstOrCreate(
                ['division_name' => $division['division_name'], 'type' => $division['type']],
                $division
            );
        }
    }
}
