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
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('q'));

        $questions = Question::withCount('options')
            ->when($cari !== '', fn ($q) => $q->where('teks', 'like', "%{$cari}%"))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $terkunci = Question::whereIn('id', $questions->pluck('id'))
            ->get()
            ->filter(fn (Question $q) => $q->isLocked())
            ->pluck('id')
            ->all();

        return view('admin.questions.index', compact('questions', 'cari', 'terkunci'));
    }

    public function create(): View
    {
        return view('admin.questions.create');
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $question = Question::create([
                'teks' => $data['teks'],
                'durasi_detik' => $data['durasi_detik'],
                'pembahasan' => $data['pembahasan'] ?? null,
            ]);

            $this->simpanOpsi($question, $data['opsi'], (int) $data['correct']);
        });

        return redirect()->route('admin.questions.index')->with('sukses', 'Soal ditambahkan.');
    }

    public function edit(Question $question): View|RedirectResponse
    {
        if ($question->isLocked()) {
            return redirect()->route('admin.questions.index')
                ->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa diedit.']);
        }

        $question->load('options');

        return view('admin.questions.edit', compact('question'));
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        if ($question->isLocked()) {
            return redirect()->route('admin.questions.index')
                ->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa diedit.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'teks' => $data['teks'],
                'durasi_detik' => $data['durasi_detik'],
                'pembahasan' => $data['pembahasan'] ?? null,
            ]);

            $question->options()->delete();
            $this->simpanOpsi($question, $data['opsi'], (int) $data['correct']);
        });

        return redirect()->route('admin.questions.index')->with('sukses', 'Soal diperbarui.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        if ($question->isLocked()) {
            return redirect()->route('admin.questions.index')
                ->withErrors(['soal' => 'Soal ini dipakai sesi yang sudah dibuka, jadi tidak bisa dihapus.']);
        }

        $question->delete();

        return redirect()->route('admin.questions.index')->with('sukses', 'Soal dihapus.');
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

    public function importForm(): View
    {
        $quizzes = Quiz::orderBy('judul')->get();

        return view('admin.questions.import', compact('quizzes'));
    }

    public function import(Request $request): RedirectResponse
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

        $quiz = $request->filled('quiz_id') ? Quiz::find($request->quiz_id) : null;

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
        $colA = $cariKolom(['opsi_a', 'pilihan_a', 'opsi a', 'a']);
        $colB = $cariKolom(['opsi_b', 'pilihan_b', 'opsi b', 'b']);
        $colC = $cariKolom(['opsi_c', 'pilihan_c', 'opsi c', 'c']);
        $colD = $cariKolom(['opsi_d', 'pilihan_d', 'opsi d', 'd']);
        $colE = $cariKolom(['opsi_e', 'pilihan_e', 'opsi e', 'e']);
        $colF = $cariKolom(['opsi_f', 'pilihan_f', 'opsi f', 'f']);
        $colKunci = $cariKolom(['jawaban_benar', 'kunci', 'kunci_jawaban', 'jawaban']);
        $colDurasi = $cariKolom(['durasi_detik', 'durasi']);
        $colPembahasan = $cariKolom(['pembahasan', 'penjelasan']);

        if ($colTeks === false || $colA === false || $colB === false || $colKunci === false) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Header CSV minimal harus memuat kolom: teks, opsi_a, opsi_b, dan jawaban_benar.',
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
            $kunci = trim((string) ($row[$colKunci] ?? ''));
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

            if ($teks === '') {
                $alasan = 'Teks soal kosong.';
            } elseif (mb_strlen($teks) > 2000) {
                $alasan = 'Teks soal melebihi 2000 karakter.';
            } elseif (count($rawOptions) < 2) {
                $alasan = 'Minimal harus memiliki 2 pilihan jawaban (opsi_a dan opsi_b).';
            } elseif ($durasi < 5 || $durasi > 300) {
                $alasan = 'Durasi soal harus antara 5 sampai 300 detik.';
            } else {
                $correctIndex = null;
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

            if ($alasan !== null) {
                $hasil[] = [
                    'baris' => $baris,
                    'teks' => $teks !== '' ? Str::limit($teks, 80) : '(soal kosong)',
                    'status' => 'gagal',
                    'alasan' => $alasan,
                ];
                continue;
            }

            DB::transaction(function () use ($teks, $durasi, $pembahasan, $rawOptions, $correctIndex, $quiz) {
                $question = Question::create([
                    'teks' => $teks,
                    'durasi_detik' => $durasi,
                    'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                ]);

                $index = 0;
                foreach ($rawOptions as $huruf => $teksOpsi) {
                    $question->options()->create([
                        'teks' => $teksOpsi,
                        'is_correct' => $index === $correctIndex,
                    ]);
                    $index++;
                }

                if ($quiz) {
                    $quiz->questions()->syncWithoutDetaching([$question->id]);
                }
            });

            $hasil[] = [
                'baris' => $baris,
                'teks' => Str::limit($teks, 80),
                'status' => 'berhasil',
                'alasan' => null,
            ];
        }

        fclose($handle);

        $ringkasan = [
            'berhasil' => count(array_filter($hasil, fn ($h) => $h['status'] === 'berhasil')),
            'gagal' => count(array_filter($hasil, fn ($h) => $h['status'] === 'gagal')),
            'baris' => $hasil,
            'quiz_nama' => $quiz?->judul,
        ];

        return redirect()->route('admin.questions.import.result')->with('impor_hasil', $ringkasan);
    }

    public function importResult(Request $request): View|RedirectResponse
    {
        $ringkasan = $request->session()->pull('impor_hasil');

        if ($ringkasan === null) {
            return redirect()->route('admin.questions.import');
        }

        return view('admin.questions.import-result', compact('ringkasan'));
    }

    public function template(): StreamedResponse
    {
        $isi = "teks,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,jawaban_benar,durasi_detik,pembahasan\n"
            ."\"Ibu kota negara Indonesia saat ini adalah?\",\"Jakarta\",\"Bandung\",\"Surabaya\",\"Medan\",\"\",A,30,\"Jakarta merupakan ibu kota negara Indonesia.\"\n"
            ."\"Berapakah hasil dari 15 dikalikan 4?\",\"45\",\"50\",\"60\",\"65\",\"\",C,20,\"15 x 4 = 60.\"\n"
            ."\"Komponen komputer yang berfungsi sebagai otak pemrosesan data adalah?\",\"RAM\",\"CPU\",\"Harddisk\",\"Power Supply\",\"\",B,30,\"Central Processing Unit (CPU) bertugas memproses seluruh instruksi komputer.\"\n"
            ."\"Manakah di bawah ini yang merupakan contoh bahasa pemrograman berorientasi objek?\",\"HTML\",\"CSS\",\"Java\",\"SQL\",\"\",C,30,\"Java adalah bahasa pemrograman yang berbasis OOP (Object-Oriented Programming).\"\n"
            ."\"Protokol default yang digunakan untuk browsing web secara aman adalah?\",\"HTTP\",\"HTTPS\",\"FTP\",\"SSH\",\"\",B,25,\"HTTPS menggunakan enkripsi SSL/TLS untuk keamanan.\"\n";

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'template-soal.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
