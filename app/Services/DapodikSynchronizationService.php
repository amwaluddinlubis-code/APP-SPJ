<?php

namespace App\Services;

use App\Models\DapodikConnection;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DapodikSynchronizationService
{
    public function test(DapodikConnection $connection): array
    {
        return collect(['getSekolah', 'getGtk', 'getPesertaDidik', 'getPengguna', 'getRombonganBelajar'])
            ->mapWithKeys(fn (string $endpoint) => [$endpoint => count($this->fetch($connection, $endpoint))])->all();
    }

    public function synchronize(DapodikConnection $connection): array
    {
        $gtk = $this->fetch($connection, 'getGtk');
        $students = $this->fetch($connection, 'getPesertaDidik');

        return DB::connection('school')->transaction(function () use ($connection, $gtk, $students): array {
            $employeeIds = [];
            foreach ($gtk as $row) {
                $normalized = $this->normalize($row['nama'] ?? '');
                $employee = filled($row['nuptk'] ?? null) ? Employee::where('nuptk', trim($row['nuptk']))->first() : null;
                $employee ??= Employee::where('normalized_name', $normalized)->first();
                $employee ??= new Employee;
                $employee->fill([
                    'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:'.($row['ptk_id'] ?? Str::uuid()), 'dapodik_id' => $row['ptk_id'] ?? null,
                    'name' => $row['nama'] ?? '-', 'normalized_name' => $normalized, 'nip' => $row['nip'] ?? null, 'nik' => $row['nik'] ?? null,
                    'nuptk' => $row['nuptk'] ?? null, 'gender' => $row['jenis_kelamin'] ?? null, 'employment_status' => $row['status_kepegawaian_id_str'] ?? null,
                    'staff_type' => $row['jenis_ptk_id_str'] ?? null, 'position' => $row['jabatan_ptk_id_str'] ?? null, 'birth_place' => $row['tempat_lahir'] ?? null,
                    'birth_date' => $row['tanggal_lahir'] ?? null, 'religion' => $row['agama_id_str'] ?? null, 'last_education' => $row['pendidikan_terakhir'] ?? null,
                    'last_study_field' => $row['bidang_studi_terakhir'] ?? null, 'rank_group' => $row['pangkat_golongan_terakhir'] ?? null,
                    'is_primary_school' => (bool) ($row['ptk_induk'] ?? false), 'is_active' => true, 'payload' => $row, 'last_synced_at' => now(),
                ])->save();
                $employeeIds[] = $employee->id;
            }
            Employee::where('source_type', 'DAPODIK')->whereNotIn('id', $employeeIds)->update(['is_active' => false]);

            $studentIds = [];
            foreach ($students as $row) {
                $normalized = $this->normalize($row['nama'] ?? '');
                $student = filled($row['nisn'] ?? null) ? Student::where('nisn', trim($row['nisn']))->first() : null;
                $student ??= Student::where('dapodik_id', $row['peserta_didik_id'] ?? '')->first();
                $student ??= new Student;
                $student->fill([
                    'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:'.($row['peserta_didik_id'] ?? Str::uuid()), 'dapodik_id' => $row['peserta_didik_id'] ?? null,
                    'name' => $row['nama'] ?? '-', 'normalized_name' => $normalized, 'nisn' => $row['nisn'] ?? null, 'nipd' => $row['nipd'] ?? null,
                    'nik' => $row['nik'] ?? null, 'gender' => $row['jenis_kelamin'] ?? null, 'birth_place' => $row['tempat_lahir'] ?? null,
                    'birth_date' => $row['tanggal_lahir'] ?? null, 'religion' => $row['agama_id_str'] ?? null, 'address' => $row['alamat_jalan'] ?? null,
                    'phone' => $row['nomor_telepon_seluler'] ?? ($row['nomor_telepon_rumah'] ?? null), 'email' => $row['email'] ?? null,
                    'father_name' => $row['nama_ayah'] ?? null, 'mother_name' => $row['nama_ibu'] ?? null, 'guardian_name' => $row['nama_wali'] ?? null,
                    'class_name' => $row['nama_rombel'] ?? null, 'class_id' => $row['rombongan_belajar_id'] ?? null, 'grade_level' => $row['tingkat_pendidikan_id'] ?? null,
                    'semester_id' => $row['semester_id'] ?? null, 'registration_type' => $row['jenis_pendaftaran_id_str'] ?? null,
                    'previous_school' => $row['sekolah_asal'] ?? null, 'school_entry_date' => $row['tanggal_masuk_sekolah'] ?? null,
                    'special_needs' => ! empty($row['kebutuhan_khusus']), 'child_order' => $row['anak_keberapa'] ?? null,
                    'height' => $row['tinggi_badan'] ?: null, 'weight' => $row['berat_badan'] ?: null, 'is_active' => true, 'payload' => $row, 'last_synced_at' => now(),
                ])->save();
                $studentIds[] = $student->id;
            }
            Student::where('source_type', 'DAPODIK')->whereNotIn('id', $studentIds)->update(['is_active' => false]);
            $connection->update(['last_synced_at' => now(), 'last_status' => 'SUCCESS', 'last_message' => count($gtk).' GTK dan '.count($students).' siswa disinkronkan.']);

            return ['employees' => count($gtk), 'students' => count($students)];
        });
    }

    private function fetch(DapodikConnection $connection, string $endpoint): array
    {
        $response = Http::acceptJson()->withToken($connection->token)->connectTimeout(3)->timeout(30)
            ->get(rtrim($connection->base_url, '/').'/WebService/'.$endpoint, ['npsn' => $connection->npsn]);
        if (! $response->successful()) {
            throw new RuntimeException("Dapodik {$endpoint} merespons HTTP {$response->status()}.");
        }
        $rows = $response->json('rows', []);

        return is_array($rows) && array_is_list($rows) ? $rows : (is_array($rows) && $rows !== [] ? [$rows] : []);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }
}
