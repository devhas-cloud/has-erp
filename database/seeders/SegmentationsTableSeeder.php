<?php

namespace Database\Seeders;

use App\Models\Segmentation;
use Illuminate\Database\Seeder;

class SegmentationsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Referensi dari export Salesforce (Account.Segmentation__c) — 53 nilai.
        Segmentation::query()->delete();

        $segments = [
            ['segmentation_name' => 'Agriculture'],
            ['segmentation_name' => 'Aquaculture'],
            ['segmentation_name' => 'Balai Pemerintah'],
            ['segmentation_name' => 'Cement'],
            ['segmentation_name' => 'Certification'],
            ['segmentation_name' => 'Chemical'],
            ['segmentation_name' => 'Coal Mining'],
            ['segmentation_name' => 'Construction'],
            ['segmentation_name' => 'Consultant'],
            ['segmentation_name' => 'Copper Mining'],
            ['segmentation_name' => 'Dinas Pemerintah'],
            ['segmentation_name' => 'Distributor/Partner'],
            ['segmentation_name' => 'Drilling'],
            ['segmentation_name' => 'Education'],
            ['segmentation_name' => 'Energy'],
            ['segmentation_name' => 'EPC Contractor / Engineering'],
            ['segmentation_name' => 'Explosive'],
            ['segmentation_name' => 'Fertilizer'],
            ['segmentation_name' => 'Food and Beverage'],
            ['segmentation_name' => 'Gas'],
            ['segmentation_name' => 'Gas Exploration and production'],
            ['segmentation_name' => 'Gold Mining'],
            ['segmentation_name' => 'Government'],
            ['segmentation_name' => 'Health Clinic/Medical'],
            ['segmentation_name' => 'Hospital'],
            ['segmentation_name' => 'Industrial Estate'],
            ['segmentation_name' => 'IOT / System Integrator'],
            ['segmentation_name' => 'Kementrian'],
            ['segmentation_name' => 'Lab Services'],
            ['segmentation_name' => 'Logistic & Shipping'],
            ['segmentation_name' => 'Manufacture'],
            ['segmentation_name' => 'Mining Contractor'],
            ['segmentation_name' => 'Mining Others'],
            ['segmentation_name' => 'Mining Services'],
            ['segmentation_name' => 'Mining Smelter'],
            ['segmentation_name' => 'Nickel Mining'],
            ['segmentation_name' => 'Oil and Gas'],
            ['segmentation_name' => 'Oleochemical'],
            ['segmentation_name' => 'Palm Oil / Plantation'],
            ['segmentation_name' => 'Personal'],
            ['segmentation_name' => 'Petrochemical'],
            ['segmentation_name' => 'Pharmaceutical'],
            ['segmentation_name' => 'Power Plant'],
            ['segmentation_name' => 'Pulp and Paper'],
            ['segmentation_name' => 'Research'],
            ['segmentation_name' => 'Steel'],
            ['segmentation_name' => 'Supplier'],
            ['segmentation_name' => 'Textile/Rayon'],
            ['segmentation_name' => 'Training Center'],
            ['segmentation_name' => 'Transportation'],
            ['segmentation_name' => 'University'],
            ['segmentation_name' => 'Water Service and Supply'],
            ['segmentation_name' => 'WTP/WWTP Enginerring'],
        ];

        foreach ($segments as $segment) {
            Segmentation::create($segment + ['description' => $segment['segmentation_name'], 'status' => 'Active']);
        }
    }
}
