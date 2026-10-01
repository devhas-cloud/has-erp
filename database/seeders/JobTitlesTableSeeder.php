<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitlesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Referensi dari export Salesforce (Contact.Job_Title__c) + 3 title tambahan.
        JobTitle::query()->delete();

        $titles = [
            ['title_name' => 'Admin / Staff Admin'],
            ['title_name' => 'Asisten Manager'],
            ['title_name' => 'Blasting Engineer'],
            ['title_name' => 'Blasting Supervisor'],
            ['title_name' => 'Branch Manager'],
            ['title_name' => 'Business Owner'],
            ['title_name' => 'CEO / Director'],
            ['title_name' => 'Chemist'],
            ['title_name' => 'Coordinator'],
            ['title_name' => 'Dekan'],
            ['title_name' => 'Department Head'],
            ['title_name' => 'Deputy Manager'],
            ['title_name' => 'Division Head'],
            ['title_name' => 'Dokter OH'],
            ['title_name' => 'Engineering Manager'],
            ['title_name' => 'Environmental Engineer'],
            ['title_name' => 'Environmental Manager'],
            ['title_name' => 'Environmental Officer'],
            ['title_name' => 'Environmental Specialist'],
            ['title_name' => 'Environmental Superintendent'],
            ['title_name' => 'Environmental Supervisor'],
            ['title_name' => 'General Manager'],
            ['title_name' => 'Geologist'],
            ['title_name' => 'Geotechnical Engineer'],
            ['title_name' => 'Head Lab'],
            ['title_name' => 'Health and Safety Officer'],
            ['title_name' => 'HSE Engineer'],
            ['title_name' => 'HSE Manager'],
            ['title_name' => 'HSE Officer'],
            ['title_name' => 'HSE Superintendent'],
            ['title_name' => 'Industrial Hygiene Manager'],
            ['title_name' => 'Industrial Hygiene Officer'],
            ['title_name' => 'Industrial Hygienist'],
            ['title_name' => 'K3 Manager'],
            ['title_name' => 'K3 Officer'],
            ['title_name' => 'Kepala Laboratorium'],
            ['title_name' => 'Ketua Jurusan'],
            ['title_name' => 'Ketua Prodi'],
            ['title_name' => 'Lab Analyst'],
            ['title_name' => 'Lab Consultant'],
            ['title_name' => 'Lab Manager'],
            ['title_name' => 'Lab Technician'],
            ['title_name' => 'Laboratory Supervisor'],
            ['title_name' => 'Lecture/Dosen'],
            ['title_name' => 'Manager'],
            ['title_name' => 'Mine Engineer'],
            ['title_name' => 'Mine Planning Engineer'],
            ['title_name' => 'Occupational Health Officer'],
            ['title_name' => 'Occupational Hygienist'],
            ['title_name' => 'Plant Manager'],
            ['title_name' => 'President Director'],
            ['title_name' => 'Procurement Manager'],
            ['title_name' => 'Project Engineer'],
            ['title_name' => 'Project Manager'],
            ['title_name' => 'Purchasing / Procurement'],
            ['title_name' => 'QA/QC Analyst'],
            ['title_name' => 'Quality Assurance Manager'],
            ['title_name' => 'Regulatory Affairs Manager'],
            ['title_name' => 'Research Assistant'],
            ['title_name' => 'Section Head'],
            ['title_name' => 'SHE Manager'],
            ['title_name' => 'SHE Officer'],
            ['title_name' => 'Site Manager'],
            ['title_name' => 'Staff'],
            ['title_name' => 'Superintendent'],
            ['title_name' => 'Supervisor'],
            ['title_name' => 'Sustainability Manager'],
            ['title_name' => 'Team Leader'],
            ['title_name' => 'Technical Manager'],
            ['title_name' => 'University Researcher'],
        ];

        foreach ($titles as $title) {
            JobTitle::create($title + ['description' => $title['title_name'], 'status' => 'Active']);
        }
    }
}
