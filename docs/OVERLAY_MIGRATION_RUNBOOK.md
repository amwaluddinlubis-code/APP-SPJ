# Runbook Migrasi Overlay Operator

Dokumen ini menjelaskan migrasi data operator dari ekspor SQL database lama ke
tabel `spj_fresh_*` pada database tenant. Database ARKAS/raw mirror tidak ditulis.

## Perintah

Jalankan dari root project:

```powershell
php artisan spj:migrate-overlay --school-id=1 --source-sql="D:\PC Data\Documents\spj.sqlite.sql"
php artisan spj:migrate-overlay --school-id=1 --source-sql="D:\PC Data\Documents\spj.sqlite.sql" --execute
```

Perintah pertama hanya dry-run. Perintah kedua membuat backup tenant sebelum
menulis. Hasil pemetaan dan jumlah unmatched/ambiguous disimpan di
`storage/app/overlay-migration-reports/{npsn}`.

## Aturan pencocokan

Prioritas identity adalah `id_kas_umum`, `source_key`, `no_bukti + tanggal`,
lalu `no_bukti` jika hanya ada satu kandidat. Kandidat ganda tidak dipaksa
masuk. Yang dipindahkan hanya overlay operator: uraian pembayaran, metode dan
referensi pembayaran, penerima kuitansi, kategori, rekonsiliasi, uraian item,
serta lifecycle Paket SPJ dan nomor dokumen.

## Bukti eksekusi SMP Negeri 2 Ranto Baek

Pada 2026-09-18, ekspor `D:\PC Data\Documents\spj.sqlite.sql` diproses untuk
NPSN `10260756` (school id `1`): 36 transaksi cocok, 64 item, 36 paket, 73
baris tidak cocok, dan 0 ambiguous. Backup dibuat sebelum penulisan di tenant
`storage/app/school-databases/10260786/spj.sqlite`.

Database sumber lama dan file ekspor tidak diubah. Untuk dua sekolah berikutnya,
ulang dry-run dengan `--school-id` dan file ekspor sekolah masing-masing; jangan
menyalin database tenant sekolah lain.
