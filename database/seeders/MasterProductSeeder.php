<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\MasterProduct;
use Illuminate\Database\Seeder;

class MasterProductSeeder extends Seeder
{
    public function run(): void
    {
        $waterDivision = Division::where('division_name', 'WATER')->first();
        $imsDivision = Division::where('division_name', 'IMS')->first();

        if ($waterDivision) {
            $this->seedWaterProducts($waterDivision->id);
        } else {
            $this->command?->warn('Divisi WATER tidak ditemukan, seed produk WATER dilewati.');
        }

        if ($imsDivision) {
            $this->seedImsProducts($imsDivision->id);
        } else {
            $this->command?->warn('Divisi IMS tidak ditemukan, seed produk IMS dilewati.');
        }
    }

    private function seedWaterProducts(int $divisionId): void
    {
        $products = [
            // Recordall — Disc Series
            ['name' => 'Recordall Disc Series Meter Model 25 (5/8 in)', 'code' => 'BM-WTR-001', 'category' => 'Water Meter', 'price' => 2450000],
            ['name' => 'Recordall Disc Series Meter Model 35 (3/4 in)', 'code' => 'BM-WTR-002', 'category' => 'Water Meter', 'price' => 2875000],
            ['name' => 'Recordall Disc Series Meter Model 55 (1 in)', 'code' => 'BM-WTR-003', 'category' => 'Water Meter', 'price' => 4120000],
            ['name' => 'Recordall Turbo Series Meter 2 in', 'code' => 'BM-WTR-004', 'category' => 'Water Meter', 'price' => 18750000],
            ['name' => 'Recordall Compound Series Meter 3 in', 'code' => 'BM-WTR-005', 'category' => 'Water Meter', 'price' => 52400000],

            // E-Series — Ultrasonic
            ['name' => 'E-Series Residential Ultrasonic Meter 5/8 x 1/2 in', 'code' => 'BM-WTR-006', 'category' => 'Ultrasonic Meter', 'price' => 3450000],
            ['name' => 'E-Series Commercial Ultrasonic Meter 2 in', 'code' => 'BM-WTR-007', 'category' => 'Ultrasonic Meter', 'price' => 26800000],
            ['name' => 'E-Series Industrial Ultrasonic Meter G2 8 in', 'code' => 'BM-WTR-008', 'category' => 'Ultrasonic Meter', 'price' => 96500000],

            // ModMAG — Electromagnetic
            ['name' => 'ModMAG M2000 Electromagnetic Flow Meter DN50', 'code' => 'BM-WTR-009', 'category' => 'Electromagnetic Flow Meter', 'price' => 78500000],
            ['name' => 'ModMAG M2000 Electromagnetic Flow Meter DN100', 'code' => 'BM-WTR-010', 'category' => 'Electromagnetic Flow Meter', 'price' => 112500000],

            // Dynasonics — Ultrasonic Clamp-on
            ['name' => 'Dynasonics TFX-5000 Ultrasonic Clamp-on Flow Meter', 'code' => 'BM-WTR-011', 'category' => 'Ultrasonic Flow Meter', 'price' => 68500000],
            ['name' => 'Dynasonics U500w Ultrasonic Flow Meter 1.5-2 in', 'code' => 'BM-WTR-012', 'category' => 'Ultrasonic Flow Meter', 'price' => 42300000],

            // ORION — AMI/AMR Endpoints
            ['name' => 'ORION Cellular LTE Endpoint', 'code' => 'BM-WTR-013', 'category' => 'AMI Endpoint', 'price' => 1250000],
            ['name' => 'ORION Fixed Network Endpoint SE', 'code' => 'BM-WTR-014', 'category' => 'AMI Endpoint', 'price' => 985000],

            // Hedland — Variable Area
            ['name' => 'Hedland H726A Variable Area Flow Meter 1 in NPT', 'code' => 'BM-WTR-015', 'category' => 'Variable Area Flow Meter', 'price' => 15600000],

            // Blancett — Turbine
            ['name' => 'Blancett Model 1100 Turbine Flow Meter 1 in', 'code' => 'BM-WTR-016', 'category' => 'Turbine Flow Meter', 'price' => 22400000],

            // Preso — Differential Pressure
            ['name' => 'Preso Ellipse Wedge Meter Primary Flow Element 4 in', 'code' => 'BM-WTR-017', 'category' => 'Differential Pressure', 'price' => 48700000],

            // s::can — Water Quality
            ['name' => 's::can spectro::lyser Water Quality Monitor', 'code' => 'BM-WTR-018', 'category' => 'Water Quality Monitor', 'price' => 185000000],
            ['name' => 's::can con::lyse pH Sensor Probe', 'code' => 'BM-WTR-019', 'category' => 'Water Quality Monitor', 'price' => 32500000],

            // BEACON — Analytics Software
            ['name' => 'BEACON AMA Software License (Annual Subscription)', 'code' => 'BM-WTR-020', 'category' => 'Analytics Software', 'price' => 4500000],
        ];

        foreach ($products as $product) {
            MasterProduct::updateOrCreate(
                ['code' => $product['code']],
                [
                    'name' => $product['name'],
                    'brand' => 'Badger Meter',
                    'category' => $product['category'],
                    'division_id' => $divisionId,
                    'description' => null,
                    'image' => null,
                    'price' => $product['price'],
                    'status' => 'Active',
                ]
            );
        }

        $this->command?->info('Seeded '.count($products).' produk WATER.');
    }

