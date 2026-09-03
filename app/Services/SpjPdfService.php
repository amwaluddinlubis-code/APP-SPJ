<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SpjPdfService
{
    public function download(SpjPackage $package, School $school)
    {
        $transaction = $package->transaction;
        $transaction->loadMissing(['goods', 'items', 'workers', 'participants', 'travels']);
        if ($transaction->goods->isNotEmpty()) {
            $transaction->setRelation('items', $transaction->goods);
        }
        $year = FiscalYear::query()->findOrFail($transaction->fiscal_year_id);
        $profile = DB::connection('school')->table('school_profiles')->where('fiscal_year_id', $year->id)->first();
        $letterhead = $this->letterheadDataUri($school->letterhead_path);
        $pdf = Pdf::loadView('spj-documents.pdf.package', compact('package', 'transaction', 'year', 'school', 'profile', 'letterhead'))
            ->setPaper('a4', 'portrait');

        $fileName = 'PAKET-SPJ-'.$this->safeFileName($package->document_number).'.pdf';
        $response = $pdf->download($fileName);

        $package->forceFill(['generated_at' => now(), 'status' => 'DICETAK'])->save();

        return $response;
    }

    private function letterheadDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }
        $absolutePath = $disk->path($path);
        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }

    private function safeFileName(?string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $value ?: 'DRAFT') ?: 'DRAFT';
    }
}
