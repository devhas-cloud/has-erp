<?php

namespace App\Services;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\RoleInProject;
use App\Models\Source;
use App\Models\User;

class ContactImportService
{
    private const COLUMN_COUNT = 18;

    private array $lookups = [];

    /**
     * Resolve all lookup name→id mappings from database.
     */
    private function resolveLookups(): void
    {
        $this->lookups = [
            'account' => AccountCompany::where('status', 'Active')->pluck('id', 'account_name')->toArray(),
            'job_title' => JobTitle::where('status', 'Active')->pluck('id', 'title_name')->toArray(),
            'contact_source' => Source::where('status', 'Active')->pluck('id', 'source_name')->toArray(),
            'department' => Division::where('type', 'External')->where('status', 'Active')->pluck('id', 'division_name')->toArray(),
            'contact_method' => ContactMethod::where('status', 'Active')->pluck('id', 'method_name')->toArray(),
            'role_in_project' => RoleInProject::where('status', 'Active')->pluck('id', 'role_name')->toArray(),
            'assigned_to' => User::pluck('id', 'username')->toArray(),
            'contact_owner' => User::pluck('id', 'username')->toArray(),
        ];
    }

    /**
     * Import contacts from CSV file.
     * Returns ['success' => N, 'failed' => N, 'errors' => [...], 'created' => N]
     */
    public function import(string $filePath, int $userId): array
    {
        $this->resolveLookups();

        // File CSV dari Excel kadang ber-encoding UTF-16 ("CSV Unicode") → transkode ke UTF-8.
        $utf8Path = $this->transcodeToUtf8($filePath);

        $success = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 0;

        try {
            $handle = fopen($utf8Path, 'r');
            if (! $handle) {
                return ['success' => 0, 'failed' => 0, 'errors' => ['Cannot open file.'], 'created' => 0];
            }

            // Baca baris pertama untuk deteksi delimiter (koma, titik-koma, tab, pipe)
            $firstLine = fgets($handle);
            if ($firstLine === false || trim($firstLine) === '') {
                return ['success' => 0, 'failed' => 0, 'errors' => ['File is empty or invalid. CSV harus memiliki baris header.'], 'created' => 0];
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
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
        } finally {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            @unlink($utf8Path);
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'created' => $success,
        ];
    }

    /**
     * Baca file dan transkode ke UTF-8 jika bukan UTF-8 (mis. UTF-16 dari Excel).
     * Menulis hasilnya ke file temp UTF-8 dan mengembalikan path-nya.
     */
    private function transcodeToUtf8(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return $filePath;
        }

        $encoding = null;
        if (str_starts_with($content, "\xFF\xFE")) {
            $encoding = 'UTF-16LE';
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $encoding = 'UTF-16BE';
        } elseif (str_contains($content, "\x00")) {
            // UTF-16 tanpa BOM: deteksi lewat posisi byte NUL (LE → ganjil, BE → genap)
            $sample = substr($content, 0, 256);
            $len = strlen($sample);
            $nulOdd = 0;
            $nulEven = 0;
            for ($i = 0; $i < $len; $i++) {
                if ($sample[$i] === "\x00") {
                    if ($i % 2 === 0) {
                        $nulEven++;
                    } else {
                        $nulOdd++;
                    }
                }
            }
            if ($nulOdd > $nulEven) {
                $encoding = 'UTF-16LE';
            } elseif ($nulEven > $nulOdd) {
                $encoding = 'UTF-16BE';
            }
        }

        if ($encoding !== null) {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Strip BOM yang tersisa (UTF-16 hasil konversi atau UTF-8 ber-BOM)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $tmp = tempnam(sys_get_temp_dir(), 'contact_import_').'.csv';
        file_put_contents($tmp, $content);

        return $tmp;
    }

    /**
     * Process a single CSV row. Returns null on success, error string on failure.
     */
    private function processRow(array $row, int $rowNum, int $userId): ?string
    {
        // Normalize row (pad to COLUMN_COUNT columns)
        $row = array_pad($row, self::COLUMN_COUNT, '');
        $row = array_map(fn ($v) => trim((string) $v), $row);

        [
            $salutation, $fullName, $accountName, $email, $phone, $mobile,
            $jobTitleName, $sourceName, $departmentName, $contactMethodName, $roleInProjectName,
            $assignedToName, $ownerName,
            $addressStreet, $addressCity, $addressProvince, $addressPostalCode, $addressCountry,
        ] = $row;

        // --- Validation ---

        $required = [
            ['salutation', $salutation],
            ['full_name', $fullName],
            ['account', $accountName],
            ['email', $email],
            ['mobile', $mobile],
            ['job_title', $jobTitleName],
            ['contact_source', $sourceName],
            ['department', $departmentName],
            ['contact_method', $contactMethodName],
            ['role_in_project', $roleInProjectName],
        ];

        foreach ($required as [$field, $value]) {
            if ($value === '') {
                return "{$field} wajib diisi.";
            }
        }

        if (! in_array($salutation, ['Ibu', 'Bapak'])) {
            return "salutation tidak valid: '{$salutation}'. Gunakan: Ibu, Bapak";
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "email tidak valid: '{$email}'";
        }

        if (AccountContact::where('email', $email)->exists()) {
            return "email sudah digunakan: '{$email}'";
        }

        if (AccountContact::where('mobile', $mobile)->exists()) {
            return "mobile sudah digunakan: '{$mobile}'";
        }

        // --- Resolve lookups ---
        $lookupErrors = [];

        $accountId = $this->resolve('account', $accountName, $lookupErrors, 'account');
        $jobTitleId = $this->resolve('job_title', $jobTitleName, $lookupErrors, 'job_title');
        $sourceId = $this->resolve('contact_source', $sourceName, $lookupErrors, 'contact_source');
        $departmentId = $this->resolve('department', $departmentName, $lookupErrors, 'department');
        $contactMethodId = $this->resolve('contact_method', $contactMethodName, $lookupErrors, 'contact_method');
        $roleInProjectId = $this->resolve('role_in_project', $roleInProjectName, $lookupErrors, 'role_in_project');

        $assignedToId = $assignedToName !== '' ? $this->resolve('assigned_to', $assignedToName, $lookupErrors, 'assigned_to') : $userId;
        $ownerId = $ownerName !== '' ? $this->resolve('contact_owner', $ownerName, $lookupErrors, 'contact_owner') : $userId;

        if (! empty($lookupErrors)) {
            return implode('; ', $lookupErrors);
        }

        // --- Insert ---
        try {
            AccountContact::create([
                'account_companies_id' => $accountId,
                'full_name' => $fullName,
                'salutation' => $salutation,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'mobile' => $mobile,
                'job_titles_id' => $jobTitleId,
                'sources_id' => $sourceId,
                'divisions_id' => $departmentId,
                'contact_methods_id' => $contactMethodId,
                'role_in_projects_id' => $roleInProjectId,
                'contact_owner_id' => $ownerId,
                'assigned_to_id' => $assignedToId,
                'address_street' => $addressStreet !== '' ? $addressStreet : null,
                'address_city' => $addressCity !== '' ? $addressCity : null,
                'address_province' => $addressProvince !== '' ? $addressProvince : null,
                'address_postal_code' => $addressPostalCode !== '' ? $addressPostalCode : null,
                'address_country' => $addressCountry !== '' ? $addressCountry : null,
                'status' => 'Active',
            ]);

            return null; // success
        } catch (\Exception $e) {
            return 'Gagal menyimpan: '.$e->getMessage();
        }
    }

    /**
     * Detect CSV delimiter by scanning the first line.
     * Counts occurrences of comma, semicolon, tab, pipe — the one with the most wins.
     * Falls back to comma.
     */
    private function detectDelimiter(string $firstLine): string
    {
        $candidates = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
            '|' => substr_count($firstLine, '|'),
        ];

        $valid = array_filter($candidates, fn ($count) => $count > 0);
        if (empty($valid)) {
            return ',';
        }

        arsort($valid);

        return key($valid);
    }

    /**
     * Lookup object for reference sheet generation.
     */
    public static function getReferenceData(): array
    {
        return [
            'Salutation' => ['Ibu', 'Bapak'],
            'Account (account_name)' => AccountCompany::where('status', 'Active')->pluck('account_name')->toArray(),
            'Job Title' => JobTitle::where('status', 'Active')->pluck('title_name')->toArray(),
            'Contact Source' => Source::where('status', 'Active')->pluck('source_name')->toArray(),
            'Department' => Division::where('type', 'External')->where('status', 'Active')->pluck('division_name')->toArray(),
            'Contact Method' => ContactMethod::where('status', 'Active')->pluck('method_name')->toArray(),
            'Role in Project' => RoleInProject::where('status', 'Active')->pluck('role_name')->toArray(),
            'Assigned To (username)' => User::pluck('username')->toArray(),
            'Contact Owner (username)' => User::pluck('username')->toArray(),
            'Tanggal/No HP' => 'Mobile disimpan lengkap dengan kode negara (contoh: 628123456789).',
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
}
