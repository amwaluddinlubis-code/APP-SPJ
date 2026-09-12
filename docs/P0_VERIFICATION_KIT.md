# P0 Verification Kit

Terakhir diperbarui: **2026-09-12**

Dokumen ini mendefinisikan alat verifikasi release-safety yang dipakai berulang. Status release authoritative berada di `CURRENT_PROGRESS.md`.

## 1. Functional gate aktif

Evidence gate hidup di bagian ini. Dokumen lain wajib me-link ke sini, bukan menyalin checkpoint/angka secara terpisah.

Latest completed green source gate saat checkpoint dokumentasi ini dibuat:

```text
commit        : 0627ac45355453e138905cf0e81c235e6c5e2d2a
CI run        : 34666556040 (#386)
workflow      : SPJ Critical Verification
result        : SUCCESS
```

Source HEAD berikutnya:

```text
commit        : 68ab857dc698a1652e3b50233267e9ff64f40ba3
change        : remove automatic `TW.` prefix from `{TW}` numbering token
CI run        : 34667172478 (#387)
status        : IN PROGRESS saat checkpoint dokumentasi dibuat
```

Karena `68ab857...` adalah source change, gate `#386` tetap latest completed green gate tetapi belum menjadi canonical gate untuk source HEAD sampai `#387` selesai hijau.

Workflow blocking:

```text
npm run build          -> BLOCKING
php artisan view:cache -> BLOCKING
SPJ Critical PHPUnit   -> BLOCKING
Full Unit PHPUnit      -> BLOCKING
Full Feature PHPUnit   -> BLOCKING
```

Repository Pint tetap advisory (`continue-on-error: true`) pada konfigurasi release saat ini.

Workflow `.github/workflows/spj-critical.yml` mengabaikan `docs/**` dan root `*.md`; dokumentasi-only commit tidak memicu checkpoint CI baru.

---

## 2. Command verifikasi canonical

Untuk verifikasi lokal/developer umum:

```powershell
php artisan spj:verify
```

Urutan default:

```text
Repository Pint --test
-> SPJ Critical PHPUnit
-> npm run build
-> php artisan view:cache
-> optional real-tenant audit
```

GitHub Actions menambahkan full Unit dan full Feature suite sebagai blocking coverage.

Opsi iterasi developer tersedia, tetapi `--skip-*` bukan evidence release final:

```powershell
php artisan spj:verify --skip-style
php artisan spj:verify --skip-build
php artisan spj:verify --skip-tests
```

---

## 3. SPJ Critical suite

```powershell
php artisan test --testsuite="SPJ Critical" --compact
```

Suite ini menjaga release-safety lintas fitur, termasuk six-category lifecycle, numbering, preview/download side-effect, safe sync, authorization/tenant boundary, maintenance, ARKAS importer, template upload, SiPLah, employee identity, quarter audit, ownership/workspace migration, dan reconciliation.

Nama/jumlah test dapat berubah. Jangan menyalin angka test lama sebagai status branch aktif bila tidak tersedia sebagai evidence verbatim.

---

## 4. Real-tenant audit — read-only

Canonical command:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --year=<TAHUN> --fund-source=<SUMBER_DANA>
```

Kontrak:

- tidak provision tenant;
- tidak migrate/repair sebagai side effect;
- `PRAGMA query_only=ON`;
- tidak mengubah transaction/item/package;
- tidak mengubah metadata `SchoolDatabase`;
- hash baseline harus tetap sama.

### Evidence real-data aktif

Untuk school real-data yang sedang diverifikasi:

```text
2026 / TW2 / Fund Source 1 : 66 transaksi ber-item / 66 Paket READY -> PASS
2026 / TW2 / Fund Source 2 : 0 transaksi -> partition check PASS pada scope yang diuji
TW1/TW3/TW4 2026           : tidak mempunyai transaksi pada baseline aktif
```

SPPD 2026 tidak tersedia pada real data; jangan dibuat fiktif untuk coverage.

---

## 5. Read-only numbering preflight

Sebelum mutation numbering pada data nyata:

```powershell
php artisan spj:preflight-numbering <NPSN> \
  --year=<TAHUN> \
  --quarter=<Q> \
  --fund-source=<SUMBER_DANA>
