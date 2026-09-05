<?php

namespace Tests\Unit;

use App\Services\ArkasPipePayload;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ArkasPipePayloadUtf8Test extends TestCase
{
    public function test_decode_normalizes_windows_1252_bytes_in_arkas_text_fields(): void
    {
        Log::spy();

        $output = "FIELDS|ID_KAS_UMUM|URAIAN|NAMA_TOKO\n"
            ."DATA|KAS-001|Belanja ".chr(0x96)." perlengkapan|Toko O".chr(0x92)."Brien\n";

        $records = ArkasPipePayload::decode($output);

        $this->assertCount(1, $records);
        $this->assertSame('Belanja – perlengkapan', $records[0]['URAIAN']);
        $this->assertSame('Toko O’Brien', $records[0]['NAMA_TOKO']);
        $this->assertTrue(mb_check_encoding($records[0]['URAIAN'], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($records[0]['NAMA_TOKO'], 'UTF-8'));

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_decode_preserves_valid_utf8_indonesian_text(): void
    {
        Log::spy();

        $output = "FIELDS|ID_KAS_UMUM|URAIAN|NAMA_TOKO\n"
            ."DATA|KAS-002|Pembelian buku Bahasa Indonesia|Toko Pendidikan Cemerlang\n";

        $records = ArkasPipePayload::decode($output);

        $this->assertSame('Pembelian buku Bahasa Indonesia', $records[0]['URAIAN']);
        $this->assertSame('Toko Pendidikan Cemerlang', $records[0]['NAMA_TOKO']);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_values_and_pairs_also_return_valid_utf8(): void
    {
        Log::spy();

        $values = ArkasPipePayload::values("NAMA_SEKOLAH|SD Negeri ".chr(0x96)." Contoh\n");
        $pairs = ArkasPipePayload::pairs("1|Sumber Dana ".chr(0x96)." Reguler\n");

        $this->assertSame('SD Negeri – Contoh', $values['NAMA_SEKOLAH']);
        $this->assertSame('Sumber Dana – Reguler', $pairs[0]['name']);
        $this->assertTrue(mb_check_encoding($values['NAMA_SEKOLAH'], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($pairs[0]['name'], 'UTF-8'));
    }
}
