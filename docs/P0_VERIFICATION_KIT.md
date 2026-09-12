# P0 Verification Kit

Terakhir diperbarui: **2026-09-12**

Dokumen ini mendefinisikan alat verifikasi release-safety yang dipakai berulang. Status release tidak ditentukan oleh dokumen ini; gunakan `CURRENT_PROGRESS.md` sebagai sumber utama.

## 1. Functional gate aktif

Evidence gate hidup di bagian ini. Dokumen lain wajib me-link ke sini, bukan menyalin checkpoint/angka gate.

CI code gate terbaru yang mencakup source branch `gui-standardization` sebelum perubahan dokumentasi-only ini:

```text
commit        : 3d52bdb251a8f41f41db1193b7b95b9e2f27ee3d
CI run        : 34661155452 (#372)
CI job        : 103463759747
workflow      : SPJ Critical Verification
job result    : SUCCESS
frontend build: PASS
Blade compile : PASS
SPJ Critical  : PASS
Full Unit     : PASS
Full Feature  : PASS
```

Jumlah test/assertion tidak disalin dari checkpoint lama bila tidak tersedia sebagai evidence verbatim pada metadata CI yang sedang diperiksa. Snapshot lama `261 tests / 1996 assertions` dan verifikasi lokal `265 tests / 2007 assertions` tetap merupakan evidence historis, tetapi bukan angka canonical untuk code gate aktif di atas.

Repository Pint tetap **ADVISORY** pada konfigurasi release sekarang. Workflow menjalankannya dengan `continue-on-error: true`, sedangkan gate berikut bersifat blocking:

```text
Repository Pint --test   -> ADVISORY
npm run build            -> BLOCKING
php artisan view:cache   -> BLOCKING
SPJ Critical PHPUnit     -> BLOCKING
Full Unit PHPUnit        -> BLOCKING di CI
Full Feature PHPUnit     -> BLOCKING di CI
```

Workflow `.github/workflows/spj-critical.yml` mengabaikan perubahan `docs/**` dan root `*.md`. Karena itu, perubahan dokumentasi-only tidak membuat checkpoint CI baru dan tidak dengan sendirinya membatalkan code gate terakhir. Jika ada commit source/code setelah gate CI di atas, gate tersebut tidak lagi canonical untuk source HEAD sampai CI hijau berikutnya tersedia.

## 2. Command canonical

Jalankan:

```powershell
php artisan spj:verify
```

Urutan default:

```text
Repository Pint --test
→ SPJ Critical PHPUnit
→ npm run build
→ php artisan view:cache
→ optional real-tenant audit
```

`spj:verify` adalah command canonical untuk verifikasi lokal/developer. GitHub Actions menambahkan full `Unit` dan full `Feature` suite sebagai blocking CI coverage di luar urutan default command tersebut.

Pint tetap advisory pada konfigurasi release sekarang. Bila style perlu dijadikan blocking gate:

```powershell
php artisan spj:verify --strict-style
```

Opsi iterasi developer:

```powershell
php artisan spj:verify --skip-style
php artisan spj:verify --skip-build
php artisan spj:verify --skip-tests
```

`--skip-*` tidak boleh dipakai sebagai evidence release final.

## 3. SPJ Critical suite

```powershell
php artisan test --testsuite="SPJ Critical" --compact
```

Suite ini mencakup release-safety lintas fitur, termasuk:

- six-category lifecycle E2E;
- numbering dan pre-numbering policy;
- preview/download side-effect guards;
- safe sync/reconciliation;
- authorization/tenant boundary;
- APP DATA maintenance hardening;
- Generic ARKAS Importer release safety;
- template upload validation/routing;
- SiPLah procurement policy;
- employee identity/unified master;
- quarter audit read-only;
- ownership/workspace migration;
- maintenance linkage;
- JASA_LAINNYA reconciliation.

Nama test dapat bertambah; daftar status authoritative tetap berada di `CURRENT_PROGRESS.md`.

## 4. Six-category scenario factory

File:

```text
tests/Support/SpjScenarioFactory.php
```

Factory menyediakan reusable request payload untuk:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Factory membantu deterministic tests dan tidak boleh dipakai untuk memalsukan real-data evidence.

## 5. Real-tenant audit

Dengan database sekolah nyata:

```powershell
php artisan spj:verify --npsn=<NPSN> --quarter=<Q>
```

Opsional:

```powershell
php artisan spj:verify --npsn=<NPSN> --quarter=<Q> --year=<TAHUN>
php artisan spj:verify --npsn=<NPSN> --quarter=<Q> --year=<TAHUN> --fund-source=<SUMBER_DANA>
```

Step audit tenant harus read-only. `spj:audit-quarter`:

- tidak membuat database tenant;
- tidak memigrasi database;
- memakai `PRAGMA query_only=ON`;
- tidak memperbaiki data otomatis;
- tidak boleh mengubah hash file atau metadata `SchoolDatabase`.

Regression utama:

```text
tests/Feature/SpjQuarterAuditCommandTest.php
tests/Feature/SpjQuarterAuditPolicyTest.php
```

## 6. Audit snapshot dan diff

Simpan baseline:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> \
  --output=storage/app/audits/before.json
```

Setelah patch pada isolated copy:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<Q> \
  --output=storage/app/audits/after.json
```

Bandingkan:

```powershell
php artisan spj:audit-diff \
  storage/app/audits/before.json \
  storage/app/audits/after.json \
  --fail-on-regression
```

Snapshot dengan school/year/quarter/fund-source berbeda tidak boleh dibandingkan sebagai delta yang sama.

## 7. Browser dan document QA

CI/PHPUnit tidak membuktikan:

- browser interaction aktual;
- layout desktop/laptop aktual;
- mobile verification;
- Word/Excel/PDF visual fidelity;
- print area/page break/header/footer;
- hasil cetak fisik;
- installed Windows runtime.

Area tersebut tetap RVR/DEFERRED sesuai `CURRENT_PROGRESS.md`.

## 8. Verification workflow per perubahan

Untuk perubahan source yang mempengaruhi release-safety:

```text
1. Tambah/perbarui regression test
2. Masukkan test kritis ke SPJ Critical bila applicable
3. Jalankan focused test
4. Jalankan spj:verify
5. Jika menyentuh mapping/source, audit tenant before/after
6. Jika menyentuh UI/generator, lakukan browser/document QA
7. Update CURRENT_PROGRESS hanya dengan evidence yang benar-benar tersedia
8. Update DEVELOPMENT_ROADMAP bila prioritas berubah
```

Jangan membuat checkpoint CI baru hanya karena dokumentasi berubah.

## 9. Real-data rules

- original database upload immutable;
- audit original read-only;
- mutation hanya pada isolated copy;
- jangan membuat penerima/vendor/SPPD/template fiktif;
- jangan mengubah source transaction/item untuk memaksa PASS;
- numbering harus mengikuti canonical order dan berhenti pada blocker legitimate;
- absence of real category/data adalah coverage limitation, bukan alasan fabrikasi.

## 10. Current real-data focus

Baseline aktif mempunyai 66 Paket SPJ tahun 2026 berstatus READY. Fokus penggunaan verification kit sekarang adalah:

- audit read-only seluruh package;
- klasifikasi blocker legitimate;
- JASA_LAINNYA multi-recipient output;
- PEMELIHARAAN bahan+upah output;
- SiPLah generated-document E2E;
- employee identity/participant real-data review;
- browser QA desktop/laptop;
- official-template RVR.
