<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentTemplateReplacementService
{
    /**
     * Persist a validated template without exposing the active record to a missing file.
     *
     * The previous file is intentionally retained until the school-database transaction
     * commits. If the database write fails, the newly stored file is removed and the
     * previous template remains fully usable.
     *
     * @param  array<int, string>  $applicableCategories
     */
    public function replace(
        int $fiscalYearId,
        string $documentType,
        string $name,
        UploadedFile $uploaded,
        string $extension,
        array $applicableCategories = [],
    ): DocumentTemplate {
        $newPath = $uploaded->storeAs(
            'document-templates/'.$fiscalYearId,
            'tpl_'.Str::uuid()->toString().'.'.$extension,
        );

        if (! is_string($newPath) || $newPath === '') {
            throw new RuntimeException('File template gagal disimpan.');
        }

        try {
            [$template, $oldPath] = DB::connection('school')->transaction(function () use (
                $fiscalYearId,
                $documentType,
                $name,
                $extension,
                $applicableCategories,
                $newPath,
            ): array {
                $existing = DocumentTemplate::query()
                    ->where([
                        'fiscal_year_id' => $fiscalYearId,
                        'document_type' => $documentType,
                        'format' => $extension,
                    ])
                    ->lockForUpdate()
                    ->first();

                $values = [
                    'name' => $name,
                    'file_path' => $newPath,
                    'applicable_categories' => $applicableCategories,
                    'is_active' => true,
                ];

                if ($existing) {
                    $oldPath = $existing->file_path;
                    $existing->fill($values)->save();

                    return [$existing, $oldPath];
                }

                $template = DocumentTemplate::query()->create([
                    'fiscal_year_id' => $fiscalYearId,
                    'document_type' => $documentType,
                    'format' => $extension,
                    ...$values,
                ]);

                return [$template, null];
            });
        } catch (Throwable $exception) {
            $this->deleteQuietly($newPath);

            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $newPath) {
            $this->deleteQuietly($oldPath);
        }

        return $template;
    }

    private function deleteQuietly(string $path): void
    {
        try {
            Storage::delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
