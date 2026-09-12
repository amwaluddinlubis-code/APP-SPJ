# P0 Verification Kit

Terakhir diperbarui: **2026-09-12**

Dokumen ini mendefinisikan alat verifikasi release-safety yang dipakai berulang. Status release authoritative berada di `CURRENT_PROGRESS.md`.

## 1. Functional gate aktif

Evidence gate hidup di bagian ini. Dokumen lain wajib me-link ke sini, bukan menyalin checkpoint/angka secara terpisah.

Latest completed green source gate saat checkpoint dokumentasi ini dibuat:

```text
commit        : 32aa928671a94ec5bdbf78a7f86e8840c79cae5d
CI run        : 34694289853 (#468)
workflow      : SPJ Critical Verification
result        : SUCCESS
```

Blocking workflow #468:

```text
npm run build          -> PASS
php artisan view:cache -> PASS
SPJ Critical PHPUnit   -> PASS : 277 tests / 2067 assertions
Full Unit PHPUnit      -> PASS : 49 tests / 169 assertions
Full Feature PHPUnit   -> PASS : 352 tests / 2503 assertions
```

Perubahan template yang sudah tercakup gate #468:

```text
- Download Template XLSX per baris benar-benar menghasilkan tepat satu worksheet fisik
- worksheet lain dibuang dari copy download pada level OOXML, bukan hidden/veryHidden
- source/master tersimpan tidak dimutasi oleh download individu
- regression membuka ulang hasil download dan memverifikasi getSheetCount() === 1
- regression ZIP memastikan part worksheet yang tidak dipilih benar-benar tidak ada
- Cek Placeholder melakukan lookup read-only dan memakai resolver nilai generator yang sama
- placeholder lookup dapat memakai nomor Paket/SPJ, nomor dokumen turunan, atau No. Bukti
- placeholder lookup menjaga Fund Source context
- Master Template Terbaru dirakit saat download dari template XLSX aktif per document type canonical
- source master historis tidak dimutasi ketika template individu diperbarui
- update XLSX individu menggantikan versi document type tersebut pada master download berikutnya
- record lain yang berasal dari salinan master multi-sheet tetap menyumbangkan sheet canonical aktifnya
- nama dan urutan sheet output dinormalisasi mengikuti SpjDocumentTypeRegistry
- workbook hasil rakitan divalidasi ulang melalui SpjTemplatePackageImporter
- master parsial ditolak bila satu atau lebih template XLSX canonical aktif tidak tersedia
```

Focused regression `DocumentTemplateIndividualDownloadTest` pada gate #468 membuat source workbook multi-sheet dengan sheet canonical terpilih berada di posisi kedua. Output dibuka ulang dan harus mempunyai tepat satu sheet canonical; inspeksi ZIP memastikan part worksheet pertama/ketiga sudah hilang, sedangkan source tetap utuh. Dengan demikian `hidden`/`veryHidden` **tidak lagi diterima sebagai bukti single-template**.

`DocumentTemplateMasterExportTest` pada gate yang sama tetap meniru flow nyata `import master -> update satu XLSX -> download master terbaru`: source template yang tidak diubah berbentuk salinan penuh workbook master multi-sheet, sedangkan `RINCIAN_BELANJA` diganti oleh file XLSX individu. Hasil export memakai versi individu terbaru untuk Rincian Belanja, mempertahankan versi aktif untuk tipe lain, mempunyai sheet canonical lengkap, dan lolos validator paket yang sama dengan jalur import.

`DocumentTemplatePlaceholderInspectorTest` membuktikan lookup nilai aktual placeholder, pencarian melalui nomor Paket/dokumen/No. Bukti, isolasi Fund Source, dan contract AJAX pada halaman Pengaturan Template Dokumen.

Kontrak tersebut berstatus **FUNCTIONAL PASS**. Gate membuktikan struktur workbook single-download dapat dibaca ulang, sheet lain benar-benar tidak ada pada paket OOXML hasil download, serta master memenuhi kontrak re-import. Gate **belum** membuktikan visual fidelity pada Microsoft Excel/LibreOffice, print area, page break, header/footer, drawing, defined name/formula kompleks, atau hasil cetak. Area visual/document runtime tetap **RVR**.

Refactor canonical numbering registry yang sebelumnya sudah PASS tetap dipertahankan oleh gate #468, termasuk:

```text
- canonical numbering registry sebagai source of truth
- halaman Format Penomoran membaca registry
- halaman Penomoran Triwulan membaca registry
- policy/gate/order/event-date resolver membaca registry
- allocator membaca target relation/field/scope dari registry
- lifecycle FINAL/cancel/replacement membaca registry
- kompatibilitas behavior SPJ legacy tanpa kategori dipertahankan
- token {TW} tetap I/II/III/IV tanpa prefix TW. otomatis
```

Repository Pint tetap **advisory** (`continue-on-error: true`) pada workflow release saat ini. Pada run #468, command `php vendor/bin/pint --test` masih melaporkan **5 style issue repository-wide** pada file lama yang tidak terkait koreksi template:

```text
app/Console/Commands/TestIsolatedSpjCancellation.php
app/Console/Commands/TestIsolatedSpjNumbering.php
app/Console/Commands/TestIsolatedSpjQuarterRollback.php
app/Console/Commands/TestIsolatedSpjTailRollback.php
tests/Feature/SpjIsolatedQuarterRollbackCommandTest.php
```

