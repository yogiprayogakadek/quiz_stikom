# Provider AI OpenCode Server Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah provider AI "OpenCode server" yang bisa dipilih per workspace di UI Admin, dipakai untuk generate soal, saran nilai essay, dan cover kelas, tanpa mengubah perilaku provider OpenAI-compatible yang sudah ada.

**Architecture:** `AiQuestionService` tetap menjadi façade; transport HTTP dipindah ke `OpenAiTextClient` / `OpenCodeTextClient` di balik interface `TextClient`, dan cover memakai `ImageClient`. `AiConfig` menyatukan pengaturan database + `config/services.php`; `AiManager` memilih klien sesuai `ai_provider`.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Alpine.js, PHPUnit, `Illuminate\Support\Facades\Http`.

**Spec:** `docs/superpowers/specs/2026-11-20-opencode-ai-provider-design.md`

## Global Constraints

- Tanpa dependency Composer baru; hanya `Illuminate\Support\Facades\Http`.
- Password OpenCode disimpan dengan cast `encrypted` dan tidak boleh muncul di HTML maupun respons JSON.
- Semua request OpenCode memakai Basic auth dan selalu mengirim `types=chat` atau `types=image`.
- Kegagalan klien dilempar sebagai `App\Exceptions\AiRequestException` dengan `status` dan `retryAfter` dalam detik.
- Tidak ada fallback otomatis antar provider.
- Pesan error berbahasa Indonesia, mengikuti gaya pesan yang sudah ada.
- Nilai default provider: `openai`. Nilai `ai_provider` yang sah: `openai`, `opencode`.

## Review Focus

1. Password OpenCode dikosongkan saat menyimpan → password lama harus tetap tersimpan, bukan dikosongkan.
2. Base URL OpenCode diakhiri `/` → klien tidak boleh menghasilkan `//api/model`.
3. Provider OpenCode dipilih tapi base URL/password/model kosong → pesan "belum dikonfigurasi", bukan error 500.
4. Server OpenCode membalas 429 `retryAfterMs` → `AiRequestException->retryAfter` berisi detik, bukan milidetik.
5. Workspace tanpa baris `organization_settings` → mewarisi `ai_provider` dan seluruh field OpenCode dari platform.

---

### Task 1: Kolom database & model pengaturan

**Files:**
- Create: `database/migrations/2026_11_20_000001_add_opencode_provider_to_settings.php`
- Modify: `app/Models/AppSetting.php`
- Modify: `app/Models/OrganizationSetting.php`
- Test: `tests/Feature/OpenCodeSettingsTest.php`

**Interfaces:**
- Produces: kolom `ai_provider`, `opencode_base_url`, `opencode_username`, `opencode_password`, `opencode_model`, `opencode_timeout` pada `app_settings` dan `organization_settings`; field tersebut ikut `AppSetting::fields()` sehingga `OrganizationSetting::effective()` mewarisinya.

- [ ] **Step 1: Tulis feature test pewarisan dan default**

```php
// tests/Feature/OpenCodeSettingsTest.php
namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenCodeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_platform_memakai_config_env(): void
    {
        config(['services.opencode.username' => 'opencode', 'services.opencode.timeout' => 120]);

        $settings = AppSetting::current();

        $this->assertSame('openai', $settings->ai_provider);
        $this->assertSame('opencode', $settings->opencode_username);
        $this->assertSame(120, $settings->opencode_timeout);
        $this->assertContains('opencode_password', AppSetting::fields());
    }

    public function test_workspace_mewarisi_provider_dan_field_opencode_dari_platform(): void
    {
        AppSetting::query()->create([
            'id' => 1,
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://oc.example.test',
            'opencode_model' => 'model-dari-platform',
        ]);
        $organization = Organization::create([
            'name' => 'Kampus Warisan', 'slug' => 'kampus-warisan', 'type' => 'education',
            'timezone' => 'Asia/Jakarta', 'is_active' => true,
        ]);

        $effective = OrganizationSetting::effective($organization->id);

        $this->assertSame('opencode', $effective->ai_provider);
        $this->assertSame('https://oc.example.test', $effective->opencode_base_url);
        $this->assertSame('model-dari-platform', $effective->opencode_model);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OpenCodeSettingsTest`
Expected: FAIL (kolom/tabel belum ada atau field tidak dikenal).

- [ ] **Step 3: Tulis migrasi dan perbarui kedua model**

Migrasi: `Schema::table('app_settings', …)` dan `Schema::table('organization_settings', …)` menambah `opencode_base_url`, `opencode_username` (string 100), `opencode_password` (text), `opencode_model` (string 150), `opencode_timeout` (unsignedSmallInteger). Kolom `ai_provider` string(16) diberi `default('openai')` **hanya** di `app_settings`; di `organization_settings` dibuat `nullable()` tanpa default agar baris workspace lama tetap mewarisi pilihan platform. `down()` menghapus kolom yang sama.

