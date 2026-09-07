# Migrasi Icon Canonical

Terakhir diverifikasi: **2026-09-07**

Aplikasi memakai `<x-ui.icon>` sebagai sumber icon canonical. Markup baru harus menggunakan `x-ui.icon` atau prop `icon` pada `<x-ui.button>` dan tidak menambah emoji/SVG action baru bila icon canonical sudah tersedia.

## Compatibility migration

View lama yang besar belum harus direwrite sekaligus. Untuk action operasional legacy, aplikasi memakai:

```text
resources/views/components/ui/icon-templates.blade.php
resources/js/legacy-action-icon-migrator.js
```

Template icon tetap dirender melalui `<x-ui.icon>`, lalu migrator hanya memasang clone icon canonical pada action lama berdasarkan label operator. Layer ini tidak mengubah route, method form, payload, validation, lifecycle, atau business rule.

Cakupan migrasi saat ini:

```text
Detail Transaksi
Paket SPJ
Buat/Buka Paket SPJ
Simpan / Edit / Tambah / Hapus
Download / Unduh / Cetak / Pratinjau PDF
Kembali / Semua Paket
Refresh / Muat Ulang / Sinkronisasi
Naik/Turun urutan
Lihat/Buka transaksi
Isi data
Sub-tab Rincian / Isian Manual / Kesiapan / Penomoran
Navigation action Lanjut/Berikutnya
```

Action yang sudah memiliki SVG/icon sendiri tidak diproses ulang. DOM baru dari Livewire juga diproses setelah `livewire:navigated` dan melalui MutationObserver.

## Arah akhir

Compatibility migrator adalah jembatan. Saat sebuah view disentuh secara substansial, action legacy di view tersebut sebaiknya dipindahkan langsung ke `<x-ui.button icon="...">` atau `<x-ui.icon>` dan tidak bergantung permanen pada migrator.

Checkpoint setelah perubahan icon:

```powershell
npm run build
php artisan view:cache --no-interaction
git diff --check
```
