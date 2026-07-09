<?php

namespace Database\Seeders;

use App\Models\Segmentation;
use Illuminate\Database\Seeder;

class SegmentationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            ['segmentation_name' => 'Chemical', 'description' => 'Segmentation for chemical industry', 'status' => 'Active'],
            ['segmentation_name' => 'Consultant', 'description' => 'Segmentation for consulting industry', 'status' => 'Active'],
            ['segmentation_name' => 'Distributor/Partner', 'description' => 'Segmentation for distributor and partner industry', 'status' => 'Active'],
            ['segmentation_name' => 'Pharmaceutical', 'description' => 'Segmentation for pharmaceutical industry', 'status' => 'Active'],
            ['segmentation_name' => 'Electronics', 'description' => 'Segmentation for electronics industry', 'status' => 'Active'],
            ['segmentation_name' => 'Textile', 'description' => 'Segmentation for textile industry', 'status' => 'Active'],
            ['segmentation_name' => 'Construction', 'description' => 'Segmentation for construction industry', 'status' => 'Active'],
            ['segmentation_name' => 'Healthcare', 'description' => 'Segmentation for healthcare industry', 'status' => 'Active'],
            ['segmentation_name' => 'Energy & Utilities', 'description' => 'Segmentation for energy and utilities industry', 'status' => 'Active'],
            ['segmentation_name' => 'Food & Beverage', 'description' => 'Segmentation for food and beverage industry', 'status' => 'Active'],
            ['segmentation_name' => 'Automotive', 'description' => 'Segmentation for automotive industry', 'status' => 'Active'],
            ['segmentation_name' => 'Aerospace & Defense', 'description' => 'Segmentation for aerospace and defense industry', 'status' => 'Active'],
            ['segmentation_name' => 'Telecommunications', 'description' => 'Segmentation for telecommunications industry', 'status' => 'Active'],
            ['segmentation_name' => 'Retail & E-commerce', 'description' => 'Segmentation for retail and e-commerce industry', 'status' => 'Active'],
            ['segmentation_name' => 'Logistics & Transportation', 'description' => 'Segmentation for logistics and transportation industry', 'status' => 'Active'],
            ['segmentation_name' => 'Media & Entertainment', 'description' => 'Segmentation for media and entertainment industry', 'status' => 'Active'],
            ['segmentation_name' => 'Real Estate & Property Management', 'description' => 'Segmentation for real estate and property management industry', 'status' => 'Active'],
            ];

        foreach ($segments as $segment) {
            Segmentation::firstOrCreate(
                ['segmentation_name' => $segment['segmentation_name']],
                $segment
            );
        }
    }
}
