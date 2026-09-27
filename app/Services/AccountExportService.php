<?php

namespace App\Services;

use ZipArchive;

class AccountExportService
{
    public function export($accounts, array $headers): string
    {
        $filePath = storage_path('app/temp/account-export-'.uniqid().'.xlsx');
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings($headers, $accounts));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetData($headers, $accounts));

        $zip->close();

        return $filePath;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Account Export" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function sharedStrings(array $headers, $accounts): string
    {
        $strings = [];

        foreach ($headers as $h) {
            $strings[] = $h;
        }

        foreach ($accounts as $account) {
            foreach ($this->extractRow($account) as $val) {
                $strings[] = (string) $val;
            }
        }

        $unique = array_values(array_unique($strings));
        $count = count($unique);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">';

        foreach ($unique as $s) {
            $xml .= '<si><t>'.$this->escape($s).'</t></si>';
        }

        $xml .= '</sst>';

        return $xml;
    }

    private function sheetData(array $headers, $accounts): string
    {
        $cols = '';
        $colCount = count($headers);
        for ($i = 0; $i < $colCount; $i++) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="20" customWidth="1"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>';

        // Header row (style 1: bold white on blue)
        $xml .= '<row r="1">';
        foreach ($headers as $i => $h) {
            $ref = $this->colLetter($i).'1';
            $xml .= '<c r="'.$ref.'" s="1" t="inlineStr"><is><t>'.$this->escape($h).'</t></is></c>';
        }
        $xml .= '</row>';

        // Data rows (style 2: bottom border)
        foreach ($accounts as $rowIdx => $account) {
            $r = $rowIdx + 2;
            $xml .= '<row r="'.$r.'">';
            foreach ($this->extractRow($account) as $colIdx => $val) {
                $ref = $this->colLetter($colIdx).$r;
                $xml .= '<c r="'.$ref.'" s="2" t="inlineStr"><is><t>'.$this->escape((string) $val).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function extractRow(object $account): array
    {
        return [
            $account->account_name ?? '',
            $account->typesAccountsCompany?->type_name ?? '',
            $account->source?->source_name ?? '',
            $account->segmentation?->segmentation_name ?? '',
            $account->businessEntity?->entity_name ?? '',
            $account->businessValue?->value_name ?? '',
            $account->interactionLevel?->level_name ?? '',
            $account->website ?? '',
            $account->phone ?? '',
            $account->description ?? '',
            $account->parentAccount?->account_name ?? '',
            $account->endUserAccount?->account_name ?? '',
            $account->address_billing_street ?? '',
            $account->address_billing_city ?? '',
            $account->address_billing_province ?? '',
            $account->address_billing_postal_code ?? '',
            $account->address_billing_country ?? '',
            $account->address_shipping_street ?? '',
            $account->address_shipping_city ?? '',
            $account->address_shipping_province ?? '',
            $account->address_shipping_postal_code ?? '',
            $account->address_shipping_country ?? '',
            $account->accountOwner?->display_name ?? '',
            $account->status ?? 'Active',
            $account->created_at?->format('d M Y H:i') ?? '',
        ];
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}