```

Kontrak preflight:

- tenant database dipaksa query-only;
- tidak activate/provision/migrate;
- tidak membuat `QuarterNumberingRun`;
- tidak membuat format/sequence/nomor;
- SHA-256 baseline diverifikasi tidak berubah;
- canonical package order dipreview;
- validator numbering dijalankan tanpa mutation.

Evidence real-data saat ini:

```text
PREFLIGHT RESULT      : PASS
baseline hash         : UNCHANGED
number issued         : NONE
```

---

## 6. Isolated mutation QA

Mutation real-data hanya boleh dilakukan pada copy terisolasi, bukan baseline asli.

Command yang tersedia:

```powershell
php artisan spj:test-numbering-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-cancel-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-tail-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-quarter-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
```

Setiap command menolak baseline asli dan memverifikasi hash baseline tidak berubah.

### Evidence yang sudah PASS

```text
FIRST NUMBER
BPU01 -> sequence 1 / NUMBERED
baseline UNCHANGED

INDIVIDUAL CANCEL + RESERVE
BPU01 sequence 1 -> CANCELLED permanen
BPU02 -> sequence 2
baseline UNCHANGED

TAIL ROLLBACK
1,2,3 -> rollback from 2 -> checkpoint 1 -> sequence 2 dapat diterbitkan lagi
baseline UNCHANGED
```

### Belum menjadi real-data runtime evidence

`spj:test-quarter-rollback-copy` sudah mempunyai functional regression/command, tetapi isolated real-data runtime untuk quarter rollback belum dijalankan pada checkpoint ini. Dependency lintas quarter tetap functional PASS; real-data 2026 tidak mempunyai transaksi TW3/TW4 untuk membuktikan dependency tersebut tanpa fabrikasi data.

Quarter rollback real-data runtime tidak perlu dipaksakan jika tidak ada bug atau kebutuhan operator yang menuntutnya.

---

## 7. Numbering token `{TW}`

Mulai source commit `68ab857dc698a1652e3b50233267e9ff64f40ba3`:

```text
{TW} -> I / II / III / IV
```

Prefix `TW.` tidak lagi ditambahkan otomatis oleh renderer. Operator dapat menambahkan literal prefix dalam pattern bila dibutuhkan:

```text
{SEQ}/SPJ/{SCHOOL}/TW.{TW}/{YEAR}
```

Nomor lama yang sudah diterbitkan tidak dimutasi otomatis.

---

## 8. Audit snapshot dan diff

Jika perlu membandingkan before/after patch pada isolated copy:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/before.json
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/after.json
php artisan spj:audit-diff storage/app/audits/before.json storage/app/audits/after.json --fail-on-regression
```

Snapshot dengan school/year/quarter/fund-source berbeda tidak boleh diperlakukan sebagai delta yang sama.

---

## 9. Browser dan document QA

CI/PHPUnit tidak membuktikan:

- browser interaction aktual;
- desktop/laptop layout aktual;
- mobile/tablet usability aktual;
- Word/Excel/PDF visual fidelity;
- print area/page break/header/footer;
- hasil cetak fisik;
- installed Windows runtime.

Area tersebut tetap RVR/DEFERRED sesuai `CURRENT_PROGRESS.md`.

---

## 10. Verification workflow per perubahan

Gunakan pendekatan proporsional terhadap risiko dan bug yang benar-benar ditemukan:

```text
1. Reproduce masalah nyata
2. Perbaiki source/domain/UI pada scope yang tepat
3. Tambahkan focused regression hanya bila bug perlu dikunci
4. Jalankan focused test yang relevan
5. Untuk source release-safety, tunggu CI blocking hijau
6. Jika menyentuh real-data/source mapping, audit before/after secara read-only
7. Jika menyentuh UI/generator, verifikasi melalui operator/browser/document flow
8. Update CURRENT_PROGRESS dan ROADMAP dengan evidence aktual
```

Jangan membuat test/smoke test baru hanya untuk memperbesar coverage setelah kontrak sudah cukup dibuktikan.

---

## 11. Real-data rules

- original database immutable;
- audit original read-only;
- mutation hanya pada isolated copy;
- jangan membuat penerima/vendor/SPPD/template fiktif;
- jangan mengubah source transaction/item untuk memaksa PASS;
- numbering mengikuti canonical order dan berhenti pada blocker legitimate;
- absence of real category/data adalah coverage limitation, bukan alasan fabrikasi.

---

## 12. Current real-data focus

Fokus aktif sudah berpindah dari menambah smoke test numbering ke operator output QA:

- generate dokumen nyata melalui aplikasi;
- BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, HONOR_PEGAWAI;
- JASA_LAINNYA multi-recipient output;
- PEMELIHARAAN bahan+upah output;
- SiPLah generated-document E2E bila applicable;
- browser QA desktop/laptop;
- official-template visual QA;
- employee identity/participant dan reconciliation saat ditemukan pada operator flow.
