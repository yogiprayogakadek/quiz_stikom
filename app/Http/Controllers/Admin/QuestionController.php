<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionController extends Controller
{
    public function index(Request $request, ?Quiz $quiz = null): View
    {
        $cari = trim((string) $request->query('q'));

        $query = $quiz ? $quiz->questions() : Question::query();

        $questions = $query->withCount('options')
            ->when($cari !== '', fn ($q) => $q->where('teks', 'like', "%{$cari}%"))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $terkunci = Question::whereIn('id', $questions->pluck('id'))
            ->get()
            ->filter(fn (Question $q) => $q->isLocked())
            ->pluck('id')
            ->all();

        return view('admin.questions.index', compact('questions', 'cari', 'terkunci', 'quiz'));
    }

    public function create(?Quiz $quiz = null): View
    {
        return view('admin.questions.create', compact('quiz'));
    }

    public function store(StoreQuestionRequest $request, ?Quiz $quiz = null): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $quiz) {
            $question = Question::create([
                'quiz_id' => $quiz?->id,
                'teks' => $data['teks'],
                'tipe' => $data['tipe'],
                'bobot' => $data['bobot'],
                'durasi_detik' => $data['durasi_detik'],
                'pembahasan' => $data['pembahasan'] ?? null,
                'kunci_essay' => $data['tipe'] === 'essay' ? ($data['kunci_essay'] ?? null) : null,
            ]);

            if ($data['tipe'] === 'pilihan_ganda') {
                $this->simpanOpsi($question, $data['opsi'], (int) $data['correct']);
            }
        });

        if ($quiz) {
            return redirect()->route('admin.quizzes.questions.index', $quiz)->with('sukses', 'Soal kuis ditambahkan.');
        }

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Soal ditambahkan.');
    }

    public function edit(?Quiz $quiz, Question $question): View|RedirectResponse
    {
        // Parameter normalization if called as single resource
        if ($question->id === null && $quiz instanceof Question) {
            $question = $quiz;
            $quiz = $question->quiz;
        }

        if ($quiz && (int) $question->quiz_id !== (int) $quiz->id) {
            abort(404);
        }

        if ($question->isLocked()) {
            $target = $quiz ? redirect()->route('admin.quizzes.questions.index', $quiz) : redirect()->route('admin.quizzes.index');
            return $target->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa diedit.']);
        }

        $question->load('options');

        return view('admin.questions.edit', compact('question', 'quiz'));
    }

    public function update(UpdateQuestionRequest $request, ?Quiz $quiz, Question $question): RedirectResponse
    {
        if ($question->id === null && $quiz instanceof Question) {
            $question = $quiz;
            $quiz = $question->quiz;
        }

        if ($quiz && (int) $question->quiz_id !== (int) $quiz->id) {
            abort(404);
        }

        if ($question->isLocked()) {
            $target = $quiz ? redirect()->route('admin.quizzes.questions.index', $quiz) : redirect()->route('admin.quizzes.index');
            return $target->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa diedit.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'teks' => $data['teks'],
                'tipe' => $data['tipe'],
                'bobot' => $data['bobot'],
                'durasi_detik' => $data['durasi_detik'],
                'pembahasan' => $data['pembahasan'] ?? null,
                'kunci_essay' => $data['tipe'] === 'essay' ? ($data['kunci_essay'] ?? null) : null,
            ]);

            $question->options()->delete();

            if ($data['tipe'] === 'pilihan_ganda') {
                $this->simpanOpsi($question, $data['opsi'], (int) $data['correct']);
            }
        });

        if ($quiz) {
            return redirect()->route('admin.quizzes.questions.index', $quiz)->with('sukses', 'Soal diperbarui.');
        }

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Soal diperbarui.');
    }

    public function destroy(?Quiz $quiz, Question $question): RedirectResponse
    {
        if ($question->id === null && $quiz instanceof Question) {
            $question = $quiz;
            $quiz = $question->quiz;
        }

        if ($quiz && (int) $question->quiz_id !== (int) $quiz->id) {
            abort(404);
        }

        if ($question->isLocked()) {
            $target = $quiz ? redirect()->route('admin.quizzes.questions.index', $quiz) : redirect()->route('admin.quizzes.index');
            return $target->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa dihapus.']);
        }

        $question->delete();

        if ($quiz) {
            return redirect()->route('admin.quizzes.questions.index', $quiz)->with('sukses', 'Soal dihapus.');
        }

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Soal dihapus.');
    }

    private function simpanOpsi(Question $question, array $opsi, int $correct): void
    {
        foreach ($opsi as $index => $item) {
            $question->options()->create([
                'teks' => $item['teks'],
                'is_correct' => $index === $correct,
            ]);
        }
    }

    public function importForm(?Quiz $quiz = null): View
    {
        $quizzes = Quiz::orderBy('judul')->get();

        return view('admin.questions.import', compact('quizzes', 'quiz'));
    }

    public function import(Request $request, ?Quiz $quiz = null): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'quiz_id' => ['nullable', 'exists:quizzes,id'],
        ], [
            'file.required' => 'Pilih file CSV terlebih dahulu.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
            'quiz_id.exists' => 'Kuis yang dipilih tidak ditemukan.',
        ]);

        if (! $quiz && $request->filled('quiz_id')) {
            $quiz = Quiz::find($request->quiz_id);
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'File CSV kosong.']);
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $header);

        $cariKolom = function (array $aliases) use ($header): int|false {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $header, true);
                if ($idx !== false) {
                    return $idx;
                }
            }
            return false;
        };

        $colTeks = $cariKolom(['teks', 'soal', 'pertanyaan']);
        $colTipe = $cariKolom(['tipe', 'jenis']);
        $colBobot = $cariKolom(['bobot', 'nilai_maksimal', 'poin']);
        $colA = $cariKolom(['opsi_a', 'pilihan_a', 'opsi a', 'a']);
        $colB = $cariKolom(['opsi_b', 'pilihan_b', 'opsi b', 'b']);
        $colC = $cariKolom(['opsi_c', 'pilihan_c', 'opsi c', 'c']);
        $colD = $cariKolom(['opsi_d', 'pilihan_d', 'opsi d', 'd']);
        $colE = $cariKolom(['opsi_e', 'pilihan_e', 'opsi e', 'e']);
        $colF = $cariKolom(['opsi_f', 'pilihan_f', 'opsi f', 'f']);
        $colKunci = $cariKolom(['jawaban_benar', 'kunci', 'kunci_jawaban', 'jawaban']);
        $colKunciEssay = $cariKolom(['kunci_essay', 'kunci esai', 'jawaban_essay']);
        $colDurasi = $cariKolom(['durasi_detik', 'durasi']);
        $colPembahasan = $cariKolom(['pembahasan', 'penjelasan']);

        if ($colTeks === false) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Header CSV minimal harus memuat kolom teks.',
            ]);
        }

        $hasil = [];
        $baris = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $baris++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $teks = trim((string) ($row[$colTeks] ?? ''));
            $tipeRaw = $colTipe !== false ? Str::lower(trim((string) ($row[$colTipe] ?? ''))) : '';
            $tipe = match ($tipeRaw) {
                '', 'pilihan_ganda', 'pilihan ganda', 'pg' => 'pilihan_ganda',
                'essay', 'esai' => 'essay',
                default => null,
            };
            $bobotRaw = $colBobot !== false ? trim((string) ($row[$colBobot] ?? '')) : '';
            $bobot = ($bobotRaw !== '' && is_numeric($bobotRaw)) ? (int) $bobotRaw : 1;
            $kunci = $colKunci !== false ? trim((string) ($row[$colKunci] ?? '')) : '';
            $kunciEssay = $colKunciEssay !== false ? trim((string) ($row[$colKunciEssay] ?? '')) : '';
            $durasiRaw = $colDurasi !== false ? trim((string) ($row[$colDurasi] ?? '')) : '';
            $durasi = ($durasiRaw !== '' && is_numeric($durasiRaw)) ? (int) $durasiRaw : 30;
            $pembahasan = $colPembahasan !== false ? trim((string) ($row[$colPembahasan] ?? '')) : null;

            $rawOptions = [];
            $optionColumns = [
                'A' => $colA,
                'B' => $colB,
                'C' => $colC,
                'D' => $colD,
                'E' => $colE,
                'F' => $colF,
            ];

            foreach ($optionColumns as $huruf => $colIdx) {
                if ($colIdx !== false && isset($row[$colIdx])) {
                    $val = trim((string) $row[$colIdx]);
                    if ($val !== '') {
                        $rawOptions[$huruf] = $val;
                    }
                }
            }

            $alasan = null;
            $correctIndex = null;

            if ($tipe === null) {
                $alasan = "Tipe soal '{$tipeRaw}' tidak dikenali. Gunakan pilihan_ganda atau essay.";
            } elseif ($teks === '') {
                $alasan = 'Teks soal kosong.';
            } elseif (mb_strlen($teks) > 2000) {
                $alasan = 'Teks soal melebihi 2000 karakter.';
            } elseif ($bobot < 1 || $bobot > 1000) {
                $alasan = 'Bobot soal harus antara 1 sampai 1000.';
            } elseif ($durasi < 5 || $durasi > 300) {
                $alasan = 'Durasi soal harus antara 5 sampai 300 detik.';
            } elseif ($tipe === 'pilihan_ganda') {
                if ($colA === false || $colB === false || $colKunci === false) {
                    $alasan = 'Soal pilihan ganda butuh kolom opsi_a, opsi_b, dan jawaban_benar.';
                } elseif (count($rawOptions) < 2) {
                    $alasan = 'Minimal harus memiliki 2 pilihan jawaban (opsi_a dan opsi_b).';
                } else {
                    $kunciUpper = Str::upper($kunci);
                    $hurufKeys = array_keys($rawOptions);
                    $opsiValues = array_values($rawOptions);

                    if (isset($rawOptions[$kunciUpper])) {
                        $correctIndex = array_search($kunciUpper, $hurufKeys, true);
                    } elseif (is_numeric($kunci) && (int) $kunci >= 1 && (int) $kunci <= count($rawOptions)) {
                        $correctIndex = ((int) $kunci) - 1;
                    } else {
                        foreach ($opsiValues as $idx => $val) {
                            if (Str::lower($val) === Str::lower($kunci)) {
                                $correctIndex = $idx;
                                break;
                            }
                        }
                    }

                    if ($correctIndex === null || $correctIndex === false) {
                        $alasan = "Kunci jawaban '{$kunci}' tidak cocok dengan opsi yang tersedia (".implode(', ', $hurufKeys).').';
                    }
                }
            } elseif (mb_strlen($kunciEssay) > 5000) {
                $alasan = 'Kunci essay melebihi 5000 karakter.';
            }

            if ($alasan !== null) {
                $hasil[] = [
                    'baris' => $baris,
                    'teks' => $teks !== '' ? Str::limit($teks, 80) : '(soal kosong)',
                    'status' => 'gagal',
                    'alasan' => $alasan,
                ];
                continue;
            }

            DB::transaction(function () use ($teks, $tipe, $bobot, $durasi, $pembahasan, $kunciEssay, $rawOptions, $correctIndex, $quiz) {
                $question = Question::create([
                    'quiz_id' => $quiz?->id,
                    'teks' => $teks,
                    'tipe' => $tipe,
                    'bobot' => $bobot,
                    'durasi_detik' => $durasi,
                    'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                    'kunci_essay' => $tipe === 'essay' && $kunciEssay !== '' ? $kunciEssay : null,
                ]);

                if ($tipe === 'pilihan_ganda') {
                    $index = 0;
                    foreach ($rawOptions as $teksOpsi) {
                        $question->options()->create([
                            'teks' => $teksOpsi,
                            'is_correct' => $index === $correctIndex,
                        ]);
                        $index++;
                    }
                }
            });

            $hasil[] = [
                'baris' => $baris,
                'teks' => Str::limit($teks, 80),
                'tipe' => $tipe,
                'status' => 'berhasil',
                'alasan' => null,
            ];
        }

        fclose($handle);

        $ringkasan = [
            'berhasil' => count(array_filter($hasil, fn ($h) => $h['status'] === 'berhasil')),
            'gagal' => count(array_filter($hasil, fn ($h) => $h['status'] === 'gagal')),
            'baris' => $hasil,
            'quiz_id' => $quiz?->id,
            'quiz_nama' => $quiz?->judul,
        ];

        if ($quiz) {
            return redirect()->route('admin.quizzes.questions.import.result', $quiz)->with('impor_hasil', $ringkasan);
        }

        return redirect()->route('admin.quizzes.index')->with('impor_hasil', $ringkasan);
    }

    public function importResult(Request $request, ?Quiz $quiz = null): View|RedirectResponse
    {
        $ringkasan = $request->session()->pull('impor_hasil');

        if ($ringkasan === null) {
            return $quiz ? redirect()->route('admin.quizzes.questions.import', $quiz) : redirect()->route('admin.quizzes.index');
        }

        return view('admin.questions.import-result', compact('ringkasan', 'quiz'));
    }

    public function template(): StreamedResponse
    {
        $isi = "teks,tipe,bobot,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,jawaban_benar,durasi_detik,pembahasan,kunci_essay\n"
            ."\"Ibu kota negara Indonesia saat ini adalah?\",\"pilihan_ganda\",1,\"Jakarta\",\"Bandung\",\"Surabaya\",\"Medan\",\"\",A,30,\"Jakarta merupakan ibu kota negara Indonesia.\",\n"
            ."\"Berapakah hasil dari 15 dikalikan 4?\",\"pilihan_ganda\",1,\"45\",\"50\",\"60\",\"65\",\"\",C,20,\"15 x 4 = 60.\",\n"
            ."\"Komponen komputer yang berfungsi sebagai otak pemrosesan data adalah?\",\"pilihan_ganda\",1,\"RAM\",\"CPU\",\"Harddisk\",\"Power Supply\",\"\",B,30,\"Central Processing Unit (CPU) bertugas memproses seluruh instruksi komputer.\",\n"
            ."\"Jelaskan perbedaan HTTP dan HTTPS.\",\"essay\",10,,,,,,,60,\"HTTP tidak terenkripsi, HTTPS memakai TLS.\",\"HTTP mengirim data tanpa enkripsi. HTTPS memakai TLS sehingga data terenkripsi.\"\n";

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'template-soal.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
