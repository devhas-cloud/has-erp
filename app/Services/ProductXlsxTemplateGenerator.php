<?php

namespace App\Services;

use App\Models\Division;

class ProductXlsxTemplateGenerator
{
    private array $headers = [
        'name', 'code', 'brand', 'category', 'division',
        'description', 'price', 'status',
    ];

    private array $exampleRow = [
        'Laptop ThinkPad X1', 'PROD-001', 'Lenovo', 'Electronics', 'IT',
        'High-performance business laptop', '15000000', 'Active',
    ];

    public function generate(array $references, string $filePath): void
    {
        $zip = new \ZipArchive;
        $zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetDataProduct());
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheetReferences($references));

        $zip->close();
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'.
            '</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets>'.
            '<sheet name="Data Product" sheetId="1" r:id="rId1"/>'.
            '<sheet name="Daftar Referensi" sheetId="2" r:id="rId2"/>'.
            '</sheets>'.
            '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'.
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'.
            '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'.
            '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<fonts count="2">'.
            '<font><sz val="11"/><name val="Calibri"/></font>'.
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'.
            '</fonts>'.
            '<fills count="3">'.
            '<fill><patternFill patternType="none"/></fill>'.
            '<fill><patternFill patternType="gray125"/></fill>'.
            '<fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/><bgColor indexed="64"/></patternFill></fill>'.
            '</fills>'.
            '<borders count="2">'.
            '<border><left/><right/><top/><bottom/><diagonal/></border>'.
            '<border><left/><right/><top/><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'.
            '</borders>'.
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'.
            '<cellXfs count="3">'.
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'.
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'.
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'.
            '</cellXfs>'.
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'.
            '</styleSheet>';
    }

    private function sharedStrings(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="1" uniqueCount="1">'.
            '</sst>';
    }

    private function sheetDataProduct(): string
    {
        $cols = [];
        foreach ($this->headers as $i => $h) {
            $cols[] = '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.($i < 2 ? 20 : 16).'" customWidth="1"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<cols>'.implode('', $cols).'</cols>'.
            '<sheetData>';

        // Header row (style 1: bold white on blue)
        $xml .= '<row r="1">';
        foreach ($this->headers as $i => $h) {
            $ref = $this->colLetter($i).'1';
            $xml .= '<c r="'.$ref.'" s="1" t="inlineStr"><is><t>'.$this->escape($h).'</t></is></c>';
        }
        $xml .= '</row>';

        // Example row (style 2: bottom border)
        $xml .= '<row r="2">';
        foreach ($this->exampleRow as $i => $v) {
            $ref = $this->colLetter($i).'2';
            $xml .= '<c r="'.$ref.'" s="2" t="inlineStr"><is><t>'.$this->escape($v).'</t></is></c>';
        }
        $xml .= '</row>';

        // Empty row 3 for user data area
        $xml .= '<row r="3">';
        foreach ($this->headers as $i => $h) {
            $ref = $this->colLetter($i).'3';
            $xml .= '<c r="'.$ref.'" s="2" t="inlineStr"><is><t></t></is></c>';
        }
        $xml .= '</row>';

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function sheetReferences(array $references): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="60" customWidth="1"/></cols>'.
            '<sheetData>';

        // Header
        $xml .= '<row r="1">';
        $xml .= '<c r="A1" s="1" t="inlineStr"><is><t>Field</t></is></c>';
        $xml .= '<c r="B1" s="1" t="inlineStr"><is><t>Nilai yang Valid</t></is></c>';
        $xml .= '</row>';

        $row = 2;
        foreach ($references as $field => $values) {
            $xml .= '<row r="'.$row.'">';
            $xml .= '<c r="A'.$row.'" s="2" t="inlineStr"><is><t>'.$this->escape($field).'</t></is></c>';
            $xml .= '<c r="B'.$row.'" s="2" t="inlineStr"><is><t>'.$this->escape(is_array($values) ? implode(', ', $values) : (string) $values).'</t></is></c>';
            $xml .= '</row>';
            $row++;
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
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
