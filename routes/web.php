<?php

use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\Peserta\AttemptController;
use App\Http\Controllers\Peserta\KelasController as PesertaKelasController;
use App\Http\Controllers\Peserta\SessionController as PesertaSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/ganti-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/ganti-password', [PasswordController::class, 'update'])->name('password.change.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::get('/display/{token}', [DisplayController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('display.show');

Route::get('/display/{token}/state', [DisplayController::class, 'state'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('display.state');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/dashboard/search', [DashboardController::class, 'adminSearch'])->name('dashboard.search');
    Route::get('/dashboard/peserta-online', [DashboardController::class, 'pesertaOnline'])->name('dashboard.peserta-online');

    Route::get('participants/import', [ParticipantController::class, 'importForm'])->name('participants.import');
    Route::post('participants/import', [ParticipantController::class, 'import'])->name('participants.import.store');
    Route::get('participants/import/result', [ParticipantController::class, 'importResult'])->name('participants.import.result');
    Route::get('participants/template', [ParticipantController::class, 'template'])->name('participants.template');
    Route::resource('participants', ParticipantController::class)->except(['show']);
    Route::post('participants/{participant}/reset-password', [ParticipantController::class, 'resetPassword'])
        ->name('participants.reset-password');

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
    Route::resource('quizzes.questions', QuestionController::class);

    // Backward-compatible question routes (redirects / fallback if needed)
    Route::get('questions/template', [QuestionController::class, 'template'])->name('questions.template');
    Route::resource('quizzes', QuizController::class)->except(['show']);
    Route::resource('classes', KelasController::class)->except(['show', 'create'])->parameters(['classes' => 'kelas']);

    Route::get('sessions/{session}/laporan', [ReportController::class, 'session'])->name('reports.session');
    Route::get('sessions/{session}/laporan/csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('sessions/{session}/laporan/berita-acara', [ReportController::class, 'beritaAcara'])->name('reports.berita-acara');
    Route::get('attempts/{attempt}', [ReportController::class, 'attempt'])->name('reports.attempt');
    Route::post('attempts/{attempt}/nilai', [ReportController::class, 'nilaiEssay'])->name('attempts.nilai');
    Route::post('attempts/{attempt}/reset', [ReportController::class, 'resetAttempt'])->name('attempts.reset');

    Route::resource('sessions', AdminSessionController::class);
    Route::post('sessions/{session}/lobby', [AdminSessionController::class, 'openLobby'])->name('sessions.lobby');
    Route::post('sessions/{session}/mulai', [AdminSessionController::class, 'start'])->name('sessions.start');
    Route::post('sessions/{session}/peserta', [AdminSessionController::class, 'updateParticipants'])->name('sessions.participants.update');
    Route::get('sessions/{session}/monitor', [AdminSessionController::class, 'monitor'])->name('sessions.monitor');
    Route::post('sessions/{session}/tutup', [AdminSessionController::class, 'close'])->name('sessions.close');
    Route::post('sessions/{session}/lewati-soal', [AdminSessionController::class, 'skip'])->name('sessions.skip');
    Route::post('sessions/{session}/peserta/{attempt}/kumpulkan', [AdminSessionController::class, 'forceFinish'])
        ->name('sessions.attempts.finish');
    Route::post('sessions/{session}/peserta/{attempt}/tambah-waktu', [AdminSessionController::class, 'addTime'])
        ->name('sessions.attempts.add-time');
    Route::post('sessions/{session}/peserta/{attempt}/reset', [AdminSessionController::class, 'resetAttempt'])
        ->name('sessions.attempts.reset');
    Route::post('sessions/{session}/peserta/{attempt}/keluarkan', [AdminSessionController::class, 'kick'])
        ->name('sessions.attempts.kick');
});

Route::middleware(['auth', 'role:peserta'])->group(function () {
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
    Route::post('/attempt/{attempt}/events', [AttemptController::class, 'event'])
        ->middleware('throttle:attempt-events')
        ->name('peserta.attempt.events');
    Route::post('/sesi/{session}/pelanggaran', [PesertaSessionController::class, 'pelanggaran'])->name('peserta.session.pelanggaran');
});
