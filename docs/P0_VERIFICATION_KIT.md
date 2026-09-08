# P0 Verification Kit

Terakhir diperbarui: **2026-09-08**

Dokumen ini mendefinisikan alat verifikasi yang dipakai berulang selama P0 agar setiap perubahan tidak mengulang pemilihan fixture, test, build command, dan audit database dari awal.

## 1. Tujuan

Workflow canonical setelah perubahan source:

```text
ubah source
→ php artisan spj:verify
→ lihat checkpoint blocking pertama yang gagal
→ perbaiki
→ ulangi
```

Ketika database sekolah nyata tersedia:

```text
ubah source
→ php artisan spj:verify --npsn=10208183 --quarter=1
→ source/build/test verification
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

Factory hanya membangun **request payload**, tidak memasukkan row database. Test feature tetap bebas membuat transaction/package sesuai kebutuhan masing-masing tanpa menyalin struktur form kategori berulang-ulang.

Kontrak factory dijaga oleh `tests/Unit/SpjScenarioFactoryTest.php`.

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

Suite ini memusatkan regression release-safety: workflow dokumen kritis, numbering, safe sync ARKAS, security, JASA_LAINNYA reconciliation, SiPLah policy, maintenance linkage, ownership, package/source boundary, auditor read-only, audit diff, workspace migration, verification command, dan six-category scenario contract.

Regression P0 baru harus dimasukkan ke suite ini bila menyangkut release-safety. Test visual/browser-only tidak dipaksakan masuk PHPUnit.

---

## 4. Single command `spj:verify`

Command:

```powershell
php artisan spj:verify
```

Urutan default:

```text
Repository Pint --test   → advisory WARN bila style debt ditemukan
SPJ Critical PHPUnit     → blocking
npm run build            → blocking
php artisan view:cache   → blocking
real tenant audit        → RVR bila NPSN tidak diberikan / blocking bila diminta
```

Repository saat verification kit pertama dibuat masih memiliki style debt Pint tersebar di source lama maupun file yang baru disentuh. Karena formatting bukan release-safety blocker, Pint default dicatat sebagai **WARN** dan tidak menutup build/test. Debt tersebut tetap terlihat dan tidak dianggap PASS.

Jika ingin style menjadi gate penuh:

```powershell
php artisan spj:verify --strict-style
```

Opsi iterasi developer:

```powershell
php artisan spj:verify --skip-style
php artisan spj:verify --skip-build
php artisan spj:verify --skip-tests
```

`--skip-*` bukan konfigurasi release checkpoint normal.

### Dengan database nyata

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
```

Opsional:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1 --year=2026
php artisan spj:verify --npsn=10208183 --quarter=1 --year=2026 --fund-source=BOSP
```

Step terakhir memanggil auditor tenant read-only; tidak membuat, memigrasi, atau memperbaiki database.

---

## 5. Audit snapshot dan diff

Simpan baseline:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 `
  --output=storage/app/audits/10208183-tw1-before.json
```

Setelah patch:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 `
  --output=storage/app/audits/10208183-tw1-after.json
```

Bandingkan:

```powershell
php artisan spj:audit-diff `
  storage/app/audits/10208183-tw1-before.json `
  storage/app/audits/10208183-tw1-after.json `
  --fail-on-regression
```

Diff membandingkan summary, anomaly code, coverage/lifecycle kategori, dan kandidat E2E. Snapshot dari school/year/quarter/fund-source berbeda ditolak agar tidak menghasilkan delta palsu.

`--output` menulis laporan JSON, **bukan database tenant**. Query tenant tetap memakai `PRAGMA query_only=ON`.

---

## 6. GitHub CI

Workflow:

```text
.github/workflows/spj-critical.yml
```

Dijalankan pada push/PR `gui-standardization` dan `workflow_dispatch`.

Pipeline:

```text
checkout
→ PHP 8.3 + SQLite
→ Node 22
→ composer install
→ npm ci
→ Repository Pint --test (advisory)
→ npm build (blocking)
→ Blade view cache (blocking)
→ SPJ Critical tests (blocking)
```

Pint repository-wide sengaja `continue-on-error` untuk sementara agar style debt tidak mencegah kita melihat hasil build/test. CI hanya disebut **functional PASS** jika semua blocking gate hijau; bila Pint merah, status dicatat **PASS with style WARN**, bukan “all checks clean”.

CI tidak memakai database SDN 10208183 dan tidak menggantikan real-tenant P0-01.

---

## 7. Aturan penggunaan ke depan

Setiap patch P0:

```text
1. Tambah/perbarui regression test
2. Gunakan SpjScenarioFactory bila membutuhkan payload kategori
3. Masukkan regression release-safety ke SPJ Critical
4. Jalankan spj:verify
5. Simpan audit before/after bila mapping data berubah
6. Jalankan spj:audit-diff --fail-on-regression
7. Browser/document QA bila menyentuh UI/generator
8. Baru update status roadmap
```

Jangan membuat command verifikasi baru per milestone bila langkahnya dapat ditampung verification kit ini.

---

## 8. Checkpoint yang tetap membutuhkan laptop/data nyata

Tetap RVR sampai SDN 10208183 tersedia:

- P0-01 audit triwulan nyata;
- pemilihan enam kandidat nyata;
- E2E sampai FINAL;
- validasi isi Word/Excel/PDF dengan data nyata;
- backup/reset/restore database nyata;
- browser QA operator.

Saat laptop tersedia gunakan entry point canonical:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
```

kemudian simpan baseline audit untuk diff bila ada perbaikan berikutnya.
