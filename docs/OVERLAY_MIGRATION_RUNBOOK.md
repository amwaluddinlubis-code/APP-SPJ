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

Untuk keputusan manual unresolved, buat artefak read-only yang reproducible:

```powershell
php artisan spj:overlay-decision-support --school-id=1 --source-sql="D:\PC Data\Documents\spj.sqlite.sql"
```

Jika permission `storage` belum tersedia, gunakan `--output-dir` ke direktori
writable; input dump dan database tenant tetap hanya dibaca.

Command ini menghasilkan JSON detail dan CSV ringkas. Setiap ambiguous memuat
detail transaksi fresh, seluruh kandidat transaksi lama, alasan, dan `winner:
null`. Setiap unmatched memuat raw search context, kandidat manual yang hanya
berfungsi sebagai bahan pencarian, serta klasifikasi `possible_manual_lookup`
atau `truly_missing_or_unverified`. Command tidak menulis database tenant.

Jika keputusan sudah diberikan, mapping disimpan sebagai file eksplisit dengan
kolom `old_transaction_id`, `fresh_transaction_id`, `decision`, dan `reason`.
Validator wajib menolak old/fresh yang tidak ada, target duplikat, target yang
sudah matched deterministik, tenant berbeda, collision, serta mapping yang
tidak idempotent. Mapping belum boleh dieksekusi tanpa persetujuan eksplisit.

Input dapat berupa JSON seperti contoh di bawah atau CSV dengan header yang sama.

Format mapping JSON:

```json
{
  "mappings": [
    {
      "old_transaction_id": 123,
      "fresh_transaction_id": 456,
      "decision": "APPROVE",
      "reason": "manual review: ..."
    }
  ]
}
```

Validasi read-only dijalankan dengan:

```powershell
php artisan spj:validate-overlay-mapping --school-id=1 --source-sql="D:\PC Data\Documents\spj.sqlite.sql" --mapping="D:\path\mapping.json"
```

Database SQLite sementara untuk membaca SQL dump dibuat dengan `sqlite::memory:`;
perintah tidak memerlukan izin membuat file sementara di `storage`. Report juga
menyimpan `matched_mappings` (ID lama → ID fresh, alasan, confidence
`deterministic`) dan `unresolved` lengkap dengan candidate IDs, key, tanggal,
alasan, serta klasifikasi. Jika report masih memiliki `ambiguous` atau
`unmatched`, jangan jalankan `--execute`.

Migrasi bersifat additive pada overlay fresh. Kolom operator yang sudah terisi
di target tidak ditimpa; hanya nilai target yang kosong yang diisi dari ekspor
lama. Fakta ARKAS/BKU tidak ikut ditulis. Kandidat ambiguous dan unmatched
tetap berada di report untuk rekonsiliasi manual dan tidak dipaksa masuk.

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

Eksekusi setelah reset database pada 2026-09-20 menghasilkan 198 transaksi
deterministik, 493 item, dan 67 paket; 87 transaksi unmatched dan 44 ambiguous
ditahan. Sebanyak 682 field overlay kosong diisi tanpa menimpa field target yang
sudah terisi. Backup tenant dan report eksekusi tersimpan sebelum hasil selesai.

Dry-run rekonsiliasi 2026-09-21 pada tenant `10260756` kembali menghasilkan
198 matched, 87 unmatched, dan 44 ambiguous. Ke-44 ambiguous memiliki duplicate
`no_bukti` tanpa kecocokan tanggal unik; ke-87 unmatched tidak memiliki kandidat
deterministik pada `id_kas_umum`, `source_key`, `no_bukti+tanggal`, maupun
`no_bukti`. Keduanya tetap manual/source-audit dan belum boleh dieksekusi.
