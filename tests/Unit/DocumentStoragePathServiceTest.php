<?php

namespace Tests\Unit;

use App\Services\DocumentStoragePathService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentStoragePathServiceTest extends TestCase
{
    public function test_persist_report_stores_under_year_laporan_folder(): void
    {
        $service = app(DocumentStoragePathService::class);
        $source = tempnam(sys_get_temp_dir(), 'spjreport');
        file_put_contents($source, 'isi-laporan');

        $destination = $service->persistReport($source, 'REKAP-SPJ-2026.xlsx', 2026);

        $this->assertFileExists($destination);
        $this->assertSame('isi-laporan', file_get_contents($destination));
        $this->assertStringContainsString('2026'.DIRECTORY_SEPARATOR.'LAPORAN', $destination);

        @unlink($source);
        @unlink($destination);
    }

    public function test_persist_report_sanitizes_file_name(): void
    {
        $service = app(DocumentStoragePathService::class);
        $source = tempnam(sys_get_temp_dir(), 'spjreport');
        file_put_contents($source, 'x');

        $destination = $service->persistReport($source, 'Rekap/SPJ: 2026?.xlsx', 2026);

        $this->assertFileExists($destination);
        $this->assertStringNotContainsString('/', basename($destination));

        @unlink($source);
        @unlink($destination);
    }

    public function test_archive_report_pdf_writes_copy_and_download_streams_from_storage(): void
    {
        $service = app(DocumentStoragePathService::class);

        $archived = $service->archiveReportPdf('%PDF-1.4 contoh', 'REKAP-SPJ-2026.pdf', 2026);

        $this->assertNotNull($archived);
        $this->assertFileExists($archived);

        $fresh = tempnam(sys_get_temp_dir(), 'spjreport');
        file_put_contents($fresh, 'unduhan');
        $response = $service->downloadReportFile($fresh, 'REKAP-SPJ-2026.xlsx', 2026);

        $this->assertFileDoesNotExist($fresh);
        $this->assertFileExists($response->getFile()->getPathname());

        @unlink($archived);
        @unlink($response->getFile()->getPathname());
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('generated-documents');

        parent::tearDown();
    }
}
