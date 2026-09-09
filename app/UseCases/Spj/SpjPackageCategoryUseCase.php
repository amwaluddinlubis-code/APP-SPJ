<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpjPackageCategoryUseCase
{
    public function switchCategory(string $packageId, Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'spj_category' => ['required', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI'],
        ]);

        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package
            || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $package->transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            $message = 'Paket dokumen tidak ditemukan pada konteks sekolah/tahun/sumber dana aktif.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])
                ->with('error', $message);
        }
        if (! $package->isEditable()) {
            $message = 'Paket sudah bernomor atau final. Buka kembali paket sebelum mengganti kategori SPJ.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $categoryChanged = $package->transaction->spj_category !== $data['spj_category'];
        $demotedForRevalidation = false;

        if ($categoryChanged) {
            DB::connection('school')->transaction(function () use ($package, $data, &$demotedForRevalidation): void {
                $demotedForRevalidation = $package->status === 'READY';

                $package->transaction->forceFill(['spj_category' => $data['spj_category']])->save();
                if ($demotedForRevalidation) {
                    $package->forceFill(['status' => 'DRAFT'])->save();
                }

                $description = 'Kategori paket '.$package->transaction->no_bukti.' diubah menjadi '.$data['spj_category'].'.';
                if ($demotedForRevalidation) {
                    $description .= ' Paket dikembalikan ke DRAFT untuk validasi ulang.';
                }

                app(OperationalAuditService::class)->record(
                    $package->transaction->fiscal_year_id,
                    'SPJ_PACKAGE',
                    $package->id,
                    'UBAH_KATEGORI',
                    $description,
                );
            });
        }

        $message = $demotedForRevalidation
            ? 'Kategori SPJ diperbarui. Paket dikembalikan ke DRAFT dan harus divalidasi ulang sebelum penomoran.'
            : 'Kategori SPJ diperbarui. Isian Manual dimuat sesuai kategori yang dipilih.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'package_id' => $package->id,
                'spj_category' => $data['spj_category'],
                'package_status' => $package->status,
            ]);
        }

        return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])
            ->with('success', $message);
    }
}