    private function seedImsProducts(int $divisionId): void
    {
        $brands = [
            'Schneider', 'Grundfos', 'Victaulic', 'Georg Fischer', 'Pentair',
            'WIKA', 'Endress+Hauser', 'KSB', 'AVK', 'Geberit',
        ];

        $products = [
            // Pipa
            ['name' => 'PVC Pipe AW 4 inch x 4 m', 'category' => 'Pipa PVC', 'price' => 385000],
            ['name' => 'HDPE Pipe PN16 6 inch x 6 m', 'category' => 'Pipa HDPE', 'price' => 5850000],
            ['name' => 'Galvanized Pipe Schedule 40 2 inch x 6 m', 'category' => 'Pipa Galvanized', 'price' => 1240000],
            ['name' => 'Stainless Pipe SS304 Sch 10 3 inch x 6 m', 'category' => 'Pipa Stainless', 'price' => 8650000],
            ['name' => 'Copper Pipe Type L 1 inch x 3 m', 'category' => 'Pipa Copper', 'price' => 2350000],

            // Fitting
            ['name' => 'Elbow PVC 90 Degree AW 4 inch', 'category' => 'Fitting PVC', 'price' => 85000],
            ['name' => 'Tee Galvanized Equal 2 inch', 'category' => 'Fitting Galvanized', 'price' => 165000],
            ['name' => 'Coupling HDPE Electrofusion 6 inch', 'category' => 'Fitting HDPE', 'price' => 2850000],
            ['name' => 'Flange SS304 Slip-On PN16 4 inch', 'category' => 'Flange Stainless', 'price' => 1950000],
            ['name' => 'Reducer Concentric SS304 4x2 inch', 'category' => 'Fitting Stainless', 'price' => 1450000],

            // Valve
            ['name' => 'Gate Valve Cast Iron PN16 4 inch', 'category' => 'Gate Valve', 'price' => 3850000],
            ['name' => 'Ball Valve Brass Full Bore 2 inch', 'category' => 'Ball Valve', 'price' => 685000],
            ['name' => 'Butterfly Valve Ductile Iron Gear Operated 6 inch', 'category' => 'Butterfly Valve', 'price' => 8750000],
            ['name' => 'Check Valve Swing Type SS316 3 inch', 'category' => 'Check Valve', 'price' => 4250000],
            ['name' => 'Globe Valve Bronze Screwed 1.5 inch', 'category' => 'Globe Valve', 'price' => 1250000],
            ['name' => 'Pressure Reducing Valve Brass DN50', 'category' => 'Pressure Control Valve', 'price' => 5650000],

            // Pump
            ['name' => 'Centrifugal Pump 5.5 kW Single Stage', 'category' => 'Pump Sentrifugal', 'price' => 34500000],
            ['name' => 'Submersible Pump 15 kW Multistage', 'category' => 'Pump Submersible', 'price' => 85200000],
            ['name' => 'Booster Pump Set 2x2.2 kW with Pressure Tank', 'category' => 'Booster Pump', 'price' => 42750000],
            ['name' => 'Diaphragm Pump Pneumatic 1.5 inch Aluminum', 'category' => 'Diaphragm Pump', 'price' => 28900000],

            // Instrumentasi
            ['name' => 'Pressure Gauge Bourdon Tube 0-10 Bar 63 mm', 'category' => 'Pressure Gauge', 'price' => 285000],
            ['name' => 'Flow Sensor Magnetic Inline DN25', 'category' => 'Flow Sensor', 'price' => 12750000],
            ['name' => 'Level Transmitter Hydrostatic 0-5 mWC 4-20mA', 'category' => 'Level Transmitter', 'price' => 8450000],
            ['name' => 'pH Meter Digital Portable with ATC Probe', 'category' => 'Water Analyzer', 'price' => 5625000],
            ['name' => 'Chlorine Analyzer Online Residual 0-5 ppm', 'category' => 'Water Analyzer', 'price' => 64800000],
        ];

        foreach ($products as $i => $product) {
            MasterProduct::updateOrCreate(
                ['code' => sprintf('IMS-MAT-%03d', $i + 1)],
                [
                    'name' => $product['name'],
                    'brand' => $brands[$i % count($brands)],
                    'category' => $product['category'],
                    'division_id' => $divisionId,
                    'description' => null,
                    'image' => null,
                    'price' => $product['price'],
                    'status' => 'Active',
                ]
            );
        }

        $this->command?->info('Seeded '.count($products).' produk IMS.');
    }
}
