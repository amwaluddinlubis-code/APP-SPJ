# APP-SPJ — Implementation Verification 2026-09-15

Dokumen ini mencatat verifikasi pekerjaan generator dokumen SPJ yang ditutup pada branch `gui-standardization` tanggal 2026-09-15. Catatan ini melengkapi `CURRENT_PROGRESS.md`, `TEMPLATE_MASTER_WORKFLOW.md`, dan `DOCUMENT_TEMPLATE_PLACEHOLDERS.md` sampai dokumen canonical tersebut diselaraskan pada maintenance pass berikutnya.

## Baseline terverifikasi

```text
CODE HEAD              : e1044a87652f934fb93c1f2c83ef23fe6051b54b
WORKFLOW                : SPJ Critical Verification
WORKFLOW RUN            : 34997146509
SPJ CRITICAL            : PASS
FULL UNIT               : PASS
FULL FEATURE            : PASS
RESULT                  : SUCCESS
```

Workflow canonical juga menjalankan Composer metadata validation, locked platform requirement check pada PHP 8.3, dependency installation dari committed lock, frontend build, Blade compile, dan Pint advisory check sebelum test suite.

## Perbaikan yang sudah masuk

### 1. Jasa Lainnya — inverse transaction hydration

`Transaction::serviceRecipients()` sekarang memakai inverse hydration (`chaperone()`) sehingga model `SpjServiceRecipient` yang diperoleh melalui relasi transaksi dapat memakai relasi `transaction` tanpa memicu lazy loading terlarang. Regression coverage ditambahkan untuk menjaga perilaku ini saat lazy loading prevention aktif.

Tujuan perbaikan: menutup kegagalan generate dokumen Jasa Lainnya dengan `LazyLoadingViolationException` pada relasi `SpjServiceRecipient::transaction`.

### 2. Konsumsi — peserta dirender sebagai repeating rows

Generator XLSX aktif merender peserta Konsumsi sebagai baris berulang, bukan lagi memadatkan seluruh peserta menjadi string multiline pada sel template.

Kontrak runtime:

- anchor row: `{{KONSUMSI_NO}}`;
- marker baris: `KONSUMSI_NO`, `KONSUMSI_NAMA`, `KONSUMSI_IDENTITAS`, `KONSUMSI_PORSI`, `KONSUMSI_HARGA_PORSI`, `KONSUMSI_JUMLAH`;
- satu peserta menghasilkan satu baris output;
- row renderer menggunakan mekanisme repeating-row yang sama untuk mempertahankan style/layout template dan menangani row template yang telah dialokasikan;
- `TOTAL_KONSUMSI` tetap scalar dan tidak menjadi bagian dari row expansion.

Commit implementasi utama:

```text
820214df145b71a45f700f3d206d86824511e184
fix: render consumption participants as repeating rows
```

Regression generated-document ikut ditambahkan agar output Konsumsi tidak kembali menjadi multiline participant block.

### 3. Preview PDF — deterministic cache fingerprint

Fingerprint cache preview paket tidak lagi bergantung pada bentuk `Model::toArray()` yang berubah sesuai relation loading state. Dependensi render dibuat eksplisit/deterministik, lalu regression cache memastikan:

- state render yang sama memakai cache hit;
- perubahan dependensi render meng-invalidasi cache;
- test memakai selector template runtime yang sebenarnya.

Commit utama dan follow-up test:

```text
38c54bea600387653bb679650856bd26051599c7
fix: make package preview cache fingerprint deterministic

538ff4b654739dcb4a86e493c74af122ce27262a
test: cover preview cache fingerprint invalidation

04dca2326f0202fbd68e965e0108dba89fc0caf9
test: verify package preview cache hit and invalidation

e1044a87652f934fb93c1f2c83ef23fe6051b54b
test: use real template selector for preview cache
```

### 4. Full Feature regressions

Regression yang sebelumnya membuat Full Feature suite merah sudah diselaraskan pada rangkaian commit menuju head di atas. Run `34997146509` membuktikan SPJ Critical, seluruh Unit suite, dan seluruh Feature suite selesai SUCCESS pada code head yang sama.

## Dampak dokumentasi template

Sampai dokumen canonical diselaraskan secara penuh, interpretasi yang benar untuk template XLSX adalah:

- `ITEM_*` = repeating row;
- `UPAH_*` = repeating row;
- `KONSUMSI_*` yang tercantum pada bagian peserta/daftar konsumsi = repeating row;
- `TOTAL_KONSUMSI` = scalar.

Placeholder inspector/metadata dapat tetap mengenali nama placeholder tersebut, tetapi generator XLSX final harus menghasilkan satu record peserta per baris dan tidak meninggalkan marker row yang belum ter-resolve.

## Status setelah verifikasi

```text
FUNCTIONAL CODE GATE     : PASS
JASA LAINNYA REGRESSION  : COVERED
KONSUMSI REPEATING ROW   : COVERED
PREVIEW CACHE REGRESSION : COVERED
GENERATED OUTPUT OFFICE  : RVR / operator visual QA masih diperlukan
FINAL RELEASE            : NOT YET
```

Green deterministic CI tidak menggantikan pemeriksaan visual output nyata di Microsoft Excel/LibreOffice/PDF viewer. P1 real-data/generated-document QA yang lebih luas tetap berjalan sesuai roadmap.
