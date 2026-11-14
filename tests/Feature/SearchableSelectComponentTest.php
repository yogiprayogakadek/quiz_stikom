<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SearchableSelectComponentTest extends TestCase
{
    public function test_komponen_merender_satu_kontrol_dengan_pencarian_di_dalam_dropdown(): void
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

        $this->assertStringNotContainsString('quiz_id-cari', $html);
        $this->assertStringContainsString('for="quiz_id"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="quiz_id"[^>]*value="7"/s', $html);
        $this->assertMatchesRegularExpression('/<button[\s\S]*?id="quiz_id"[\s\S]*?aria-haspopup="listbox"/', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="quiz_id-daftar"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
        $this->assertMatchesRegularExpression('/id="quiz_id"[\s\S]*?UTS Matematika \(12 soal\)[\s\S]*?<\/button>/', $html);

        $this->assertMatchesRegularExpression('/id="quiz_id-panel"[\s\S]*?id="quiz_id-pencarian"[\s\S]*?role="combobox"/', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringContainsString('data-search="uts matematika 12 soal"', $html);
        $this->assertMatchesRegularExpression('/data-nilai="7"[\s\S]*?aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/data-nilai="9"[\s\S]*?aria-disabled="true"/', $html);
        $this->assertStringContainsString('Tidak ada pilihan yang cocok', $html);
        $this->assertStringContainsString('id="quiz_id-help"', $html);
    }

    public function test_komponen_tanpa_pilihan_terpilih_menampilkan_pilihan_kosong_tanpa_wajib(): void
    {
        $html = Blade::render(
            '<x-searchable-select id="kelas_id" label="Kelas" placeholder="Pilih kelas" :options="$options" />',
            [
                'options' => [
                    ['value' => '', 'label' => 'Terbuka untuk semua peserta'],
                    ['value' => '3', 'label' => 'Kelas Pagi'],
                ],
            ],
        );

        $this->assertStringContainsString('name="kelas_id"', $html);
        $this->assertMatchesRegularExpression('/data-nilai=""[\s\S]*?aria-selected="true"/', $html);
        $this->assertStringNotContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('Terbuka untuk semua peserta', $html);
        $this->assertMatchesRegularExpression('/id="kelas_id-panel"[\s\S]*?placeholder="Cari kelas"/', $html);
    }
}
