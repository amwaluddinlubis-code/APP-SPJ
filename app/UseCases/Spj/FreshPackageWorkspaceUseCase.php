<?php

namespace App\UseCases\Spj;

use App\Models\SpjFreshPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FreshPackageWorkspaceUseCase
{
    public function __construct(private readonly ActiveSpjContext $context) {}

    public function update(string $packageId, Request $request): RedirectResponse
    {
        $package = $this->packageForIdentifier($packageId);

        if (! $package) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket fresh tidak ditemukan pada konteks aktif.');
        }

        if (! in_array(strtoupper((string) $package->status), ['DRAFT', 'READY'], true)) {
            return back()->with('error', 'Paket yang sudah bernomor atau final tidak dapat diubah.');
        }

        $data = $request->validate([
            'spj_category' => ['required', 'string', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI'],
            'payment_description' => ['nullable', 'string', 'max:5000'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'receipt_recipient_name' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.item_description' => ['required', 'string', 'max:5000'],
        ]);

        DB::connection('school')->transaction(function () use ($package, $data): void {
            $transaction = $package->transaction()->lockForUpdate()->firstOrFail();
            $package->newQuery()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $transaction->update([
                'spj_category' => $data['spj_category'],
                'payment_description' => $data['payment_description'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'receipt_recipient_name' => $data['receipt_recipient_name'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $transaction->items()->whereKey($item['id'])->update([
                    'item_description' => $item['item_description'],
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('spj.index', ['tab' => 'paket', 'fresh_package_id' => $package->id])
            ->with('success', 'Isian Paket SPJ berhasil disimpan.');
    }

    private function package(string $packageId): ?SpjFreshPackage
    {
        return SpjFreshPackage::query()
            ->with(['transaction.items.rawMirrorRow', 'transaction.rawMirrorRow'])
            ->whereKey($packageId)
            ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))
            ->first();
    }

    private function packageForIdentifier(string $identifier): ?SpjFreshPackage
    {
        return $this->package($identifier);
    }
}
