<?php

namespace App\Services;

use App\Models\Division;
use App\Models\MasterProduct;

class ProductImportService
{
    private array $divisionLookup = [];

    private function resolveLookups(): void
    {
        // Build lowercase-keyed lookup for case-insensitive matching
        $divisions = Division::where('status', 'Active')
            ->select('id', 'division_name')
            ->get();

        $this->divisionLookup = [];
        foreach ($divisions as $div) {
            $this->divisionLookup[mb_strtolower(trim($div->division_name))] = $div->id;
        }
    }

    /**
     * Safely parse a price string into a float.
     * Handles:
     *   - US:    "15000", "15000.50", "15,000.50"
     *   - EU/ID: "15000,50", "15.000,50"
     *   - Mixed: "Rp 15.000,50", "Rp 15,000.50", "IDR 15,000"
     */
    private function parsePrice(string $value): float
    {
        $s = trim($value);
        if ($s === '') {
            return 0.00;
        }

        // Strip common currency prefixes (Rp, IDR, USD, $, EUR, etc.)
        $s = preg_replace('/^(Rp|IDR|USD|EUR|\$|£|¥)\s*/i', '', $s);
        $s = trim($s);

        // Keep only digits, dot, comma, and optional leading minus
        $s = preg_replace('/[^0-9.,-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.') {
            return 0.00;
        }

        $lastComma = strrpos($s, ',');
        $lastDot   = strrpos($s, '.');

        // Both separators present → the LAST one is the decimal marker
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                // comma is last → EU/ID format: remove all dots (thousand), replace comma with dot
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // dot is last → US format: remove all commas (thousand)
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            // Only commas present
            $after = substr($s, $lastComma + 1);
            $commaCount = substr_count($s, ',');

            if ($commaCount === 1 && preg_match('/^\d{1,2}$/', $after)) {
                // Single comma with 1-2 trailing digits → decimal separator
                $s = str_replace(',', '.', $s);
            } else {
                // Otherwise → thousand separator (remove all)
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastDot !== false) {
            // Only dots present
            $after = substr($s, $lastDot + 1);
            $dotCount = substr_count($s, '.');

            if ($dotCount === 1 && preg_match('/^\d{1,2}$/', $after)) {
                // Single dot with trailing digits → decimal separator, keep as-is
            } else {
                // Multiple dots or single dot with 3+ trailing digits → thousand separator(s)
                $s = str_replace('.', '', $s);
            }
        }

        return (float) $s;
    }

    /**
     * Normalise a string to null if empty.
     */
    private function nullIfEmpty(?string $value, int $maxLength = null): ?string
    {
        $v = trim($value ?? '');
        if ($v === '') {
            return null;
        }

        return $maxLength !== null ? mb_substr($v, 0, $maxLength) : $v;
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

        // Filter to candidates that actually appear
        $valid = array_filter($candidates, fn($count) => $count > 0);
        if (empty($valid)) {
            return ','; // fallback
        }

        // The delimiter with the most occurrences wins
        arsort($valid);
        return key($valid);
    }

    /**
     * Import products from CSV file.
     * Returns ['success' => N, 'failed' => N, 'errors' => [...]]
     */
    public function import(string $filePath): array
    {
        $this->resolveLookups();

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Cannot open file.']];
        }

        $success = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        // Read the first line to detect delimiter
        $firstLine = fgets($handle);
        if ($firstLine === false || trim($firstLine) === '') {
            fclose($handle);
            return ['success' => 0, 'failed' => 0, 'errors' => ['File is empty or invalid. CSV must have at least name and code columns.']];
        }

        // Strip BOM (Excel prepends \xEF\xBB\xBF to CSV exports)
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        $delimiter = $this->detectDelimiter($firstLine);

        // Parse header using detected delimiter
        // Rewind to re-read the header properly
        rewind($handle);
        // Re-read first line to get raw content (after rewind + BOM strip already consumed)
        $headers = fgetcsv($handle, 0, $delimiter);
        if (! $headers || count($headers) < 2) {
            fclose($handle);
            $display = $firstLine;
            if (mb_strlen($display) > 120) {
                $display = mb_substr($display, 0, 120) . '…';
            }
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [
                    "File is empty or invalid. CSV must have at least 'name' and 'code' columns. "
                    . "First line read: \"{$display}\" (detected delimiter: '{$delimiter}'). "
                    . "If using Excel, try re-saving your CSV with comma (,) as delimiter instead of semicolon (;).",
                ],
            ];
        }

        // Strip BOM from first header (already stripped from firstLine, but fgetcsv may re-include it)
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        // Normalise headers (trim, lowercase)
        $headers = array_map(fn($h) => mb_strtolower(trim($h)), $headers);

        // Validate that required headers exist
        $requiredFields = ['name', 'code'];
        $missingHeaders = [];
        foreach ($requiredFields as $field) {
            if (! in_array($field, $headers, true)) {
                $missingHeaders[] = $field;
            }
        }
        if (! empty($missingHeaders)) {
            fclose($handle);
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [
                    "CSV header is missing required column(s): ".implode(', ', $missingHeaders)
                    . ". Headers found: ".implode(', ', $headers)
                    . " (detected delimiter: '{$delimiter}').",
                ],
            ];
        }

        $lineNumber = 1; // header line

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            // Skip completely empty rows
            if (count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === '')) {
                continue;
            }

            // Pad row to match header count to avoid array_combine failure
            $row = array_pad($row, count($headers), '');

            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            if (! $data) {
                $failed++;
                $errors[] = "Line {$lineNumber}: Column count mismatch (expected ".count($headers).", got ".count($row).").";
                continue;
            }

            // Trim values
            $data = array_map(fn($v) => trim($v ?? ''), $data);

            // Validate required
            $missing = [];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (! empty($missing)) {
                $failed++;
                $errors[] = "Line {$lineNumber}: Missing required field(s): ".implode(', ', $missing);
                continue;
            }

            try {
                // Parse price
                $price = $this->parsePrice($data['price'] ?? '');

                // Parse status (case-insensitive)
                $status = 'Active';
                $statusInput = mb_strtolower(trim($data['status'] ?? ''));
                if ($statusInput === 'inactive') {
                    $status = 'Inactive';
                }

                // Resolve division name → id (case-insensitive)
                $divisionId = null;
                $divisionInput = trim($data['division'] ?? '');
                if ($divisionInput !== '') {
                    $divisionKey = mb_strtolower($divisionInput);
                    if (isset($this->divisionLookup[$divisionKey])) {
                        $divisionId = $this->divisionLookup[$divisionKey];
                    }
                }

                $record = [
                    'name' => mb_substr($data['name'], 0, 150),
                    'code' => mb_substr($data['code'], 0, 50),
                    'brand' => $this->nullIfEmpty($data['brand'] ?? '', 100),
                    'category' => $this->nullIfEmpty($data['category'] ?? '', 100),
                    'description' => $this->nullIfEmpty($data['description'] ?? ''),
                    'price' => $price,
                    'status' => $status,
                    'division_id' => $divisionId,
                ];

                $existing = MasterProduct::where('code', $data['code'])->first();

                if ($existing) {
                    $existing->update($record);
                    $updated++;
                } else {
                    MasterProduct::create($record);
                    $success++;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
            }
        }

        fclose($handle);

        return [
            'success' => $success,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    public static function getReferenceData(): array
    {
        $divisions = Division::where('status', 'Active')
            ->pluck('division_name')
            ->toArray();

        return [
            'division' => $divisions,
            'status' => ['Active', 'Inactive'],
        ];
    }
}