`app/Models/AppSetting.php`:
- `fields()`: tambahkan lima field `opencode_*` setelah `ai_timeout`, dan `ai_provider` sebelum `ai_base_url`.
- `$fillable`: tambahkan field yang sama.
- `casts()`: `'opencode_password' => 'encrypted'`, `'opencode_timeout' => 'integer'`.
- `current()`: default `'ai_provider' => 'openai'`, `'opencode_base_url' => config('services.opencode.base_url')`, `'opencode_username' => config('services.opencode.username', 'opencode')`, `'opencode_model' => config('services.opencode.model')`, `'opencode_timeout' => config('services.opencode.timeout', 120)`.

`app/Models/OrganizationSetting.php`: tambahkan field yang sama ke `$fillable` dan `casts()`. Tidak ada perubahan pada `effective()`.

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter=OpenCodeSettingsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models tests/Feature/OpenCodeSettingsTest.php
git commit -m "feat(ai): add opencode provider columns to settings"
```

---

### Task 2: Konfigurasi service, env, dan `AiConfig`

**Files:**
- Modify: `config/services.php`
- Modify: `.env`, `.env.example`
- Create: `app/Services/Ai/AiConfig.php`
- Test: `tests/Unit/AiConfigTest.php`

**Interfaces:**
- Produces: `AiConfig::__construct(string $provider, array $openai, array $opencode, array $image)`, `AiConfig::fromSettings(object $settings, array $overrides = []): self`, `AiConfig::current(): self`, `AiConfig::textBaseUrl(): string`, `AiConfig::defaultModel(): string`.
- `$openai` keys: `base_url` (string), `api_key` (?string), `model` (string), `timeout` (int).
- `$opencode` keys: `base_url` (string), `username` (string), `password` (?string), `model` (string), `timeout` (int).
- `$image` keys: `base_url` (string), `api_key` (?string), `model` (string), `timeout` (int, 150).

- [ ] **Step 1: Tambah test konfigurasi**

```php
// tests/Unit/AiConfigTest.php — tambahan method
public function test_opencode_config_has_required_keys(): void
{
    $config = config('services.opencode');

    $this->assertIsArray($config);
    $this->assertArrayHasKey('base_url', $config);
    $this->assertArrayHasKey('username', $config);
    $this->assertArrayHasKey('password', $config);
    $this->assertArrayHasKey('model', $config);
    $this->assertSame(120, $config['timeout']);
}

public function test_ai_config_menimpa_nilai_dari_settings_dan_memakai_default_provider_openai(): void
{
    config([
        'services.ai.base_url' => 'https://env.example.test/v1',
        'services.ai.default_model' => 'env-model',
        'services.opencode.username' => 'opencode',
    ]);

    $settings = new \App\Models\AppSetting([
        'opencode_base_url' => 'https://oc.example.test/',
        'opencode_password' => 'rahasia',
        'opencode_timeout' => 90,
    ]);

    $config = \App\Services\Ai\AiConfig::fromSettings($settings);

    $this->assertSame('openai', $config->provider);
    $this->assertSame('https://env.example.test/v1', $config->openai['base_url']);
    $this->assertSame('https://oc.example.test/', $config->opencode['base_url']);
    $this->assertSame('opencode', $config->opencode['username']);
    $this->assertSame('rahasia', $config->opencode['password']);
    $this->assertSame(90, $config->opencode['timeout']);
    $this->assertSame('https://env.example.test/v1', $config->textBaseUrl());
}

