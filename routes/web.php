<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\RemedialController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Auth\AccountRecoveryController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizationRegistrationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Master\MailSettingController;
use App\Http\Controllers\Master\OrganizationController;
use App\Http\Controllers\Master\OwnerController;
use App\Http\Controllers\Master\ParticipantController as MasterParticipantController;
use App\Http\Controllers\Master\ParticipantFeedbackController as MasterParticipantFeedbackController;
use App\Http\Controllers\Master\SystemController;
use App\Http\Controllers\Master\SystemHealthController;
use App\Http\Controllers\Master\WorkspaceRequestController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\Peserta\AttemptController;
use App\Http\Controllers\Peserta\FeedbackController as PesertaFeedbackController;
use App\Http\Controllers\Peserta\KelasController as PesertaKelasController;
use App\Http\Controllers\Peserta\SessionController as PesertaSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/organisasi/cari', [HomeController::class, 'search'])
    ->middleware('throttle:60,1')
    ->name('organizations.search');

Route::get('/login', fn () => redirect()->route('home'));

Route::get('/syarat', [LegalController::class, 'terms'])->name('terms.show');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('privacy.show');
Route::get('/o/{organization:slug}/syarat', [LegalController::class, 'terms'])->name('organizations.terms');
Route::get('/o/{organization:slug}/privasi', [LegalController::class, 'privacy'])->name('organizations.privacy');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [OrganizationRegistrationController::class, 'create'])->name('register.organization');
    Route::post('/daftar', [OrganizationRegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.organization.store');
    Route::get('/o/{organization:slug}/login', [LoginController::class, 'showOrganization'])->name('organizations.login');
    Route::post('/o/{organization:slug}/login', [LoginController::class, 'login'])->name('organizations.login.store');
    Route::get('/lupa-password', [AccountRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/lupa-password', [AccountRecoveryController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AccountRecoveryController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AccountRecoveryController::class, 'reset'])->name('password.update');
});

Route::prefix('master')->name('master.')->middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showMaster'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/ganti-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/ganti-password', [PasswordController::class, 'update'])->name('password.change.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/organisasi/aktif', OrganizationSwitchController::class)->name('organizations.switch');
    Route::get('/email/verifikasi', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verifikasi/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verifikasi', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/display/{token}', [DisplayController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('display.show');

Route::get('/display/{token}/state', [DisplayController::class, 'state'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('display.state');

Route::prefix('master')->name('master.')->middleware(['auth', 'platform_admin'])->group(function () {
    Route::get('/', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('owners', [OwnerController::class, 'index'])->name('owners.index');
    Route::delete('owners/{owner}', [OwnerController::class, 'destroy'])->name('owners.destroy');
    Route::get('participants', [MasterParticipantController::class, 'index'])->name('participants.index');
    Route::post('participants/link', [MasterParticipantController::class, 'link'])->name('participants.link');
    Route::post('participants/verify', [MasterParticipantController::class, 'verify'])->name('participants.verify');
    Route::get('feedback', [MasterParticipantFeedbackController::class, 'index'])->name('feedback.index');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('organizations/{organization}/owner', [OwnerController::class, 'editTransfer'])->name('organizations.owner.edit');
    Route::put('organizations/{organization}/owner', [OwnerController::class, 'updateTransfer'])->name('organizations.owner.update');
    Route::get('workspace-requests/{workspaceRequest}/approve', [WorkspaceRequestController::class, 'approveForm'])->name('workspace-requests.approve');
    Route::put('workspace-requests/{workspaceRequest}/approve', [WorkspaceRequestController::class, 'approve'])->name('workspace-requests.approve.update');
    Route::put('workspace-requests/{workspaceRequest}/reject', [WorkspaceRequestController::class, 'reject'])->name('workspace-requests.reject');
    Route::get('organizations/{organization}/kuota', [OrganizationController::class, 'editLimits'])->name('organizations.limits.edit');
    Route::put('organizations/{organization}/kuota', [OrganizationController::class, 'updateLimits'])->name('organizations.limits.update');
    Route::get('organizations/{organization}/nama', [OrganizationController::class, 'editName'])->name('organizations.name.edit');
    Route::put('organizations/{organization}/nama', [OrganizationController::class, 'updateName'])->name('organizations.name.update');
    Route::get('organizations/{organization}/export', [OrganizationController::class, 'export'])->name('organizations.export');
    Route::get('organizations/{organization}/delete', [OrganizationController::class, 'showDelete'])->name('organizations.delete');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
    Route::put('organizations/{organization}/status', [OrganizationController::class, 'updateStatus'])->name('organizations.status.update');
    Route::get('email', [MailSettingController::class, 'edit'])->name('mail.edit');
    Route::put('email', [MailSettingController::class, 'update'])->name('mail.update');
    Route::post('email/test', [MailSettingController::class, 'sendTest'])->middleware('throttle:3,1')->name('mail.test');
    Route::get('system', [SystemHealthController::class, 'index'])->name('system.index');
    Route::post('system/backups', [SystemHealthController::class, 'backupStore'])->name('system.backups.store');
    Route::get('system/backups/{nama}', [SystemHealthController::class, 'backupDownload'])
        ->where('nama', '[A-Za-z0-9._-]+')
        ->name('system.backups.download');
    Route::delete('system/backups/{nama}', [SystemHealthController::class, 'backupDestroy'])
        ->where('nama', '[A-Za-z0-9._-]+')
        ->name('system.backups.destroy');
    Route::post('system/failed-jobs/{id}/retry', [SystemHealthController::class, 'retryFailedJob'])
        ->name('system.failed-jobs.retry');
    Route::delete('system/failed-jobs/{id}', [SystemHealthController::class, 'forgetFailedJob'])
        ->name('system.failed-jobs.forget');
    Route::put('system/maintenance', [SystemController::class, 'updateMaintenance'])->name('maintenance.update');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin', 'organization_member'])->group(function () {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/dashboard/search', [DashboardController::class, 'adminSearch'])->name('dashboard.search');
    Route::get('/dashboard/peserta-online', [DashboardController::class, 'pesertaOnline'])->name('dashboard.peserta-online');
    Route::get('workspace', [WorkspaceController::class, 'index'])->name('workspace.index');
    Route::post('workspace/requests', [WorkspaceController::class, 'storeRequest'])
        ->middleware('throttle:5,1')
        ->name('workspace.requests.store');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('backups/{nama}', [BackupController::class, 'download'])
        ->where('nama', '[A-Za-z0-9._-]+')
        ->name('backups.download');
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::middleware('organization_owner')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{admin}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
    Route::delete('backups/{nama}', [BackupController::class, 'destroy'])
        ->where('nama', '[A-Za-z0-9._-]+')
        ->name('backups.destroy');

    Route::get('participants/import', [ParticipantController::class, 'importForm'])->name('participants.import');
    Route::post('participants/import', [ParticipantController::class, 'import'])->name('participants.import.store');
    Route::get('participants/import/result', [ParticipantController::class, 'importResult'])->name('participants.import.result');
    Route::get('participants/template', [ParticipantController::class, 'template'])->name('participants.template');
    Route::post('participants/link', [ParticipantController::class, 'link'])->name('participants.link');
    Route::resource('participants', ParticipantController::class)->except(['show']);
    Route::post('participants/{participant}/reset-password', [ParticipantController::class, 'resetPassword'])
        ->name('participants.reset-password');
    Route::post('participants/{participant}/verify', [ParticipantController::class, 'verify'])
        ->name('participants.verify');

    Route::get('quizzes/{quiz}/questions/template', [QuestionController::class, 'template'])->name('quizzes.questions.template');
    Route::get('quizzes/{quiz}/questions/export', [QuestionController::class, 'export'])->name('quizzes.questions.export');
    Route::get('quizzes/{quiz}/questions/import', [QuestionController::class, 'importForm'])->name('quizzes.questions.import');
    Route::post('quizzes/{quiz}/questions/import', [QuestionController::class, 'import'])->name('quizzes.questions.import.store');
    Route::get('quizzes/{quiz}/questions/import/result', [QuestionController::class, 'importResult'])->name('quizzes.questions.import.result');
    Route::get('quizzes/{quiz}/questions/ai-generate', [QuestionController::class, 'aiForm'])->name('quizzes.questions.ai.form');
    Route::post('quizzes/{quiz}/questions/ai-generate', [QuestionController::class, 'aiGenerate'])->name('quizzes.questions.ai.generate');
    Route::post('quizzes/{quiz}/questions/ai-store', [QuestionController::class, 'aiStore'])->name('quizzes.questions.ai.store');
    Route::get('quizzes/{quiz}/questions/ai-generate/{generation}/progress', [QuestionController::class, 'aiProgress'])->name('quizzes.questions.ai.progress');
    Route::get('quizzes/{quiz}/questions/ai-generate/{generation}/state', [QuestionController::class, 'aiProgressState'])->name('quizzes.questions.ai.state');
    Route::get('quizzes/{quiz}/questions/ai-generate/{generation}/pratinjau', [QuestionController::class, 'aiPreview'])->name('quizzes.questions.ai.preview');
    Route::delete('quizzes/{quiz}/questions/bulk', [QuestionController::class, 'bulkDestroy'])->name('quizzes.questions.bulk-destroy');
    Route::resource('quizzes.questions', QuestionController::class);

    // Backward-compatible question routes (redirects / fallback if needed)
    Route::get('questions/template', [QuestionController::class, 'template'])->name('questions.template');
    Route::resource('quizzes', QuizController::class)->except(['show']);
    Route::resource('classes', KelasController::class)->except(['show', 'create'])->parameters(['classes' => 'kelas']);
    Route::post('classes/{kelas}/regenerate-cover', [KelasController::class, 'regenerateCover'])->name('classes.regenerate-cover');

    Route::get('sessions/{session}/laporan', [ReportController::class, 'session'])->name('reports.session');
    Route::get('sessions/{session}/laporan/csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('sessions/{session}/laporan/berita-acara', [ReportController::class, 'beritaAcara'])->name('reports.berita-acara');
    Route::get('attempts/{attempt}', [ReportController::class, 'attempt'])->name('reports.attempt');
    Route::post('attempts/{attempt}/nilai', [ReportController::class, 'nilaiEssay'])->name('attempts.nilai');
    Route::post('answers/{answer}/saran-ai', [ReportController::class, 'saranNilaiEssay'])->name('answers.ai-suggestion');
    Route::post('attempts/{attempt}/reset', [ReportController::class, 'resetAttempt'])->name('attempts.reset');

    Route::get('sessions/{session}/remidi', [RemedialController::class, 'create'])->name('sessions.remidi.create');
    Route::post('sessions/{session}/remidi', [RemedialController::class, 'store'])->name('sessions.remidi.store');
    Route::post('sessions/{session}/remidi/kuis', [RemedialController::class, 'buatKuis'])->name('sessions.remidi.kuis');
    Route::get('sessions/{session}/remidi/laporan', [RemedialController::class, 'laporan'])->name('sessions.remidi.laporan');
    Route::post('sessions/{session}/remidi/terapkan', [RemedialController::class, 'terapkan'])->name('sessions.remidi.terapkan');
    Route::get('sessions/{session}/remidi/csv', [RemedialController::class, 'export'])->name('sessions.remidi.export');

    Route::resource('sessions', AdminSessionController::class);
    Route::post('sessions/{session}/lobby', [AdminSessionController::class, 'openLobby'])->name('sessions.lobby');
    Route::post('sessions/{session}/mulai', [AdminSessionController::class, 'start'])->name('sessions.start');
    Route::post('sessions/{session}/peserta', [AdminSessionController::class, 'updateParticipants'])->name('sessions.participants.update');
    Route::get('sessions/{session}/monitor', [AdminSessionController::class, 'monitor'])->name('sessions.monitor');
    Route::post('sessions/{session}/tutup', [AdminSessionController::class, 'close'])->name('sessions.close');
    Route::post('sessions/{session}/pembahasan', [AdminSessionController::class, 'pembahasan'])->name('sessions.pembahasan');
    Route::post('sessions/{session}/lewati-soal', [AdminSessionController::class, 'skip'])->name('sessions.skip');
    Route::post('sessions/{session}/peserta/{attempt}/kumpulkan', [AdminSessionController::class, 'forceFinish'])
        ->name('sessions.attempts.finish');
    Route::post('sessions/{session}/peserta/{attempt}/tambah-waktu', [AdminSessionController::class, 'addTime'])
        ->name('sessions.attempts.add-time');
    Route::post('sessions/{session}/tambah-waktu', [AdminSessionController::class, 'addTimeBulk'])
        ->name('sessions.attempts.add-time-bulk');
    Route::post('sessions/{session}/peserta/{attempt}/reset', [AdminSessionController::class, 'resetAttempt'])
        ->name('sessions.attempts.reset');
    Route::post('sessions/{session}/peserta/{attempt}/keluarkan', [AdminSessionController::class, 'kick'])
        ->name('sessions.attempts.kick');
});

Route::middleware(['auth', 'role:peserta', 'organization_member'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'peserta'])->name('peserta.dashboard');
    Route::get('/dashboard/state', [DashboardController::class, 'pesertaState'])->name('peserta.dashboard.state');
    Route::post('/dashboard/panduan-selesai', [DashboardController::class, 'selesaikanTour'])->name('peserta.tour.selesai');

    Route::get('/kelas', [PesertaKelasController::class, 'index'])->name('peserta.kelas.index');
    Route::get('/kelas/{kelas}', [PesertaKelasController::class, 'show'])->name('peserta.kelas.show');
    Route::post('/kelas/{kelas}/masuk', [PesertaKelasController::class, 'masuk'])
        ->middleware('throttle:10,1')
        ->name('peserta.kelas.masuk');

    Route::post('/sesi/{session}/gabung', [PesertaSessionController::class, 'join'])->name('peserta.session.join');
    Route::get('/sesi/{session}/kerjakan', [PesertaSessionController::class, 'play'])->name('peserta.session.play');
    Route::get('/sesi/{session}/state', [PesertaSessionController::class, 'state'])->name('peserta.session.state');
    Route::post('/sesi/{session}/jawab', [PesertaSessionController::class, 'answer'])
        ->middleware('throttle:answers')
        ->name('peserta.session.answer');
    Route::get('/sesi/{session}/hasil', [PesertaSessionController::class, 'result'])->name('peserta.session.result');

    Route::post('/sesi/{session}/mulai', [AttemptController::class, 'start'])->name('peserta.attempt.start');
    Route::get('/attempt/{attempt}', [AttemptController::class, 'show'])->name('peserta.attempt.show');
    Route::post('/attempt/{attempt}/jawab', [AttemptController::class, 'answer'])
        ->middleware('throttle:answers')
        ->name('peserta.attempt.answer');
    Route::post('/attempt/{attempt}/selesai', [AttemptController::class, 'finish'])->name('peserta.attempt.finish');
    Route::get('/attempt/{attempt}/status', [AttemptController::class, 'status'])->name('peserta.attempt.status');
    Route::post('/attempt/{attempt}/pelanggaran', [AttemptController::class, 'pelanggaran'])->name('peserta.attempt.pelanggaran');
    Route::get('/attempt/{attempt}/hasil', [AttemptController::class, 'result'])->name('peserta.attempt.result');
    Route::post('/attempt/{attempt}/masukan', [PesertaFeedbackController::class, 'store'])->name('peserta.attempt.feedback');
    Route::post('/attempt/{attempt}/events', [AttemptController::class, 'event'])
        ->middleware('throttle:attempt-events')
        ->name('peserta.attempt.events');
    Route::post('/attempt/{attempt}/tandai', [AttemptController::class, 'tandai'])->name('peserta.attempt.tandai');
    Route::post('/sesi/{session}/pelanggaran', [PesertaSessionController::class, 'pelanggaran'])->name('peserta.session.pelanggaran');
});
