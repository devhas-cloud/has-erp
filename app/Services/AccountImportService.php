<?php

namespace App\Services;

use App\Models\AccountCompany;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\InteractionLevel;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\TypesAccountsCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountImportService
{
    private const COLUMN_COUNT = 23;

    private array $lookups = [];

    /**
     * Resolve all lookup name→id mappings from database.
     */
    private function resolveLookups(): void
    {
        $this->lookups = [
            'field_type' => TypesAccountsCompany::where('status', 'Active')->pluck('id', 'type_name')->toArray(),
            'account_source' => Source::where('status', 'Active')->pluck('id', 'source_name')->toArray(),
            'segmentation' => Segmentation::where('status', 'Active')->pluck('id', 'segmentation_name')->toArray(),
            'business_entity' => BusinessEntity::where('status', 'Active')->pluck('id', 'entity_name')->toArray(),
            'business_value' => BusinessValue::where('status', 'Active')->pluck('id', 'value_name')->toArray(),
            'interaction_level' => InteractionLevel::where('status', 'Active')->pluck('id', 'level_name')->toArray(),
            'parent_account' => AccountCompany::pluck('id', 'account_name')->toArray(),
            'end_user' => AccountCompany::pluck('id', 'account_name')->toArray(),
            'account_owner' => User::pluck('id', 'username')->toArray(),
        ];
    }

    /**
     * Import accounts from CSV file.
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
                    $errors[] = "Row {$rowNum}: {$result}";
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

        $tmp = tempnam(sys_get_temp_dir(), 'account_import_').'.csv';
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
            $accountName, $fieldTypeName, $sourceName, $segmentationName, $businessEntityName,
            $businessValueName, $interactionLevelName, $website, $phone, $description,
            $parentAccountName, $endUserName,
            $billStreet, $billCity, $billProvince, $billZip, $billCountry,
            $shipStreet, $shipCity, $shipProvince, $shipZip, $shipCountry,
            $ownerName,
        ] = $row;

        // --- Validation ---

        $required = [
            ['account_name', $accountName],
            ['field_type', $fieldTypeName],
            // ['account_source', $sourceName],
            // ['segmentation', $segmentationName],
            // ['business_entity', $businessEntityName],
            // ['business_value', $businessValueName],
        ];

        foreach ($required as [$field, $value]) {
            if ($value === '') {
                return "{$field} is required.";
            }
        }

        // Check account_name uniqueness
        if (AccountCompany::where('account_name', $accountName)->exists()) {
            return "account_name already in use: '{$accountName}'";
        }

        // --- Resolve lookups ---
        $lookupErrors = [];

        $fieldTypeId = $this->resolve('field_type', $fieldTypeName, $lookupErrors, 'field_type');
        $sourceId = $sourceName !== '' ? $this->resolve('account_source', $sourceName, $lookupErrors, 'account_source') : null;
        $segmentationId = $segmentationName !== '' ? $this->resolve('segmentation', $segmentationName, $lookupErrors, 'segmentation') : null;
        $businessEntityId = $businessEntityName !== '' ? $this->resolve('business_entity', $businessEntityName, $lookupErrors, 'business_entity') : null;
        $businessValueId = $businessValueName !== '' ? $this->resolve('business_value', $businessValueName, $lookupErrors, 'business_value') : null;

        $interactionLevelId = $interactionLevelName !== '' ? $this->resolve('interaction_level', $interactionLevelName, $lookupErrors, 'interaction_level') : null;
        $parentAccountId = $parentAccountName !== '' ? $this->resolve('parent_account', $parentAccountName, $lookupErrors, 'parent_account') : null;
        $endUserId = $endUserName !== '' ? $this->resolve('end_user', $endUserName, $lookupErrors, 'end_user') : null;
        $ownerId = $ownerName !== '' ? $this->resolve('account_owner', $ownerName, $lookupErrors, 'account_owner') : $userId;

        if (! empty($lookupErrors)) {
            return implode('; ', $lookupErrors);
        }

        // --- Insert ---
        try {
            AccountCompany::create([
                'account_name' => $accountName,
                'types_accounts_companies_id' => $fieldTypeId,
                'sources_id' => $sourceId,
                'segmentation_id' => $segmentationId,
                'business_entities_id' => $businessEntityId,
                'business_values_id' => $businessValueId,
                'interaction_levels_id' => $interactionLevelId,
                'website' => $website !== '' ? $website : null,
                'phone' => $phone !== '' ? $phone : null,
                'description' => $description !== '' ? $description : null,
                'parent_account_id' => $parentAccountId,
                'end_user' => $endUserId,
                'address_billing_street' => $billStreet !== '' ? $billStreet : null,
                'address_billing_city' => $billCity !== '' ? $billCity : null,
                'address_billing_province' => $billProvince !== '' ? $billProvince : null,
                'address_billing_postal_code' => $billZip !== '' ? $billZip : null,
                'address_billing_country' => $billCountry !== '' ? $billCountry : null,
                'address_shipping_street' => $shipStreet !== '' ? $shipStreet : null,
                'address_shipping_city' => $shipCity !== '' ? $shipCity : null,
                'address_shipping_province' => $shipProvince !== '' ? $shipProvince : null,
                'address_shipping_postal_code' => $shipZip !== '' ? $shipZip : null,
                'address_shipping_country' => $shipCountry !== '' ? $shipCountry : null,
                'account_owner_id' => $ownerId,
                'status' => 'Active',
            ]);

            return null; // success
        } catch (\Exception $e) {
            return 'Failed to save: '.$e->getMessage();
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
            'Field Type' => TypesAccountsCompany::where('status', 'Active')->pluck('type_name')->toArray(),
            'Account Source' => Source::where('status', 'Active')->pluck('source_name')->toArray(),
            'Segmentation' => Segmentation::where('status', 'Active')->pluck('segmentation_name')->toArray(),
            'Business Entity' => BusinessEntity::where('status', 'Active')->pluck('entity_name')->toArray(),
            'Business Value' => BusinessValue::where('status', 'Active')->pluck('value_name')->toArray(),
            'Interaction Level' => InteractionLevel::where('status', 'Active')->pluck('level_name')->toArray(),
            'Parent Account (account_name)' => AccountCompany::pluck('account_name')->toArray(),
            'End User (account_name)' => AccountCompany::pluck('account_name')->toArray(),
            'Account Owner (username)' => User::pluck('username')->toArray(),
        ];
    }

    private function resolve(string $key, string $name, array &$errors, string $label): ?int
    {
        $id = $this->lookups[$key][$name] ?? null;
        if ($id === null) {
            $valid = implode(', ', array_keys($this->lookups[$key]));
            $errors[] = "{$label} '{$name}' not found. Options: {$valid}";

            return null;
        }

        return $id;
    }
}