public function test_override_kosong_tidak_menimpa_nilai_tersimpan(): void
{
    $settings = new \App\Models\AppSetting([
        'ai_provider' => 'opencode',
        'opencode_base_url' => 'https://saved.example.test',
        'opencode_password' => 'saved-secret',
        'opencode_model' => 'saved-model',
    ]);

    $config = \App\Services\Ai\AiConfig::fromSettings($settings, [
        'opencode_base_url' => '',
        'opencode_password' => '',
        'opencode_model' => 'form-model',
    ]);

    $this->assertSame('opencode', $config->provider);
    $this->assertSame('https://saved.example.test', $config->opencode['base_url']);
    $this->assertSame('saved-secret', $config->opencode['password']);
    $this->assertSame('form-model', $config->opencode['model']);
    $this->assertSame('https://saved.example.test', $config->textBaseUrl());
    $this->assertSame('form-model', $config->defaultModel());
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=AiConfigTest`
Expected: FAIL (`services.opencode` belum ada, kelas `AiConfig` belum ada).

- [ ] **Step 3: Tulis konfigurasi dan kelas `AiConfig`**

`config/services.php` — blok `opencode` persis seperti di spec (bagian 4). `.env` dan `.env.example` — empat variabel `OPENCODE_*` setelah blok `AI_*`; `.env` memakai URL server nyata, password diisi manual oleh pemilik, model dikosongkan.

`AiConfig::fromSettings()`: ambil atribut dengan `$settings->getAttribute($key)`, ganti dengan override hanya bila override `filled()`, lalu jatuh ke `config('services.*')`. `timeout` di-cast `(int)`. `textBaseUrl()` mengembalikan `$opencode['base_url']` bila provider `opencode`, selain itu `$openai['base_url']`, tanpa `rtrim`. `defaultModel()` mengikuti provider yang sama.

`$image` diresolusi dari field yang sudah ada: `base_url` = `image_base_url` ?: `textBaseUrl()`, `api_key` = `image_api_key` ?: kunci provider aktif, `model` = `image_model`, `timeout` = 150. Dengan begitu cover ikut provider aktif tanpa kolom baru.

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter=AiConfigTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/services.php .env.example app/Services/Ai/AiConfig.php tests/Unit/AiConfigTest.php
git commit -m "feat(ai): add opencode configuration and AiConfig resolver"
```

---

### Task 3: `TextClient` + klien OpenAI-compatible (pindahkan transport lama)

**Files:**
- Create: `app/Services/Ai/TextClient.php`
- Create: `app/Services/Ai/OpenAiTextClient.php`
- Test: `tests/Unit/OpenAiTextClientTest.php`

**Interfaces:**
- Produces:
  - `interface TextClient { public function models(int $timeout): array; public function chat(array $messages, ?string $model, float $temperature): string; }`
  - `OpenAiTextClient::__construct(AiConfig $config)`
  - `models()` mengembalikan `array<string, array<string>>`; `chat()` mengembalikan teks asisten dan melempar `AiRequestException` saat gagal.
- Consumes: `AiConfig` dari Task 2.

- [ ] **Step 1: Tulis unit test klien OpenAI**

```php
// tests/Unit/OpenAiTextClientTest.php
namespace Tests\Unit;

use App\Exceptions\AiRequestException;
use App\Services\Ai\AiConfig;
use App\Services\Ai\OpenAiTextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiTextClientTest extends TestCase
{
    private function client(array $openai = []): OpenAiTextClient
    {
        return new OpenAiTextClient(new AiConfig('openai', array_merge([
            'base_url' => 'https://api.example.test/v1', 'api_key' => 'kunci', 'model' => 'gpt-4o-mini', 'timeout' => 10,
        ], $openai), ['base_url' => '', 'username' => 'opencode', 'password' => null, 'model' => '', 'timeout' => 120],
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 150]));
    }

    public function test_models_mengelompokkan_per_provider_dan_memakai_bearer(): void
    {
        Http::fake(['https://api.example.test/v1/models' => Http::response(['data' => [
            ['id' => 'ag/gemini-3.8-flash-medium', 'owned_by' => 'google'],
            ['id' => 'openai/gpt-4o-mini', 'owned_by' => 'openai'],
        ]])]);

        $models = $this->client()->models(10);

        $this->assertSame(['ag/gemini-3.8-flash-medium'], $models['Google']);
        $this->assertSame(['openai/gpt-4o-mini'], $models['OpenAI']);
        Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Bearer kunci');
    }

    public function test_chat_mengirim_payload_dan_mengembalikan_teks(): void
    {
        Http::fake(['https://api.example.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"ok":true}']]],
        ])]);

        $text = $this->client()->chat([['role' => 'user', 'content' => 'halo']], 'gpt-4o-mini', 0.2);

        $this->assertSame('{"ok":true}', $text);
        Http::assertSent(fn ($r) => $r['model'] === 'gpt-4o-mini' && $r['temperature'] === 0.2 && $r['stream'] === false);
    }

    public function test_429_memakai_retry_after_header(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'rate limit']], 429, ['Retry-After' => '45'])]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], 'gpt-4o-mini', 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame(45, $e->retryAfter);
            $this->assertStringContainsString('membatasi permintaan', $e->getMessage());
            $this->assertStringContainsString('45 detik', $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OpenAiTextClientTest`
Expected: FAIL (kelas belum ada).

- [ ] **Step 3: Implementasikan interface dan `OpenAiTextClient`**

Pindahkan perilaku dari `AiQuestionService` apa adanya: `withToken($api_key ?? '')`, `GET {base_url}/models`, `parseModels` + `detectProvider`; `POST {base_url}/chat/completions` dengan `model`, `messages`, `temperature`, `stream => false`; ekstraksi teks (`choices.0.message.content`, multi-part, `choices.0.text`, `reasoning_content`, refusal yang melempar `RuntimeException`, fallback SSE `data:`).

Pesan error dari `pesanGagal()`/`pesanChatGagal()`/`alasanProvider()` lama dipindah utuh ke klien ini: `models()` melempar `AiRequestException($pesan, $status)`; `chat()` melempar `AiRequestException($pesan, $status, $retryAfter)` dengan `retryAfter` dari header `Retry-After`. Kegagalan koneksi (`ConnectionException`) menjadi `AiRequestException('Tidak bisa menghubungi endpoint. Periksa Base URL dan koneksi server.', null)` untuk `models()` dan `'Tidak bisa menghubungi endpoint saat menguji model default.'` untuk `chat()`.

`chat()` memakai `$model ?: $config->defaultModel()`.

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter=OpenAiTextClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai tests/Unit/OpenAiTextClientTest.php
git commit -m "feat(ai): extract OpenAI-compatible transport into TextClient"
```

---

### Task 4: Klien OpenCode (`OpenCodeTextClient`)

**Files:**
- Create: `app/Services/Ai/OpenCodeTextClient.php`
- Test: `tests/Unit/OpenCodeTextClientTest.php`

**Interfaces:**
- Consumes: `TextClient`, `AiConfig`, `AiRequestException`.
- Produces: `OpenCodeTextClient::__construct(AiConfig $config)`, `models(int $timeout = 15): array`, `modelsOfType(string $type, int $timeout = 15): array`, `chat(array $messages, ?string $model, float $temperature): string`.

- [ ] **Step 1: Tulis unit test**

```php
// tests/Unit/OpenCodeTextClientTest.php
namespace Tests\Unit;

use App\Exceptions\AiRequestException;
use App\Services\Ai\AiConfig;
use App\Services\Ai\OpenCodeTextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenCodeTextClientTest extends TestCase
{
    private function client(array $open = []): OpenCodeTextClient
    {
        return new OpenCodeTextClient(new AiConfig('opencode',
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 60],
            array_merge(['base_url' => 'https://oc.example.test/', 'username' => 'opencode', 'password' => 'rahasia', 'model' => 'pilihan-saya', 'timeout' => 120], $open),
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 150]));
    }

    public function test_models_selalu_mengirim_types_chat_dan_basic_auth(): void
    {
        Http::fake(['https://oc.example.test/api/model*' => Http::response(['location' => ['directory' => '/srv'], 'data' => [
            ['id' => 'big-pickle', 'providerID' => 'opencode', 'type' => 'chat', 'enabled' => true],
            ['id' => 'nemotron-3.5-super-vl-free', 'providerID' => 'opencode', 'type' => 'chat', 'enabled' => true],
        ]])]);

        $models = $this->client()->models();

        $this->assertSame(['OpenCode'], array_keys($models));
        $this->assertContains('nemotron-3.5-super-vl-free', $models['OpenCode']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'types=chat')
            && ! str_contains($r->url(), '//api')
            && $r->header('Authorization')[0] === 'Basic '.base64_encode('opencode:rahasia'));
    }

    public function test_chat_menggabungkan_prompt_dan_memakai_provider_opencode(): void
    {
        Http::fake(['https://oc.example.test/api/experimental/generate/text' => Http::response(['data' => ['text' => '{"ok":true}']])]);

        $text = $this->client()->chat([
            ['role' => 'system', 'content' => 'SISTEM'],
            ['role' => 'user', 'content' => 'PENGGUNA'],
        ], null, 0.7);

        $this->assertSame('{"ok":true}', $text);
        Http::assertSent(function ($r) {
            return $r['model'] === ['id' => 'pilihan-saya', 'providerID' => 'opencode']
                && str_contains($r['prompt'], 'SISTEM')
                && str_contains($r['prompt'], 'PENGGUNA');
        });
    }

    public function test_401_menyebut_kredensial_ditolak(): void
    {
        Http::fake(['*' => Http::response(['_tag' => 'UnauthorizedError', 'message' => 'Authentication required'], 401)]);

        $this->expectException(AiRequestException::class);
        $this->client()->models();
    }

    public function test_429_memakai_retry_after_ms_sebagai_detik(): void
    {
        Http::fake(['*' => Http::response([
            '_tag' => 'ProviderRequestError', 'reason' => 'rate_limit',
            'message' => 'Free usage limit reached', 'status' => 429, 'retryAfterMs' => 61028000,
        ], 429)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], null, 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame(61028, $e->retryAfter);
            $this->assertStringContainsString('Free usage limit reached', $e->getMessage());
        }
    }

    public function test_model_not_found_menyebut_nama_model(): void
    {
        Http::fake(['*' => Http::response([
            '_tag' => 'ModelNotFoundError', 'providerID' => 'opencode', 'modelID' => 'tidak-ada',
            'message' => 'Model not found: opencode/tidak-ada',
        ], 404)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'x']], 'tidak-ada', 0.2);
            $this->fail('Harus melempar AiRequestException');
        } catch (AiRequestException $e) {
            $this->assertStringContainsString('tidak-ada', $e->getMessage());
        }
    }

    public function test_konfigurasi_belum_lengkap_ditolak_lebih_awal(): void
    {
        $this->expectException(AiRequestException::class);
        $this->expectExceptionMessage('OpenCode belum dikonfigurasi');
        $this->client(['password' => null])->models();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OpenCodeTextClientTest`
Expected: FAIL (kelas belum ada).

- [ ] **Step 3: Implementasikan `OpenCodeTextClient`**

- `modelsOfType(string $type, int $timeout)`: bila `base_url` kosong atau `password` kosong → `throw new AiRequestException('OpenCode belum dikonfigurasi. Isi Base URL dan password di Pengaturan > Provider AI.', null)`. Request: `rtrim($base_url, '/').'/api/model'` dengan query `['types' => $type]`, `withBasicAuth($username, $password)`, `timeout(min($timeout, $config timeout))`, `acceptJson()`. Non-2xx → `AiRequestException($this->errorMessage($response, null, $status), $status, $retryAfter)`. Ambil `data` sebagai array; untuk `chat` hanya entri `type === 'chat'`; grup tunggal `'OpenCode'` berisi `id` terurut `sort()`.
- `models(int $timeout = 15)`: `return $this->modelsOfType('chat', $timeout);`
- `chat()`: model kosong → pakai `$config->defaultModel()`; bila tetap kosong → `AiRequestException('Model OpenCode belum dipilih...', null)`. Prompt = `implode("\n\n", array_map(fn ($m) => $m['content'], $messages))`. Request ke `.../api/experimental/generate/text` dengan `['prompt' => $prompt, 'model' => ['id' => $model, 'providerID' => 'opencode']]`. Sukses → `data.text`; bila kosong → `RuntimeException`.
- `retryAfter`: `isset($json['retryAfterMs']) ? (int) round($json['retryAfterMs'] / 1000) : null`; jangan pakai header `Retry-After` (tidak dikirim server OpenCode).
- Pesan error: 401 → `"Username atau password OpenCode ditolak (status 401). Periksa kembali pengaturan OpenCode."`; `_tag === 'ModelNotFoundError'` → `"Model {$model} tidak ditemukan di server OpenCode."`; 429 → `"Server OpenCode membatasi permintaan (429)… Coba lagi dalam {n} detik."` (tanpa `n` → "beberapa saat"); ≥500 → `"Server OpenCode bermasalah (status {$status})."`; lainnya → `"Server OpenCode menolak permintaan (status {$status})."` Sertakan `message` dari body bila ada, dipotong 200 karakter (`Str::limit`).

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter=OpenCodeTextClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/OpenCodeTextClient.php tests/Unit/OpenCodeTextClientTest.php
git commit -m "feat(ai): add OpenCode text client"
```

---

### Task 5: Klien gambar, `AiManager`, `ConnectionTester`, dan rewire service

**Files:**
- Create: `app/Services/Ai/ImageClient.php`
- Create: `app/Services/Ai/OpenAiImageClient.php`
- Create: `app/Services/Ai/OpenCodeImageClient.php`
- Create: `app/Services/Ai/AiManager.php`
- Create: `app/Services/Ai/ConnectionTester.php`
- Modify: `app/Services/AiQuestionService.php`
- Modify: `app/Jobs/GenerateClassCoverJob.php`
- Test: `tests/Unit/OpenCodeImageClientTest.php`, `tests/Unit/AiManagerTest.php`

**Interfaces:**
- Produces:
  - `interface ImageClient { public function generate(string $prompt, string $model, string $size): string; }`
  - `AiManager::__construct(?AiConfig $config = null)`, `config(): AiConfig`, `text(): TextClient`, `image(): ?ImageClient`
  - `ConnectionTester::__construct(AiManager $ai)`, `test(?string $model = null, int $timeout = 25): array{ok: bool, endpoint: array, probe: array|null, model: array<string, array<string>>}`
  - `AiQuestionService::__construct(?AiManager $ai = null)`
- Consumes: `TextClient` (Task 3/4), `AiConfig` (Task 2).

- [ ] **Step 1: Tulis unit test klien gambar dan manager**

```php
// tests/Unit/OpenCodeImageClientTest.php
public function test_generate_memakai_aspect_dan_men-decode_base64(): void
{
    Http::fake(['https://oc.example.test/api/experimental/generate/image' => Http::response([
        'data' => ['images' => [['type' => 'image', 'mime' => 'image/png', 'data' => base64_encode('gambar-biner')]]],
    ])]);

    $bytes = (new OpenCodeImageClient($this->config()))->generate('prompt', 'grok-imagine-image-2.0', '1536x1024');

    $this->assertSame('gambar-biner', $bytes);
    Http::assertSent(fn ($r) => $r['model'] === ['id' => 'grok-imagine-image-2.0', 'providerID' => 'opencode']
        && $r['aspect'] === '16:9' && $r['n'] === 1);
}
```

```php
// tests/Unit/AiManagerTest.php
public function test_text_mengikuti_provider_aktif(): void
{
    $manager = new AiManager(new AiConfig('opencode', [], $this->openCodeConfig(), []));
    $this->assertInstanceOf(OpenCodeTextClient::class, $manager->text());

    $manager = new AiManager(new AiConfig('openai', ['base_url' => 'https://a.test/v1', 'api_key' => 'k', 'model' => 'm', 'timeout' => 10], [], []));
    $this->assertInstanceOf(OpenAiTextClient::class, $manager->text());
}

public function test_image_null_saat_konfigurasi_belum_lengkap(): void
{
    $manager = new AiManager(new AiConfig('opencode', [], ['base_url' => '', 'username' => 'opencode', 'password' => null, 'model' => '', 'timeout' => 120], ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 150]));
    $this->assertNull($manager->image());
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OpenCodeImageClientTest`
Run: `php artisan test --filter=AiManagerTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan klien gambar dan manager**

`OpenAiImageClient::generate()`: `POST {image.base_url}/images/generations` `['model' => $model, 'prompt' => $prompt, 'size' => $size, 'n' => 1]` dengan `withToken(image.api_key)`; ambil `data.0.b64_json`, jika kosong pakai `data.0.url` (unduh via `Http::timeout(90)`); non-2xx / byte kosong → `RuntimeException` bergaya pesan lama.

`OpenCodeImageClient::generate()`: butuh `base_url` + `password`; `POST .../api/experimental/generate/image` `['model' => ['id' => $model, 'providerID' => 'opencode'], 'prompt' => $prompt, 'n' => 1, 'aspect' => '16:9', 'resolution' => '2K']`; `base64_decode(data.images.0.data, true)`; gagal → `RuntimeException` dengan pesan status.

`AiManager`: `config()` memakai `AiConfig::current()` bila konstruktor tidak diberi config. `text()` mengembalikan `OpenCodeTextClient` bila `provider === 'opencode'`, selain itu `OpenAiTextClient`. `image()` mengembalikan klien sesuai provider, atau `null` bila `image.base_url`, kunci/password, atau `image.model` kosong.

`ConnectionTester::test()`: pindahkan algoritma `AiQuestionService::testConnection()` + `probeModel()` apa adanya (clamp timeout 5..30, model fetch timeout ≤10, probe ≤15, pesan "Terhubung ke {host}. {n} model ditemukan."), tetapi memakai `$this->ai->text()`. Kegagalan models/chat ditangkap dari `AiRequestException` sehingga `endpoint.status`/`probe.status` terisi.

- [ ] **Step 4: Rewire `AiQuestionService` dan job cover**

`AiQuestionService`:
- Konstruktor: `public function __construct(?AiManager $ai = null)` → `$this->ai = $ai ?? new AiManager();`
- Hapus properti `$baseUrl`, `$apiKey`, `$defaultModel`, `$timeout`, method `testConnection`, `probeModel`, `pesanGagal`, `pesanChatGagal`, `alasanProvider`, `parseModels`, `detectProvider`, `extractRawContent`.
- `getAvailableModels()`: cache key `'ai_models_'.sha1($this->ai->config()->textBaseUrl())`; ambil `$this->ai->text()->models(10)`; catch `Throwable` → `fallbackModels()`.
- `generateQuestions()` dan `evaluateEssay()`: panggil `$this->ai->text()->chat($messages, $model, $temperature)`; exception `AiRequestException` dibiarkan naik (tidak dibungkus ulang) agar status/retryAfter tetap sampai ke job.
- `fallbackModels()` memakai `$this->ai->config()->defaultModel()`.

`GenerateClassCoverJob::handle()`: ganti blok `Http::...post(.../images/generations)` dengan:
```php
$image = app(AiManager::class)->image();
if ($image === null) { Log::warning('Cover AI dilewati karena konfigurasi image API belum lengkap.', ['kelas_id' => $this->kelasId]); return; }
$bytes = $image->generate($prompt, (string) $settings->image_model, '1536x1024');
```
Pemeriksaan kuota, validasi `getimagesizefromstring`, batas 10 MB, penyimpanan, dan penghapusan cover lama tetap.

- [ ] **Step 5: Jalankan regresi AI yang ada**

Run: `php artisan test --filter="AiQuestionServiceTest|EssayTest|OrganizationQuotaTest"`
Expected: PASS. Jika ada pesan error yang berubah, samakan teksnya dengan klien OpenAI (bukan mengubah test).

- [ ] **Step 6: Jalankan test baru**

Run: `php artisan test --filter="OpenCodeImageClientTest|AiManagerTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Ai app/Services/AiQuestionService.php app/Jobs/GenerateClassCoverJob.php tests/Unit
git commit -m "refactor(ai): route text and image requests through provider clients"
```

---

### Task 6: Controller, validasi, dan endpoint tes OpenCode

**Files:**
- Modify: `app/Http/Controllers/Admin/SettingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OpenCodeSettingsTest.php`

**Interfaces:**
- Consumes: `AiConfig::fromSettings()`, `AiManager`, `ConnectionTester`, `OpenCodeTextClient`.
- Produces: route `admin.settings.opencode.test` dan `admin.settings.opencode.image-models`; penyimpanan `ai_provider` + lima field `opencode_*`.

- [ ] **Step 1: Tambah feature test simpan, validasi, dan tes koneksi**

Tambahkan ke `tests/Feature/OpenCodeSettingsTest.php` (pakai helper `adminWorkspace()` seperti `AiConnectionTest`):

```php
public function test_simpan_provider_opencode_dan_password_kosong_mempertahankan_yang_lama(): void
{
    // Organisasi + admin, OrganizationSetting dengan opencode_password 'lama'
    // PUT admin.settings.update _section=ai dengan:
    // ai_provider=opencode, ai_base_url, ai_default_model, ai_timeout,
    // opencode_base_url=https://oc.example.test, opencode_username=opencode,
    // opencode_password='', opencode_model='dipilih', opencode_timeout=150
    // assert: DB ai_provider 'opencode', opencode_model 'dipilih', opencode_password 'lama'
    // assert: response redirect + session settings_tab 'ai'
}

public function test_provider_tidak_dikenal_ditolak(): void
{
    // PUT dengan ai_provider=lain → assertSessionHasErrors('ai_provider')
}

public function test_tes_koneksi_opencode_mengembalikan_daftar_model_tanpa_password(): void
{
    // Http::fake dua endpoint (api/model dan generate/text)
    // POST admin.settings.opencode.test dengan isian form
    // assertJsonPath('ok', true), endpoint.ok true, model.OpenCode.0 terisi
    // assertStringNotContainsString('rahasia', response content)
    // Http::assertSent: url mengandung 'types=chat', Authorization Basic base64(opencode:rahasia)
}

public function test_tes_koneksi_opencode_memakai_pengaturan_tersimpan_saat_isian_kosong(): void
{
    // OrganizationSetting tersimpan (base_url, password, model)
    // POST dengan semua field kosong → assertJsonPath('endpoint.ok', true), probe memakai model tersimpan
}

public function test_daftar_model_gambar_hanya_menerima_image(): void
{
    // Http::fake api/model?types=image → satu model image
    // POST admin.settings.opencode.image-models → assertJsonPath('model.0', 'grok-imagine-image-2.0')
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OpenCodeSettingsTest`
Expected: FAIL (route/method belum ada).

- [ ] **Step 3: Implementasikan validasi, penyimpanan, dan endpoint**

`SettingController`:
- Aturan section `ai` menjadi:
```php
'ai_provider' => ['nullable', 'in:openai,opencode'],
'opencode_base_url' => ['nullable', 'url', 'max:255'],
'opencode_username' => ['nullable', 'string', 'max:100'],
'opencode_password' => ['nullable', 'string', 'max:500'],
'opencode_model' => ['nullable', 'string', 'max:150'],
'opencode_timeout' => ['nullable', 'integer', 'min:10', 'max:300'],
```
Catatan: `ai_provider` dan `opencode_timeout` dibuat nullable (bukan required) agar request yang menyimpan semua section sekaligus tanpa field OpenCode tetap berhasil; form Admin selalu mengirim keduanya.
- Tambahkan `opencode_password` ke daftar `$secret` yang di-`unset` saat blank (loop `['ai_api_key', 'image_api_key', 'opencode_password']`).
- `testAiConnection()` memakai `ConnectionTester` dengan `new AiManager(new AiConfig('openai', [...dari form/tersimpan...], $current->opencode, $current->image))`. Gunakan `AiConfig::fromSettings(app('app.settings'), $overrides)` dengan `$overrides` key `ai_base_url`, `ai_api_key`, `ai_default_model`, `ai_timeout`.- Method baru `testOpenCodeConnection(Request $request, ConnectionTester $tester): JsonResponse` — validasi field `opencode_*` (nullable seperti di atas), build `AiConfig::fromSettings(app('app.settings'), $overrides)` dengan key `opencode_*`, lalu `$tester->test($model, $timeout)` memakai `new AiManager($config)`. Respons JSON shape sama dengan tes AI.
- Method baru `openCodeImageModels(): JsonResponse` — `$client = new OpenCodeTextClient(AiConfig::current())`, kembalikan `['model' => array_values($client->modelsOfType('image', 15)[$group] ?? [])]`; bila gagal tangkap `AiRequestException` → `['model' => [], 'pesan' => $e->getMessage()]`.

`routes/web.php` (dalam grup admin, setelah `settings.ai.test`):
```php
Route::post('settings/opencode/test', [SettingController::class, 'testOpenCodeConnection'])
    ->middleware('throttle:5,1')->name('settings.opencode.test');
Route::post('settings/opencode/image-models', [SettingController::class, 'openCodeImageModels'])
    ->middleware('throttle:5,1')->name('settings.opencode.image-models');
```

Perbarui import `SettingController`: tambahkan `App\Services\Ai\AiConfig`, `App\Services\Ai\AiManager`, `App\Services\Ai\ConnectionTester`, `App\Services\Ai\OpenCodeTextClient`; hapus `App\Services\AiQuestionService` bila tidak lagi dipakai.

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter="OpenCodeSettingsTest|AiConnectionTest|SettingsSectionTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/SettingController.php routes/web.php tests/Feature/OpenCodeSettingsTest.php
git commit -m "feat(ai): save and test opencode provider from admin settings"
```

---

### Task 7: UI tab Provider AI dan pemilih model gambar

**Files:**
- Create: `resources/views/components/ai-test-panel.blade.php`
- Modify: `resources/views/admin/settings/edit.blade.php:302-434` (section `panel-ai`)
- Modify: `resources/views/admin/settings/edit.blade.php:436-...` (section `panel-cover`, isian `image_model`)
- Test: `tests/Feature/OpenCodeSettingsTest.php`

**Interfaces:**
- Consumes: route `admin.settings.opencode.test`, `admin.settings.opencode.image-models`, `admin.settings.ai.test`.
- Produces: komponen `<x-ai-test-panel :url="…" :inputs="[…]" model-input="…" :label="…" />`; radio `ai_provider`; input `opencode_*`.

- [ ] **Step 1: Tulis test tampilan**

```php
public function test_tab_ai_menampilkan_pilihan_provider_dan_isian_opencode(): void
{
    // GET admin.settings.edit?tab=ai
    // assertSee('OpenCode server'), assertSee('name="ai_provider"', false),
    // assertSee('name="opencode_base_url"', false), assertSee('name="opencode_password"', false),
    // assertSee('name="opencode_model"', false), assertSee('name="opencode_timeout"', false)
    // assertDontSee('opencode_password" value=', false)  // password tidak pernah dirender
}

public function test_tab_cover_menampilkan_tombol_muat_model_gambar_saat_provider_opencode(): void
{
    // OrganizationSetting ai_provider=opencode, GET ?tab=cover
    // assertSee('Muat model gambar')
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="test_tab_ai_menampilkan_pilihan_provider_dan_isian_opencode|test_tab_cover_menampilkan_tombol_muat_model_gambar_saat_provider_opencode"`
Expected: FAIL.

- [ ] **Step 3: Buat komponen dan ubah tab AI**

`resources/views/components/ai-test-panel.blade.php` menerima `$url`, `$inputs` (array `['elemen-id', …]` untuk dikirim sebagai body dengan key = id), `$modelInput` (id input model), `$label`. Isinya sama dengan blok tes koneksi yang sekarang (status, probe, daftar model per grup, klik mengisi `$modelInput`), dengan body dibangun lewat loop `inputs` di Alpine: `Object.fromEntries(@js($inputs).map(id => [id, document.getElementById(id)?.value ?? '']))`.

Section `panel-ai`:
- Pembungkus `<div x-data="{ provider: @js(old('ai_provider', $settings->ai_provider ?? 'openai')) }">`.
- Radio `ai_provider` dua opsi, label "OpenAI-compatible (9Router)" dan "OpenCode server", dengan `x-model="provider"`.
- Panel OpenAI: `<div x-show="provider === 'openai'">` membungkus empat isian lama + `<x-ai-test-panel :url="route('admin.settings.ai.test')" :inputs="['ai_base_url','ai_api_key','ai_default_model','ai_timeout']" model-input="ai_default_model" label="Tes koneksi" />`.
- Panel OpenCode: `<div x-show="provider === 'opencode'" x-cloak>` dengan isian `opencode_base_url`, `opencode_username`, `opencode_password` (placeholder "Kosongkan untuk mempertahankan password saat ini"), `opencode_model`, `opencode_timeout`, lalu `<x-ai-test-panel :url="route('admin.settings.opencode.test')" :inputs="['opencode_base_url','opencode_username','opencode_password','opencode_model','opencode_timeout']" model-input="opencode_model" label="Tes & muat model" />`.
- Teks bantuan tab disesuaikan: menyebut dua pilihan provider.

Section `panel-cover`, di bawah input `image_model` (hanya dirender bila `app('app.settings')->ai_provider === 'opencode'`): tombol "Muat model gambar" (Alpine `fetch` ke route `admin.settings.opencode.image-models` lewat `window.kuisFetch`) yang mengisi `<datalist id="image-model-options">`; input `image_model` memakai `list="image-model-options"`.

- [ ] **Step 4: Jalankan test, pastikan sukses**

Run: `php artisan test --filter=OpenCodeSettingsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/ai-test-panel.blade.php resources/views/admin/settings/edit.blade.php tests/Feature/OpenCodeSettingsTest.php
git commit -m "feat(ai): add provider switch and opencode settings UI"
```

---

### Task 8: Verifikasi menyeluruh

**Files:** tidak ada file baru.

- [ ] **Step 1: Jalankan seluruh test**

Run: `php artisan test`
Expected: PASS seluruh suite, termasuk `AiQuestionServiceTest`, `AiConnectionTest`, `EssayTest`, `SettingsSectionTest`, `OrganizationQuotaTest`, `OpenCodeSettingsTest`.

- [ ] **Step 2: Bersihkan config cache**

Run: `php artisan config:clear`
Expected: `Configuration cache cleared successfully.`

- [ ] **Step 3: Migrasi database pengembangan**

Run: `php artisan migrate`
Expected: migrasi `add_opencode_provider_to_settings` berjalan tanpa error.

- [ ] **Step 4: Verifikasi nyata ke server OpenCode**

Run: `php artisan tinker --execute="dump((new App\Services\Ai\OpenCodeTextClient(App\Services\Ai\AiConfig::current()))->models());"`
Expected: daftar model grup `OpenCode` (butuh `.env` `OPENCODE_URL`/`OPENCODE_PASSWORD` terisi). Bila server membalas 429 untuk generasi, catat sebagai risiko, bukan kegagalan build.

- [ ] **Step 5: Commit**

```bash
git add -A docs/superpowers
git commit -m "docs(ai): record opencode provider design and plan"
```