Karena step tersebut advisory, workflow #468 tetap SUCCESS. Jangan menyatakan repository-wide Pint clean sampai lima issue tersebut benar-benar diperbaiki. `DocumentTemplateIndividualDownloadService.php` dan regression template yang berubah pada gate #468 tidak termasuk daftar issue Pint di atas.

Workflow `.github/workflows/spj-critical.yml` mengabaikan `docs/**` dan root `*.md`; dokumentasi-only commit setelah gate #468 tidak memicu checkpoint CI baru dan tidak menggantikan code gate `32aa928671a94ec5bdbf78a7f86e8840c79cae5d`.

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

Suite ini menjaga release-safety lintas fitur, termasuk six-category lifecycle, numbering, preview/download side-effect, safe sync, authorization/tenant boundary, maintenance, ARKAS importer, template upload/download, placeholder inspector, master template lifecycle, SiPLah, employee identity, quarter audit, ownership/workspace migration, reconciliation, dan lifecycle master template.

Nama/jumlah test dapat berubah. Jangan menyalin angka test lama sebagai status branch aktif bila tidak tersedia sebagai evidence verbatim.

Gate #468 menjadi evidence bahwa behavior existing tetap terjaga setelah koreksi true single-sheet download, penambahan placeholder inspector, dan lifecycle import-master/update-individu/download-master.

---

## 4. Canonical numbering registry verification

Source of truth metadata numbering:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Metadata canonical yang dimiliki registry:

```text
code
label
numbered
applicable_categories
channel
event_date_rule
number_target
scope_rule
```

Consumer yang wajib membaca registry/policy adapter dan tidak boleh mempunyai daftar domain numbering sendiri:

```text
DocumentNumberFormatController / halaman Format Penomoran
SpjNumberingUseCase / halaman Penomoran Triwulan
SpjNumberingPolicyService
SpjNumberingGateService
SpjNumberingOrderService
SpjDocumentNumberService
SpjDocumentLifecycleService
SpjDocumentLifecycleUseCase
SpjSingleNumberingUseCase
```

Current canonical numbered codes:

```text
SPJ
PESANAN
BAP
BAST
SPK
RAB
SURAT_TUGAS_PERJALANAN_DINAS
```

Daftar di atas adalah snapshot dokumentasi untuk membantu pembaca, **bukan source executable**. Source executable tetap registry. Jika registry berubah, consumer harus ikut secara dinamis dan dokumentasi ini diperbarui bila perubahan tersebut mengubah kontrak operator/domain.

`SpjDocumentTypeRegistry` bukan duplikat source numbering. Registry tersebut tetap menangani template/placeholder/output registry, sedangkan `SpjNumberingDocumentRegistry` menangani metadata domain penomoran.

---

## 5. Real-tenant audit — read-only

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

## 6. Read-only numbering preflight

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

## 7. Isolated mutation QA

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

## 8. Numbering token `{TW}`

Token canonical:

```text
{TW} -> I / II / III / IV
```

Prefix `TW.` tidak ditambahkan otomatis oleh renderer. Operator dapat menambahkan literal prefix dalam pattern bila dibutuhkan:

```text
{SEQ}/SPJ/{SCHOOL}/TW.{TW}/{YEAR}
```

Nomor lama yang sudah diterbitkan tidak dimutasi otomatis.

---

## 9. Audit snapshot dan diff

Jika perlu membandingkan before/after patch pada isolated copy:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/before.json
php artisan spj:audit-quarter <NPSN> --quarter=<Q> --output=storage/app/audits/after.json
php artisan spj:audit-diff storage/app/audits/before.json storage/app/audits/after.json --fail-on-regression
```

Snapshot dengan school/year/quarter/fund-source berbeda tidak boleh diperlakukan sebagai delta yang sama.

---

## 10. Browser dan document QA

CI/PHPUnit tidak membuktikan:

- browser interaction aktual;
- desktop/laptop layout aktual;
- mobile/tablet usability aktual;
- Word/Excel/PDF visual fidelity;
- single-template Office repair/fidelity pada workbook nyata;
- print area/page break/header/footer;
- hasil cetak fisik;
- installed Windows runtime.

Area tersebut tetap RVR/DEFERRED sesuai `CURRENT_PROGRESS.md`.

---

## 11. Verification workflow per perubahan

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

Untuk perubahan numbering metadata, mulai dari `SpjNumberingDocumentRegistry`; jangan menambahkan array kode/label/event-date/target baru di controller, Blade, gate, atau allocator.

---

## 12. Real-data rules

- original database immutable;
- audit original read-only;
- mutation hanya pada isolated copy;
- jangan membuat penerima/vendor/SPPD/template fiktif;
- jangan mengubah source transaction/item untuk memaksa PASS;
- numbering mengikuti canonical order dan berhenti pada blocker legitimate;
- absence of real category/data adalah coverage limitation, bukan alasan fabrikasi.

---

## 13. Current real-data focus

Fokus aktif sudah berpindah dari menambah smoke test numbering ke operator output QA:

- buka hasil Download Template individu pada Excel/LibreOffice dan pastikan satu worksheet tanpa repair prompt;
- buka Master Template Terbaru pada Excel/LibreOffice;
- gunakan Cek Placeholder untuk memeriksa nilai aktual tanpa upload berulang;
- generate dokumen nyata melalui aplikasi;
- BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, HONOR_PEGAWAI;
- JASA_LAINNYA multi-recipient output;
- PEMELIHARAAN bahan+upah output;
- SiPLah generated-document E2E bila applicable;
- browser QA desktop/laptop;
- official-template visual QA;
- employee identity/participant dan reconciliation saat ditemukan pada operator flow.
