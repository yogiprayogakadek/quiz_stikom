<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SearchableSelectComponentTest extends TestCase
{
    public function test_komponen_merender_pencarian_dan_pilihan_yang_dipilih(): void
    {
        $html = Blade::render(
            '<x-searchable-select id="quiz_id" name="quiz_id" label="Kuis" :options="$options" :selected="$selected" required help="Pilih kuis yang sudah punya soal." />',
            [
                'options' => [
                    ['value' => '', 'label' => 'Pilih kuis', 'search' => ''],
                    ['value' => '7', 'label' => 'UTS Matematika (12 soal)', 'search' => 'uts matematika 12 soal'],
                    ['value' => '9', 'label' => 'Kuis Kosong (0 soal) - belum ada soal', 'search' => 'kuis kosong 0 soal', 'disabled' => true],
                ],
                'selected' => '7',
            ],
        );

        $this->assertStringContainsString('for="quiz_id-cari"', $html);
        $this->assertStringContainsString('id="quiz_id-cari"', $html);
        $this->assertStringContainsString('type="search"', $html);
        $this->assertStringContainsString('for="quiz_id"', $html);
        $this->assertStringContainsString('name="quiz_id"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('data-search="uts matematika 12 soal"', $html);
        $this->assertMatchesRegularExpression('/value="7"\s+data-search="[^"]*"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="9"\s+data-search="[^"]*"\s+disabled/', $html);
        $this->assertStringContainsString('Tidak ada pilihan yang cocok', $html);
        $this->assertStringContainsString('id="quiz_id-help"', $html);
    }

    public function test_komponen_tanpa_pilihan_terpilih_tidak_menandai_opsi_mana_pun(): void
    {
        $html = Blade::render(
            '<x-searchable-select id="kelas_id" label="Kelas" :options="$options" />',
            [
                'options' => [
                    ['value' => '', 'label' => 'Terbuka untuk semua peserta'],
                    ['value' => '3', 'label' => 'Kelas Pagi'],
                ],
            ],
        );

        $this->assertStringContainsString('name="kelas_id"', $html);
        $this->assertDoesNotMatchRegularExpression('/value="3"\s+data-search="[^"]*"\s+selected/', $html);
        $this->assertStringNotContainsString('required', $html);
    }
}
