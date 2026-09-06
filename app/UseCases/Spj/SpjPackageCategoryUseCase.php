<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjPackageCategoryUseCase
{
    public function switchCategory(string $packageId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spj_category' => ['required', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI'],
        ]);

        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package
            || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $package->transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])
                ->with('error', 'Paket dokumen tidak ditemukan pada konteks sekolah/tahun/sumber dana aktif.');
        }
        if (! $package->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Buka kembali paket sebelum mengganti kategori SPJ.');
        }

        if ($package->transaction->spj_category !== $data['spj_category']) {
            $package->transaction->forceFill(['spj_category' => $data['spj_category']])->save();
            app(OperationalAuditService::class)->record(
                $package->transaction->fiscal_year_id,
                'SPJ_PACKAGE',
                $package->id,
                'UBAH_KATEGORI',
                'Kategori paket '.$package->transaction->no_bukti.' diubah menjadi '.$data['spj_category'].'.'
            );
        }

        return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])
            ->with('success', 'Kategori SPJ diperbarui. Isian Manual dimuat ulang sesuai kategori yang dipilih.');
    }
}
