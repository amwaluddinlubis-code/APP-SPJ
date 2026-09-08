# P0 Verification Kit

Terakhir diperbarui: **2026-09-08**

Dokumen ini mendefinisikan alat verifikasi yang dipakai berulang selama P0 agar setiap perubahan tidak mengulang pemilihan fixture, test, build command, dan audit database dari awal.

## 1. Tujuan

Workflow canonical setelah perubahan source:

```text
ubah source
→ php artisan spj:verify
→ lihat checkpoint pertama yang gagal
→ perbaiki
→ ulangi
```

Ketika database sekolah nyata tersedia:

```text
ubah source
→ php artisan spj:verify --npsn=10208183 --quarter=1
→ static/build/test verification
→ audit tenant query-only
→ review kandidat/anomaly
```

Verification kit tidak menggantikan browser QA atau inspeksi output Word/Excel/PDF nyata.

---

## 2. Six-category scenario factory

File:

```text
tests/Support/SpjScenarioFactory.php
```

Factory menyediakan payload operator reusable untuk enam kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Contoh:

```php
$payload = SpjScenarioFactory::payload('JASA_LAINNYA', 1_250_000);
```

Factory hanya membangun **request payload**, tidak memasukkan row database. Ini disengaja supaya test feature dapat membuat transaction/package sesuai kebutuhan masing-masing tetapi tidak lagi menyalin struktur form kategori berulang-ulang.

Kontrak factory dijaga oleh:

```text
tests/Unit/SpjScenarioFactoryTest.php
```

Untuk kategori berbasis nilai, fixture memastikan rincian dasar merekonsiliasi ke gross yang diberikan. KONSUMSI memastikan `participant_count` sama dengan total porsi.

---

## 3. SPJ Critical test suite

`phpunit.xml` mempunyai suite canonical:

```text
SPJ Critical
```

Jalankan langsung:

```powershell
php artisan test --testsuite="SPJ Critical" --compact
```

Suite ini sengaja lebih kecil daripada seluruh Feature suite dan berisi checkpoint release-safety yang sudah ada, termasuk:

- critical document workflow;
- numbering;
- safe ARKAS synchronization;
- security hardening;
- JASA_LAINNYA reconciliation;
- SiPLah document policy;
- maintenance linkage workspace;
- ownership Detail Transaksi ↔ Paket SPJ;
- package/source boundary;
- quarter auditor read-only;
- audit snapshot diff;
- workspace migration;
- canonical six-category scenario contract.

Jika sebuah regression baru menyangkut release-safety P0, test regresinya harus dimasukkan ke suite ini. Test visual/browser-only tidak dipaksakan masuk PHPUnit.

---

## 4. Single command `spj:verify`

Command:

```powershell
php artisan spj:verify
```

Urutan default:

```text
Pint --test
→ SPJ Critical PHPUnit suite
→ npm run build
→ php artisan view:cache
→ real tenant audit = RVR bila NPSN tidak diberikan
```

Command berhenti pada checkpoint pertama yang FAIL supaya root cause tidak tertutup noise dari step berikutnya.

Opsi developer:

```powershell
php artisan spj:verify --skip-style
php artisan spj:verify --skip-build
php artisan spj:verify --skip-tests
```

Opsi tersebut hanya untuk iterasi lokal. Release checkpoint normal harus menjalankan semua static steps.

### Dengan database nyata

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
```

Opsional:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1 --year=2026
php artisan spj:verify --npsn=10208183 --quarter=1 --year=2026 --fund-source=BOSP
```

Step terakhir memanggil auditor tenant read-only. Ia tidak membuat/migrasi/memperbaiki database.

---

## 5. Audit snapshot dan diff

Untuk menghindari membaca audit dari nol setelah setiap patch, `spj:audit-quarter` dapat menyimpan JSON:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 `
  --output=storage/app/audits/10208183-tw1-before.json
```

Setelah perbaikan:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 `
  --output=storage/app/audits/10208183-tw1-after.json
```

Bandingkan:

```powershell
php artisan spj:audit-diff `
  storage/app/audits/10208183-tw1-before.json `
  storage/app/audits/10208183-tw1-after.json
```

Untuk verification gate:

```powershell
php artisan spj:audit-diff `
  storage/app/audits/10208183-tw1-before.json `
  storage/app/audits/10208183-tw1-after.json `
  --fail-on-regression
```

Diff membandingkan:

- jumlah transaksi/Paket;
- kategori belum ditetapkan;
- `item_description` kosong;
- mismatch finansial;
- CRITICAL/WARNING/INFO;
- anomaly code;
- coverage/lifecycle per kategori;
- perubahan kandidat E2E.

Snapshot hanya dapat dibandingkan bila school/context tahun/triwulan/sumber dana kompatibel. Ini mencegah delta palsu karena dua dataset berbeda.

`--output` menulis file laporan JSON, **bukan database tenant**. Query tenant tetap memakai `PRAGMA query_only=ON`.

---

## 6. GitHub CI

Workflow:

```text
.github/workflows/spj-critical.yml
```

Dijalankan pada push/PR branch `gui-standardization` dan manual `workflow_dispatch`.

Pipeline:

```text
checkout
→ PHP 8.3 + SQLite
→ Node 22
→ composer install
→ npm ci
→ Pint --test
→ npm build
→ Blade view cache
→ SPJ Critical tests
```

CI **tidak** memakai database SDN 10208183 dan tidak menggantikan real-tenant P0-01. Tujuannya adalah mencegah regression source/build yang seharusnya dapat ditemukan sebelum laptop/operator database diperlukan.

Status CI baru boleh disebut PASS setelah run GitHub Actions benar-benar selesai hijau.

---

## 7. Aturan penggunaan ke depan

Setiap patch P0 sebaiknya mengikuti pola:

```text
1. Tambah/perbarui regression test
2. Gunakan SpjScenarioFactory bila kategori membutuhkan form payload
3. Masukkan test release-safety baru ke SPJ Critical bila applicable
4. Jalankan spj:verify
5. Bila menyentuh data mapping, simpan audit before/after
6. Gunakan spj:audit-diff
7. Browser/document QA bila perubahan menyentuh UI/generator
8. Baru update status roadmap
```

Jangan membuat command verifikasi baru untuk setiap milestone bila langkahnya dapat dimasukkan ke kit ini.

---

## 8. Checkpoint yang tetap membutuhkan laptop/data nyata

Verification kit dapat disiapkan dan dijalankan di CI tanpa database sekolah, tetapi item berikut tetap RVR sampai SDN 10208183 tersedia:

- P0-01 audit triwulan nyata;
- pemilihan enam kandidat nyata;
- E2E sampai FINAL;
- validasi isi file Word/Excel/PDF dengan data nyata;
- backup/reset/restore database nyata;
- browser QA operator.

Saat laptop tersedia, entry point canonical bukan lagi rangkaian command manual. Gunakan:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
```

kemudian simpan baseline audit untuk diff bila ada perbaikan data/source berikutnya.
