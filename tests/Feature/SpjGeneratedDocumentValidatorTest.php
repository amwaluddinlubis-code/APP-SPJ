<?php

namespace Tests\Feature;

use App\Services\SpjGeneratedDocumentValidator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SpjGeneratedDocumentValidatorTest extends TestCase
{
    /** @var array<int,string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_valid_xlsx_final_output_is_openable_and_accepted(): void
    {
        $path = $this->xlsx('Selesai');
        $response = new BinaryFileResponse($path);

        app(SpjGeneratedDocumentValidator::class)->assertBinaryResponse(
            $response,
            'xlsx',
            'Rincian Belanja',
            'RINCIAN_BELANJA',
        );

        $this->addToAssertionCount(1);
    }

    public function test_unresolved_xlsx_marker_is_rejected_after_file_is_written(): void
    {
        $path = $this->xlsx('{{UNKNOWN_RELEASE_MARKER}}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UNKNOWN_RELEASE_MARKER');

        app(SpjGeneratedDocumentValidator::class)->assertFile(
            $path,
            'xlsx',
            'Rincian Belanja',
            'RINCIAN_BELANJA',
        );
    }

    public function test_corrupt_xlsx_package_is_rejected(): void
    {
        $path = $this->temporaryPath();
        file_put_contents($path, 'bukan-paket-office');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('paket Office tidak dapat dibuka');

        app(SpjGeneratedDocumentValidator::class)->assertFile($path, 'xlsx', 'Paket SPJ Excel');
    }

    public function test_valid_docx_is_accepted_and_unresolved_docx_is_rejected(): void
    {
        $valid = $this->docx('Dokumen selesai');
        app(SpjGeneratedDocumentValidator::class)->assertFile(
            $valid,
            'docx',
            'Checklist SPJ',
            'SPJ_CHECKLIST',
        );
        $this->addToAssertionCount(1);

        $unresolved = $this->docx('{{UNKNOWN_WORD_MARKER}}');

        try {
            app(SpjGeneratedDocumentValidator::class)->assertFile(
                $unresolved,
                'docx',
                'Checklist SPJ',
                'SPJ_CHECKLIST',
            );
            $this->fail('DOCX dengan placeholder unresolved seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('UNKNOWN_WORD_MARKER', $exception->getMessage());
        }
    }

    public function test_pdf_response_requires_pdf_signature_and_eof_marker(): void
    {
        $validator = app(SpjGeneratedDocumentValidator::class);
        $valid = new Response("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n", 200, ['Content-Type' => 'application/pdf']);

        $validator->assertPdfResponse($valid, 'Paket SPJ PDF');
        $this->addToAssertionCount(1);

        try {
            $validator->assertPdfContents("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n", 'Paket SPJ PDF');
            $this->fail('PDF tanpa EOF seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('penanda akhir PDF', $exception->getMessage());
        }

        try {
            $validator->assertPdfContents("bukan-pdf\n%%EOF", 'Paket SPJ PDF');
            $this->fail('Output tanpa signature PDF seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('signature PDF', $exception->getMessage());
        }
    }

    private function xlsx(string $value): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', $value);
        $path = $this->temporaryPath();
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    private function docx(string $value): string
    {
        $word = new PhpWord;
        $word->addSection()->addText($value);
        $path = $this->temporaryPath();
        WordIOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spj-output-validator-');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara untuk pengujian.');
        }

        $this->temporaryFiles[] = $path;

        return $path;
    }
}
