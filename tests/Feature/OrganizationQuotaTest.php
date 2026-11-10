<?php

namespace Tests\Feature;

use App\Jobs\GenerateAiQuestionsJob;
use App\Jobs\GenerateClassCoverJob;
use App\Models\AiGeneration;
use App\Models\AppSetting;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Quiz;
use App\Models\User;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_limit_organisasi_dan_default_platform(): void
    {
        config([
            'platform.ai_monthly_requests' => 100,
            'platform.storage_mb' => 2,
            'platform.admin_limit' => 3,
            'platform.participant_limit' => 4,
            'platform.class_limit' => 5,
        ]);

        $organization = $this->organization([
            'ai_monthly_requests' => 7,
            'storage_mb' => 5,
            'admin_limit' => 2,
            'participant_limit' => 8,
            'class_limit' => 6,
        ]);

        $usage = app(OrganizationUsage::class);

        $this->assertSame(7, $usage->aiLimit($organization->id));
        $this->assertSame(5 * 1024 * 1024, $usage->storageLimitBytes($organization->id));
        $this->assertSame(2, $usage->adminLimit($organization->id));
        $this->assertSame(8, $usage->participantLimit($organization->id));
        $this->assertSame(6, $usage->classLimit($organization->id));

        $default = $this->organization(['slug' => 'default-limit']);

        $this->assertSame(100, $usage->aiLimit($default->id));
        $this->assertSame(2 * 1024 * 1024, $usage->storageLimitBytes($default->id));
        $this->assertSame(3, $usage->adminLimit($default->id));
        $this->assertSame(4, $usage->participantLimit($default->id));
        $this->assertSame(5, $usage->classLimit($default->id));
    }

    public function test_kuota_admin_peserta_dan_kelas_ditegakkan(): void
    {
        $organization = $this->organization([
            'admin_limit' => 1,
            'participant_limit' => 0,
            'class_limit' => 0,
        ]);
        $owner = $this->owner($organization);

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner-token'])
            ->post(route('admin.team.store'), [
                'nama' => 'Admin Baru',
                'username' => 'admin-baru-kuota',
                'email' => 'admin-baru-kuota@example.test',
            ])
            ->assertSessionHasErrors('nama');

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner-token'])
            ->post(route('admin.participants.store'), [
                'nama' => 'Peserta Baru',
                'username' => 'peserta-baru-kuota',
                'email' => 'peserta-baru-kuota@example.test',
            ])
            ->assertSessionHasErrors('nama');

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner-token'])
            ->post(route('admin.classes.store'), [
                'nama' => 'Kelas Baru',
                'pin' => 'KUOTA1',
            ])
            ->assertSessionHasErrors('nama');

        $this->assertDatabaseMissing('users', ['username' => 'admin-baru-kuota']);
        $this->assertDatabaseMissing('users', ['username' => 'peserta-baru-kuota']);
        $this->assertDatabaseMissing('kelas', ['pin' => 'KUOTA1']);
    }

    public function test_import_peserta_berhenti_saat_kuota_penuh(): void
    {
        $organization = $this->organization(['participant_limit' => 1]);
        $owner = $this->owner($organization);

        $csv = "nama,username\nPeserta Satu,satu-kuota\nPeserta Dua,dua-kuota\n";
        $file = UploadedFile::fake()->createWithContent('peserta.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner-token'])
            ->post(route('admin.participants.import.store'), ['file' => $file])
            ->assertRedirect(route('admin.participants.import.result'));

        $hasil = session('impor_hasil');

        $this->assertSame(1, $hasil['ringkasan']['berhasil']);
        $this->assertSame(1, $hasil['ringkasan']['gagal']);
        $this->assertSame('Kuota peserta workspace penuh.', $hasil['ringkasan']['baris'][1]['alasan']);
    }

    public function test_master_dapat_mengubah_kuota_organisasi(): void
    {
        $master = User::create([
            'nama' => 'Master Platform',
            'username' => 'master-kuota',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-token',
        ]);
        $organization = $this->organization();

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->put(route('master.organizations.limits.update', $organization), [
                'ai_monthly_requests' => 25,
                'storage_mb' => 64,
                'admin_limit' => 2,
                'participant_limit' => 30,
                'class_limit' => 4,
            ])
            ->assertRedirect();

        $organization->refresh();

        $this->assertSame(25, $organization->ai_monthly_requests);
        $this->assertSame(64, $organization->storage_mb);
        $this->assertSame(2, $organization->admin_limit);
        $this->assertSame(30, $organization->participant_limit);
        $this->assertSame(4, $organization->class_limit);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization_limits_updated',
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
    }

    public function test_job_ai_questions_memakai_provider_organisasi(): void
    {
        config([
            'services.ai.base_url' => 'https://platform-ai.example.test/v1',
            'services.ai.api_key' => 'platform-key',
            'services.ai.default_model' => 'platform-model',
        ]);

        AppSetting::create([
            'ai_base_url' => 'https://platform-ai.example.test/v1',
            'ai_api_key' => 'platform-key',
            'ai_default_model' => 'platform-model',
        ]);

        $organization = $this->organization();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_base_url' => 'https://org-ai.example.test/v1',
            'ai_api_key' => 'org-key',
            'ai_default_model' => 'org-model',
        ]);

        $owner = $this->owner($organization);
        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'Kuis Kuota',
            'created_by' => $owner->id,
        ]);
        $generation = AiGeneration::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'quiz_id' => $quiz->id,
            'status' => 'pending',
            'params' => [
                'topik' => 'HTTP',
                'jumlah_pg' => 1,
                'jumlah_essay' => 0,
                'nilai_maksimal' => 100,
                'porsi_pg' => 100,
            ],
        ]);

        Http::fake([
            'https://org-ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'questions' => [[
                                'teks' => 'Apa itu HTTP?',
                                'tipe' => 'pilihan_ganda',
                                'bobot' => 100,
                                'durasi_detik' => 60,
                                'pembahasan' => 'Protokol transfer hypertext.',
                                'opsi' => [
                                    ['teks' => 'Hypertext Transfer Protocol', 'is_correct' => true],
                                    ['teks' => 'High Text Protocol', 'is_correct' => false],
                                    ['teks' => 'Hyper Transfer Text', 'is_correct' => false],
                                    ['teks' => 'Home Tool Transfer Protocol', 'is_correct' => false],
                                ],
                            ]],
                        ]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateAiQuestionsJob($generation->id))->handle(app(OrganizationContext::class));

        $this->assertSame('selesai', $generation->fresh()->status);
        Http::assertSent(fn ($request) => $request->url() === 'https://org-ai.example.test/v1/chat/completions');
    }

    public function test_job_cover_memakai_provider_organisasi(): void
    {
        Storage::fake('public');

        AppSetting::create([
            'class_cover_mode' => 'ai',
            'image_base_url' => 'https://platform-image.example.test/v1',
            'image_api_key' => 'platform-image-key',
            'image_model' => 'platform-image-model',
        ]);

        $organization = $this->organization([
            'ai_monthly_requests' => 10,
            'storage_mb' => 10,
        ]);
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'class_cover_mode' => 'ai',
            'image_base_url' => 'https://org-image.example.test/v1',
            'image_api_key' => 'org-image-key',
            'image_model' => 'org-image-model',
        ]);

        $kelas = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Kuota',
            'pin' => 'KUOTA2',
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=');
        Http::fake([
            'https://org-image.example.test/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($png)]],
            ]),
        ]);

        (new GenerateClassCoverJob($kelas->id))->handle(
            app(OrganizationUsage::class),
            app(OrganizationContext::class),
        );

        $this->assertNotNull($kelas->fresh()->cover_path);
        Http::assertSent(fn ($request) => $request->url() === 'https://org-image.example.test/v1/images/generations');
    }

    private function organization(array $override = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organisasi Kuota',
            'slug' => 'organisasi-kuota',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ], $override));
    }

    private function owner(Organization $organization): User
    {
        $owner = User::create([
            'nama' => 'Owner Kuota',
            'username' => 'owner-kuota-'.$organization->id,
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'owner-token',
        ]);

        $organization->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        return $owner;
    }
}
