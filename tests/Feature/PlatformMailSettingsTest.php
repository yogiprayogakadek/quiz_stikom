<?php

namespace Tests\Feature;

use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\PlatformMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class PlatformMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_dapat_membuka_pengaturan_email(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->get(route('master.mail.edit'))
            ->assertOk()
            ->assertSee('Email platform')
            ->assertSee('Kirim email uji');
    }

    public function test_admin_organisasi_ditolak_dari_pengaturan_email(): void
    {
        $admin = User::create([
            'nama' => 'Admin Organisasi',
            'username' => 'admin_organisasi_mail',
            'email' => 'admin-organisasi@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'admin-mail-token',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-mail-token'])
            ->get(route('master.mail.edit'))
            ->assertForbidden();
    }

    public function test_pengaturan_smtp_disimpan_dan_password_terenkripsi(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->put(route('master.mail.update'), [
                'mail_host' => 'smtp.example.test',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'mailer',
                'mail_password' => 'rahasia-smtp',
                'mail_from_address' => 'no-reply@example.test',
                'mail_from_name' => 'Platform Uji',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $stored = DB::table('app_settings')->where('id', 1)->first();
        $this->assertNotSame('rahasia-smtp', $stored->mail_password);
        $this->assertSame('rahasia-smtp', AppSetting::current()->mail_password);
        $this->assertSame('smtp.example.test', AppSetting::current()->mail_host);
    }

    public function test_password_kosong_mempertahankan_password_lama(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-mail-token'])
            ->put(route('master.mail.update'), [
                'mail_host' => 'smtp.example.test',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'mailer',
                'mail_password' => 'rahasia-lama',
                'mail_from_address' => 'no-reply@example.test',
                'mail_from_name' => 'Platform Uji',
            ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-mail-token'])
            ->put(route('master.mail.update'), [
                'mail_host' => 'smtp.example.test',
                'mail_port' => 2525,
                'mail_encryption' => 'tls',
                'mail_username' => 'mailer',
                'mail_password' => '',
                'mail_from_address' => 'no-reply@example.test',
                'mail_from_name' => 'Platform Uji',
            ])
            ->assertRedirect();

        $this->assertSame('rahasia-lama', AppSetting::current()->mail_password);
        $this->assertSame(2525, AppSetting::current()->mail_port);
    }

    public function test_pengaturan_smtp_divalidasi(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->put(route('master.mail.update'), [
                'mail_host' => 'smtp.example.test',
                'mail_port' => 70000,
                'mail_encryption' => 'plain',
                'mail_from_address' => 'bukan-email',
            ])
            ->assertSessionHasErrors(['mail_port', 'mail_encryption', 'mail_from_address']);
    }

    public function test_platform_mail_menerapkan_tls_ssl_dan_fallback(): void
    {
        AppSetting::create([
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'mail_from_address' => 'no-reply@example.test',
            'mail_from_name' => 'Platform Uji',
        ]);

        PlatformMail::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('no-reply@example.test', config('mail.from.address'));

        AppSetting::current()->update([
            'mail_port' => null,
            'mail_encryption' => 'ssl',
        ]);

        PlatformMail::apply();

        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'env-host']);
        AppSetting::current()->update(['mail_host' => null]);

        PlatformMail::apply();

        $this->assertSame('log', config('mail.default'));
        $this->assertSame('env-host', config('mail.mailers.smtp.host'));
    }

    public function test_email_uji_terkirim(): void
    {
        Mail::fake();

        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->post(route('master.mail.test'), ['test_email' => 'tujuan@example.test'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail) => $mail->hasTo('tujuan@example.test'));
    }

    public function test_email_uji_gagal_menampilkan_error(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->post(route('master.mail.test'), ['test_email' => 'tujuan@example.test'])
            ->assertSessionHasErrors('test_email');
    }

    public function test_navigasi_master_menampilkan_tautan_email(): void
    {
        $this->actingAs($this->master())
            ->withSession(['auth_token' => 'master-mail-token'])
            ->get(route('master.mail.edit'))
            ->assertOk()
            ->assertSee(route('master.mail.edit'))
            ->assertSee(route('master.system.index'));
    }

    private function master(): User
    {
        $master = User::create([
            'nama' => 'Master Platform',
            'username' => 'master_mail',
            'email' => 'master@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-mail-token',
        ]);
        $master->forceFill(['email_verified_at' => now()])->save();

        return $master;
    }
}
