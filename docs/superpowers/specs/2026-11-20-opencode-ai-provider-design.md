# Desain Fitur: Provider AI OpenCode Server di Samping Provider Lama

## 1. Ringkasan & Tujuan

Satu tab **Provider AI** di Pengaturan Admin memilih mesin AI yang dipakai workspace: `OpenAI-compatible` (9Router, perilaku sekarang) atau `OpenCode server` (server OpenCode di jaringan/VPS yang sudah dipasangi NPM). Semua fitur AI mengikuti pilihan itu: generate soal, saran nilai essay, dan cover kelas.

Seluruh konfigurasi OpenCode — base URL, username, password, model, timeout — diubah dari UI Admin dan disimpan di database, dengan `config/services.php` sebagai nilai awal. Daftar model diambil dari API server tersebut; tidak ada model yang ditanam di kode.

---

## 2. Goals, Non-Goals, Anti-Goals

### Goals
- Pilihan provider per workspace, dengan platform sebagai default dan workspace boleh menimpa (pola yang sama dengan field AI sekarang).
- Konfigurasi OpenCode lengkap bisa diubah dari UI Admin, password bersifat write-only.
- Daftar model chat tampil di UI setelah tes koneksi dan bisa diklik untuk mengisi kolom model.
- Cover kelas tetap bisa memakai AI saat provider OpenCode dipilih (daftar model gambar juga dari API).
- Perilaku provider lama tidak berubah sama sekali.

### Non-Goals
- Tidak menambah dependency Composer baru; tetap memakai `Illuminate\Support\Facades\Http`.
- Tidak membuat fallback otomatis antar provider.
- Tidak mengubah skema soal, bobot, sanitasi, atau alur pratinjau yang sudah ada.

### Anti-Goals
- Tidak menyimpan atau menampilkan kembali password OpenCode di HTML.
- Tidak membiarkan provider OpenCode yang belum dikonfigurasi menghasilkan error 500; harus pesan jelas.
- Tidak mengirim `GET /api/model` tanpa `types` (server mengembalikan daftar kosong).

---

## 3. Pendekatan yang Dipertimbangkan

1. **Satu pintu AI, dua mesin di belakangnya (dipilih).** `AiQuestionService` tetap menjadi satu-satunya façade; pemanggilan HTTP dipindah ke klien kecil (`OpenAiTextClient`, `OpenCodeTextClient`) di balik interface. Cover memakai pola sama (`ImageClient`).
   - Alasan: semua pemanggil (`GenerateAiQuestionsJob`, `EssayGrader`, `ReportController`, `GenerateClassCoverJob`, `QuestionController`) tidak perlu diubah satu per satu, dan auth/path/parsing tiap provider tinggal di satu file.

2. **Cabang `if` di tiap tempat.** Auth Basic, path `/api/model?types=chat`, bentuk `{"data":{"text":…}}`, dan penanganan 429 akan terduplikasi di empat tempat.

3. **Shim penerjemah gaya OpenAI di server.** Aplikasi hampir tidak berubah, tetapi konfigurasi OpenCode tidak bisa diubah dari UI Admin — justru kebutuhan utamanya.

---

## 4. Konfigurasi

`.env` / `.env.example`:
```env
OPENCODE_URL=http://192.168.0.247:4096
OPENCODE_USERNAME=opencode
OPENCODE_PASSWORD=
OPENCODE_MODEL=
```

`config/services.php`:
```php
'opencode' => [
    'base_url' => env('OPENCODE_URL'),
    'username' => env('OPENCODE_USERNAME', 'opencode'),
    'password' => env('OPENCODE_PASSWORD'),
    'model' => env('OPENCODE_MODEL'),
    'timeout' => 120,
],
```

`OPENCODE_MODEL` sengaja dibiarkan kosong di `.env.example`; model dipilih dari daftar di UI.

---

## 5. Data

