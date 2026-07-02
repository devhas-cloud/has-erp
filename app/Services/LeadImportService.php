<?php

namespace App\Services;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\AccountType;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\InteractionLevel;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\RoleInProject;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\TypesAccountsCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadImportService
{
    private array $lookups = [];

    /**
     * Resolve all lookup name→id mappings from database.
     */
    private function resolveLookups(): void
    {
        $this->lookups = [
            'job_title' => JobTitle::where('status', 'Active')->pluck('id', 'title_name')->toArray(),
            'department' => Division::where('status', 'Active')->pluck('id', 'division_name')->toArray(),
            'lead_source' => Source::where('status', 'Active')->pluck('id', 'source_name')->toArray(),
            'segmentation' => Segmentation::where('status', 'Active')->pluck('id', 'segmentation_name')->toArray(),
            'account_type' => AccountType::where('status', 'Active')->pluck('id', 'type_name')->toArray(),
            'contact_method' => ContactMethod::where('status', 'Active')->pluck('id', 'method_name')->toArray(),
            'role_in_project' => RoleInProject::where('status', 'Active')->pluck('id', 'role_name')->toArray(),
            'business_entity' => BusinessEntity::where('status', 'Active')->pluck('id', 'entity_name')->toArray(),
            'business_value' => BusinessValue::where('status', 'Active')->pluck('id', 'value_name')->toArray(),
            'interaction_level' => InteractionLevel::where('status', 'Active')->pluck('id', 'level_name')->toArray(),
            'field_type' => TypesAccountsCompany::where('status', 'Active')->pluck('id', 'type_name')->toArray(),
            'assign_to' => User::pluck('id', 'username')->toArray(),
        ];
    }

    /**
     * Import leads from CSV file.
     * Returns ['success' => N, 'failed' => N, 'errors' => [...], 'created' => N]
     */
    public function import(string $filePath, int $userId): array
    {
        $this->resolveLookups();

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Cannot open file.'], 'created' => 0];
        }

        $success = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip header row
            if ($rowNum === 1) {
                continue;
            }

