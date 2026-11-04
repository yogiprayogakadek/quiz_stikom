<?php

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
    Route::post('/login', [LoginController::class, 'login']);
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

    Route::get('participants/import', [ParticipantController::class, 'importForm'])->name('participants.import');
    Route::post('participants/import', [ParticipantController::class, 'import'])->name('participants.import.store');
    Route::get('participants/import/result', [ParticipantController::class, 'importResult'])->name('participants.import.result');
    Route::get('participants/template', [ParticipantController::class, 'template'])->name('participants.template');
    Route::resource('participants', ParticipantController::class)->except(['show']);
    Route::post('participants/{participant}/reset-password', [ParticipantController::class, 'resetPassword'])
        ->name('participants.reset-password');

    Route::get('questions/import', [QuestionController::class, 'importForm'])->name('questions.import');
    Route::post('questions/import', [QuestionController::class, 'import'])->name('questions.import.store');
    Route::get('questions/import/result', [QuestionController::class, 'importResult'])->name('questions.import.result');
    Route::get('questions/template', [QuestionController::class, 'template'])->name('questions.template');
    Route::resource('questions', QuestionController::class)->except(['show']);
    Route::resource('quizzes', QuizController::class)->except(['show']);

    Route::get('sessions/{session}/laporan', [ReportController::class, 'session'])->name('reports.session');
    Route::get('sessions/{session}/laporan/csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('attempts/{attempt}', [ReportController::class, 'attempt'])->name('reports.attempt');
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
    Route::post('sessions/{session}/peserta/{attempt}/keluarkan', [AdminSessionController::class, 'kick'])
        ->name('sessions.attempts.kick');
});

Route::middleware(['auth', 'role:peserta'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'peserta'])->name('peserta.dashboard');
    Route::get('/dashboard/state', [DashboardController::class, 'pesertaState'])->name('peserta.dashboard.state');

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