Migrasi baru menambah kolom ke `app_settings` **dan** `organization_settings`:

| Kolom | Tipe | Catatan |
|---|---|---|
| `ai_provider` | string(16), default `openai` | di `organization_settings` nullable |
| `opencode_base_url` | string, nullable | |
| `opencode_username` | string(100), nullable | kosong = `opencode` |
| `opencode_password` | text, nullable | cast `encrypted` |
| `opencode_model` | string(150), nullable | |
| `opencode_timeout` | unsignedSmallInteger, nullable | default 120 |

Kolom ikut masuk `AppSetting::fields()`, `$fillable`, `casts()`, dan default `AppSetting::current()` dari `config('services.opencode.*')`. Karena `OrganizationSetting::effective()` mengiterasi `AppSetting::fields()`, pewarisan platform → workspace otomatis berlaku.

---

## 6. Resolusi Konfigurasi & Klien

`App\Services\Ai\AiConfig` (immutable) menyatukan settings + config:

```php
final class AiConfig
{
    public function __construct(
        public string $provider,          // 'openai' | 'opencode'
        public array $openai,             // base_url, api_key, model, timeout
        public array $opencode,           // base_url, username, password, model, timeout
    ) {}

    public static function fromSettings(object $settings): self;
    public static function current(): self; // dari app('app.settings')
}
```

Interface:
```php
interface TextClient
{
    /** @return array<string, array<string>> daftar id model per grup */
    public function models(int $timeout): array;

    /** @param array<int, array{role: string, content: string}> $messages */
    public function chat(array $messages, ?string $model, float $temperature): string;

    public function describe(Response $response, ?string $model = null): string;
}

interface ImageClient
{
    public function generate(string $prompt, string $model, string $size): string; // byte gambar
}
```

`App\Services\Ai\AiManager` (stateless, dibuat per pemakaian) menyediakan `config()`, `text()`, dan `image(): ?ImageClient`. `image()` mengembalikan `null` bila base URL / password / model gambar belum lengkap.

### Perilaku tiap klien

| | OpenAI-compatible | OpenCode |
|---|---|---|
| Auth | `Bearer {api_key}` | Basic `username:password` |
| Daftar model | `GET {base}/models` → `data[]`, grup dari `owned_by`/deteksi nama | `GET {base}/api/model?types=chat` → `data[]`, grup `OpenCode` |
| Chat | `POST {base}/chat/completions` | `POST {base}/api/experimental/generate/text` |
| Payload chat | `model`, `messages`, `temperature`, `stream: false` | `prompt` (system + blank line + user), `model: {id, providerID: "opencode"}` |
| Ambil teks | `choices.0.message.content` (+ fallback reasoning/SSE) | `data.text` |
| Gambar | `POST {base}/images/generations` → `data.0.b64_json`/`url` | `POST {base}/api/experimental/generate/image` (`aspect: "16:9"`, `resolution: "2K"`, `n: 1`) → `base64_decode(data.images.0.data)` |
| 401 | "API key ditolak" | "Username atau password OpenCode ditolak" |
| 429 | pakai `Retry-After` | pakai `retryAfterMs` (dibagi 1000) |
| Model tidak ada | 404 → "Model X tidak ditemukan" | `_tag: ModelNotFoundError` → pesan sama |

Semua kegagalan klien dilempar sebagai `App\Exceptions\AiRequestException` dengan `status` dan `retryAfter` (detik), sehingga `transient()`/`retryDelay()` yang ada bekerja tanpa perubahan.

`AiQuestionService` menyimpan prompt, ekstraksi JSON, sanitasi soal, bobot, dan cache daftar model; yang pindah ke klien hanya transport. Cache key tetap `'ai_models_'.sha1(<base url aktif>)`, jadi key provider lama tidak berubah.

---

## 7. UI Admin

