<?php

namespace Tests\Unit;

use App\Services\SimpleXlsxService;
use Tests\TestCase;
use ZipArchive;

class SimpleXlsxServiceTest extends TestCase
{
    private SimpleXlsxService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SimpleXlsxService;
        $this->tempDir = sys_get_temp_dir().'/xlsx_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /**
     * Helper to create a zip file with given entries.
     */
    private function createZip(string $filename, array $entries): string
    {
        $path = $this->tempDir.'/'.$filename;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    /**
     * Test reading standard Excel file with sharedStrings, styles, inlineStr, numeric, str, and empty cells.
     */
    public function test_reads_excel_with_shared_strings_styles_and_various_cell_types(): void
    {
        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">'
            .'<si><t>Shared Text 1</t></si>'
            .'<si><r><t>Shared Rich Text 2</t></r></si>'
            .'</sst>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'</styleSheet>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'
            .'<row r="1">'
            .'<c r="A1" t="s"><v>0</v></c>' // shared string 0 -> "Shared Text 1"
            .'<c r="B1" t="inlineStr"><is><t>Inline Value</t></is></c>'
            .'<c r="C1"><v>95.5</v></c>' // numeric
            .'<c r="D1" t="str"><v>Formula String Result</v></c>'
            .'<c r="E1"/>' // empty cell
            .'</row>'
            .'<row r="2">'
            .'<c r="A2" t="s"><v>1</v></c>' // shared string 1 -> "Shared Rich Text 2"
            .'<c r="B2"><v>100</v></c>'
            .'</row>'
            .'</sheetData>'
            .'</worksheet>';

        $path = $this->createZip('excel_mock.xlsx', [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>',
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
            'xl/sharedStrings.xml' => $sharedStringsXml,
            'xl/styles.xml' => $stylesXml,
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ]);

        $rows = $this->service->readXlsxFile($path);

        $this->assertCount(2, $rows);
        $this->assertSame('Shared Text 1', $rows[0][0]);
        $this->assertSame('Inline Value', $rows[0][1]);
        $this->assertSame('95.5', $rows[0][2]);
        $this->assertSame('Formula String Result', $rows[0][3]);
        $this->assertSame('', $rows[0][4]);

        $this->assertSame('Shared Rich Text 2', $rows[1][0]);
        $this->assertSame('100', $rows[1][1]);
    }

    /**
     * Test rejecting XML with DOCTYPE or external entities (XXE).
     */
    public function test_rejects_xml_with_doctype(): void
    {
        $sheetXmlWithDoctype = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE worksheet ['
            .'<!ENTITY xxe SYSTEM "file:///etc/passwd">'
            .']>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>&xxe;</t></is></c></row>'
            .'</sheetData>'
            .'</worksheet>';

        $path = $this->createZip('xxe_attack.xlsx', [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/worksheets/sheet1.xml' => $sheetXmlWithDoctype,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DOCTYPE');
        $this->service->readXlsxFile($path);
    }

    /**
     * Test rejecting zip bomb (uncompressed size exceeds 10 MB).
     */
    public function test_rejects_zip_bomb_exceeding_10mb(): void
    {
        // Create an entry that expands to > 10MB (11MB of zeroes compresses to very few bytes)
        $largeData = str_repeat('A', 11 * 1024 * 1024);

        $path = $this->createZip('zip_bomb.xlsx', [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/worksheets/sheet1.xml' => $largeData,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('10 MB');
        $this->service->readXlsxFile($path);
    }

    /**
     * Test whitelist: only allowed entries are read, untrusted or dangerous entries are ignored.
     */
    public function test_only_whitelisted_entries_are_processed(): void
    {
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'
            .'<row r="1"><c r="A1"><v>42</v></c></row>'
            .'</sheetData>'
            .'</worksheet>';

        $path = $this->createZip('untrusted_entries.xlsx', [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
            'xl/malicious_script.php' => '<?php echo "evil"; ?>',
            '../../etc/passwd' => 'root:x:0:0:...',
        ]);

        $rows = $this->service->readXlsxFile($path);
        $this->assertCount(1, $rows);
        $this->assertSame('42', $rows[0][0]);
    }
}