            // Skip empty rows
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $result = $this->processRow($row, $rowNum, $userId);
            if ($result === null) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Baris {$rowNum}: {$result}";
            }
        }

        fclose($handle);

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'created' => $success,
        ];
    }

    /**
     * Process a single CSV row. Returns null on success, error string on failure.
     */
    private function processRow(array $row, int $rowNum, int $userId): ?string
    {
        // Normalize row (pad to 33 columns)
        $row = array_pad($row, 33, '');
        $row = array_map(fn ($v) => trim((string) $v), $row);

        [
            $leadStatus, $salutation, $fullName, $email, $jobTitleName,
            $departmentName, $leadSourceName, $leadTitle, $segmentationName, $accountTypeName,
            $followUpDate, $mobile, $phone, $company, $fieldTypeName,
            $contactMethodName, $roleInProjectName,
            $businessEntityName, $businessValueName, $interactionLevelName, $addressStreet,
            $city, $province, $zip, $country, $endUser, $unqualifiedReason,
            $closeDate, $allFieldCompleted, $leadCanBeContacted, $leadAppointment,
            $needIdentification, $assignToName,
        ] = $row;

        // --- Validation ---

        // Required fields must not be empty (check indices 0-10, 13-16 are lookup; 4-5,8-9 too)
        $required = [
            ['lead_status', $leadStatus, 0],
            ['salutation', $salutation, 1],
            ['full_name', $fullName, 2],
            ['email', $email, 3],
            ['job_title', $jobTitleName, 4],
            ['department', $departmentName, 5],
            ['lead_source', $leadSourceName, 6],
            ['lead_title', $leadTitle, 7],
            ['segmentation', $segmentationName, 8],
            ['account_type', $accountTypeName, 9],
            ['follow_up_date', $followUpDate, 10],
        ];

        foreach ($required as [$field, $value]) {
            if ($value === '') {
                return "{$field} wajib diisi.";
            }
        }

        // Validate lead_status
        $validStatuses = ['New', 'Contacted', 'Qualified', 'Unqualified'];
        if (! in_array($leadStatus, $validStatuses)) {
            return "lead_status tidak valid: '{$leadStatus}'. Gunakan: ".implode(', ', $validStatuses);
        }

        // Validate salutation
        if (! in_array($salutation, ['Bapak', 'Ibu'])) {
            return "salutation tidak valid: '{$salutation}'. Gunakan: Bapak, Ibu";
        }

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "email tidak valid: '{$email}'";
        }

        // Check email uniqueness
        if (AccountContact::where('email', $email)->exists()) {
            return "email sudah digunakan: '{$email}'";
        }

        // Validate follow_up_date
        if (! $this->isValidDate($followUpDate)) {
            return "follow_up_date tidak valid: '{$followUpDate}'. Format: YYYY-MM-DD";
        }

        // Validate close_date (optional)
        if ($closeDate !== '' && ! $this->isValidDate($closeDate)) {
            return "close_date tidak valid: '{$closeDate}'. Format: YYYY-MM-DD";
        }

        // --- Resolve lookups ---
        $lookupErrors = [];

        $jobTitleId = $this->resolve('job_title', $jobTitleName, $lookupErrors, 'job_title');
        $divisionId = $this->resolve('department', $departmentName, $lookupErrors, 'department');
        $sourceId = $this->resolve('lead_source', $leadSourceName, $lookupErrors, 'lead_source');
        $segmentationId = $this->resolve('segmentation', $segmentationName, $lookupErrors, 'segmentation');
        $accountTypeId = $this->resolve('account_type', $accountTypeName, $lookupErrors, 'account_type');

        $contactMethodId = $contactMethodName !== '' ? $this->resolve('contact_method', $contactMethodName, $lookupErrors, 'contact_method') : null;
        $roleInProjectId = $roleInProjectName !== '' ? $this->resolve('role_in_project', $roleInProjectName, $lookupErrors, 'role_in_project') : null;
        $businessEntityId = $businessEntityName !== '' ? $this->resolve('business_entity', $businessEntityName, $lookupErrors, 'business_entity') : null;
        $businessValueId = $businessValueName !== '' ? $this->resolve('business_value', $businessValueName, $lookupErrors, 'business_value') : null;
        $interactionLevelId = $interactionLevelName !== '' ? $this->resolve('interaction_level', $interactionLevelName, $lookupErrors, 'interaction_level') : null;
        $fieldTypeId = $fieldTypeName !== '' ? $this->resolve('field_type', $fieldTypeName, $lookupErrors, 'field_type') : null;
        $assignToId = $assignToName !== '' ? $this->resolve('assign_to', $assignToName, $lookupErrors, 'assign_to') : null;

        if (! empty($lookupErrors)) {
            return implode('; ', $lookupErrors);
        }

        // --- Insert ---
        DB::beginTransaction();
        try {
            $company = AccountCompany::create([
                'account_name' => $company !== '' ? $company : ($fullName.' - Company'),
                'segmentation_id' => $segmentationId,
                'account_types_id' => $accountTypeId,
                'types_accounts_companies_id' => $fieldTypeId,
                'business_entities_id' => $businessEntityId,
                'business_values_id' => $businessValueId,
                'interaction_levels_id' => $interactionLevelId,
                'address_billing_street' => $addressStreet !== '' ? $addressStreet : null,
                'address_billing_city' => $city !== '' ? $city : null,
                'address_billing_province' => $province !== '' ? $province : null,
                'address_billing_postal_code' => $zip !== '' ? $zip : null,
                'address_billing_country' => $country !== '' ? $country : null,
                'end_user' => $endUser !== '' ? (int) $endUser : null,
                'phone' => $mobile !== '' ? $mobile : null,
                'account_owner_id' => $userId,
                'status' => 'Active',
            ]);

            $contact = AccountContact::create([
                'account_companies_id' => $company->id,
                'full_name' => $fullName,
                'salutation' => $salutation,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'mobile' => $mobile !== '' ? $mobile : null,
                'job_titles_id' => $jobTitleId,
                'divisions_id' => $divisionId,
                'contact_methods_id' => $contactMethodId,
                'role_in_projects_id' => $roleInProjectId,
                'contact_owner_id' => $userId,
                'lead_status' => $leadStatus,
                'status' => 'Active',
            ]);

            Lead::create([
                'lead_status' => $leadStatus,
                'lead_title' => $leadTitle,
                'account_companies_id' => $company->id,
                'account_contacts_id' => $contact->id,
                'source_id' => $sourceId,
                'unqualified_reason' => $unqualifiedReason !== '' ? $unqualifiedReason : null,
                'closed_date' => $closeDate !== '' ? $closeDate : null,
                'all_filed_completed' => $allFieldCompleted === '1',
                'lead_owner_id' => $userId,
                'assigned_to' => $assignToId,
                'lead_can_be_contacted' => $leadCanBeContacted === '1',
                'lead_follow_up_date' => $followUpDate,
                'lead_appoinment' => $leadAppointment === '1',
                'identification' => $needIdentification === '1',
            ]);

            DB::commit();

            return null; // success
        } catch (\Exception $e) {
            DB::rollBack();

            return 'Gagal menyimpan: '.$e->getMessage();
        }
    }

    /**
     * Lookup object for reference sheet generation.
     */
    public static function getReferenceData(): array
    {
        return [
            'Lead Status' => ['New', 'Contacted', 'Qualified', 'Unqualified'],
            'Salutation' => ['Bapak', 'Ibu'],
            'Job Title' => JobTitle::where('status', 'Active')->pluck('title_name')->toArray(),
            'Department' => Division::where('status', 'Active')->pluck('division_name')->toArray(),
            'Lead Source' => Source::where('status', 'Active')->pluck('source_name')->toArray(),
            'Segmentation' => Segmentation::where('status', 'Active')->pluck('segmentation_name')->toArray(),
            'Account Type' => AccountType::where('status', 'Active')->pluck('type_name')->toArray(),
            'Contact Method' => ContactMethod::where('status', 'Active')->pluck('method_name')->toArray(),
            'Role in Project' => RoleInProject::where('status', 'Active')->pluck('role_name')->toArray(),
            'Business Entity' => BusinessEntity::where('status', 'Active')->pluck('entity_name')->toArray(),
            'Business Value' => BusinessValue::where('status', 'Active')->pluck('value_name')->toArray(),
            'Interaction Level' => InteractionLevel::where('status', 'Active')->pluck('level_name')->toArray(),
            'Field Type' => TypesAccountsCompany::where('status', 'Active')->pluck('type_name')->toArray(),
            'Assign To (username)' => User::pluck('username')->toArray(),
            'Boolean (0/1)' => '0 = Tidak, 1 = Ya',
            'Tanggal' => 'Format: YYYY-MM-DD (contoh: 2026-07-01)',
        ];
    }

    private function resolve(string $key, string $name, array &$errors, string $label): ?int
    {
        $id = $this->lookups[$key][$name] ?? null;
        if ($id === null) {
            $valid = implode(', ', array_keys($this->lookups[$key]));
            $errors[] = "{$label} '{$name}' tidak ditemukan. Pilihan: {$valid}";

            return null;
        }

        return $id;
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }
}