Tab **Provider AI**:
- Radio `ai_provider` (`openai` | `opencode`) di atas panel.
- Panel "OpenAI-compatible" berisi empat isian yang ada sekarang (tidak berubah).
- Panel "OpenCode server": base URL, username, password (placeholder "Kosongkan untuk mempertahankan password saat ini"), model, timeout.
- Tiap panel memakai satu komponen bersama `resources/views/components/ai-test-panel.blade.php`: tombol tes, status, dan daftar model yang bisa diklik untuk mengisi kolom model. Komponen menerima URL tes dan daftar id input yang dikirim.

Tab **Cover**:
- Isian `image_model` tetap ada. Saat provider OpenCode, muncul tombol "Muat model gambar" yang memanggil endpoint daftar model `types=image` dan mengisi `<datalist>`.

Endpoint baru:
- `POST admin/settings/opencode/test` → bentuk respons sama dengan tes AI lama: `{ok, endpoint:{ok,status,pesan}, probe, model}`.
- `POST admin/settings/opencode/image-models` → `{model: [id, …]}`.

Keduanya `throttle:5,1` dan hanya untuk admin workspace (middleware group yang sudah ada).

---

## 8. Validasi & Keamanan

Saat menyimpan section `ai`:
- `ai_provider` → `nullable|in:openai,opencode` (form selalu mengirim radio; nilai kosong berarti jangan ubah, sehingga penyimpanan section lain tidak ikut gagal)
- `opencode_base_url` → `nullable|url|max:255`
- `opencode_username` → `nullable|string|max:100`
- `opencode_password` → `nullable|string|max:500` (kosong = jangan timpa, sama seperti `ai_api_key`)
- `opencode_model` → `nullable|string|max:150`
- `opencode_timeout` → `nullable|integer|min:10|max:300`

Password hanya disimpan lewat cast `encrypted`, tidak pernah dirender kembali, dan tidak muncul di respons tes koneksi (diuji dengan `assertStringNotContainsString`).

---

## 9. Perilaku Gagal

- Tidak ada fallback otomatis. Provider terpilih adalah satu-satunya jalur.
- Provider OpenCode belum lengkap → `AiRequestException` "OpenCode belum dikonfigurasi…" (bukan 500).
- Cover gagal → job mencatat warning dan cover lokal tetap dipakai (perilaku sekarang).
- Generate soal / saran nilai gagal → error yang ada sekarang (transien di-retry job, permanen ditampilkan ke admin).

---

## 10. Yang Tidak Berubah

Kuota AI per workspace (`OrganizationUsage`), prompt dan sanitasi soal, alur pratinjau soal, alur retry job, serta tab pengaturan lain.

---

## 11. Pengujian

- Unit: `AiConfig` (default config, override settings, provider), `OpenAiTextClient` (grup model, auth, payload), `OpenCodeTextClient` (path + `types`, Basic auth, prompt gabungan, `data.text`, 401/404/429), `OpenCodeImageClient` (payload gambar, base64), `AiManager` (pemilihan klien).
- Feature: simpan provider + field OpenCode, password kosong tidak menimpa, validasi `ai_provider`, tes koneksi OpenCode mengembalikan daftar model tanpa membocorkan password, isian kosong memakai pengaturan tersimpan, tab AI merender radio + panel, endpoint model gambar.
- Regresi: seluruh test AI yang ada (`AiQuestionServiceTest`, `AiConnectionTest`, `EssayTest`, `OrganizationQuotaTest`) harus tetap hijau.

---

## 12. Risiko

- Server OpenCode saat ini mengembalikan **429 free usage limit** untuk generasi. Daftar model tetap normal; generasi baru berjalan setelah kuota bebas tersedia atau langganan OpenCode Go.
- `GET /api/model` tanpa `types` mengembalikan daftar kosong; klien wajib selalu mengirim `types`.
- Hanya ada satu model gambar di server (`grok-imagine-image-2.0`), jadi cover bergantung pada model itu saat provider OpenCode.
