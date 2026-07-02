<?php

namespace Database\Seeders;

use App\Models\ContactMethod;
use Illuminate\Database\Seeder;

class ContactMethodsTableSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['method_name' => 'Phone', 'description' => 'Kontak melalui telepon', 'status' => 'Active'],
            ['method_name' => 'Email', 'description' => 'Kontak melalui email', 'status' => 'Active'],
            ['method_name' => 'WhatsApp', 'description' => 'Kontak melalui WhatsApp', 'status' => 'Active'],
            ['method_name' => 'Meeting', 'description' => 'Kontak melalui pertemuan langsung', 'status' => 'Active'],
            ['method_name' => 'Video Call', 'description' => 'Kontak melalui panggilan video', 'status' => 'Active'],
        ];

        foreach ($methods as $method) {
            ContactMethod::firstOrCreate(
                ['method_name' => $method['method_name']],
                $method
            );
        }
    }
}
