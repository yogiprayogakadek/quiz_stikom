<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoadTestQuestionFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_soal_jaringan_dapat_diimpor_untuk_mode_serempak(): void
    {
        $admin = User::create([
            'nama' => 'Admin Load Test',
            'username' => 'admin-load-test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'admin-load-test-token',
        ]);
        $quiz = Quiz::create([
            'judul' => 'Simulasi Beban Jaringan Komputer',
            'created_by' => $admin->id,
        ]);
        $fixture = base_path('tests/load/questions-network-simulation.csv');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.quizzes.questions.import.store', $quiz), [
                'file' => new UploadedFile($fixture, basename($fixture), 'text/csv', null, true),
            ])
            ->assertRedirect(route('admin.quizzes.questions.import.result', $quiz))
            ->assertSessionHas('impor_hasil.berhasil', 20)
            ->assertSessionHas('impor_hasil.gagal', 0);

        $questions = $quiz->questions()->with('options')->orderBy('id')->get();

        $this->assertCount(20, $questions);
        $this->assertTrue($questions->every(fn ($question) => $question->tipe === 'pilihan_ganda'));
        $this->assertTrue($questions->every(fn ($question) => $question->bobot === 1));
        $this->assertTrue($questions->every(fn ($question) => $question->durasi_detik === 20));
        $this->assertTrue($questions->every(fn ($question) => $question->options->count() === 4));
        $this->assertTrue($questions->every(fn ($question) => $question->options->where('is_correct', true)->count() === 1));

        $correctIndexes = $questions
            ->map(fn ($question) => $question->options->values()->search(fn ($option) => $option->is_correct))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame([0, 1, 2, 3], $correctIndexes);
    }
}
