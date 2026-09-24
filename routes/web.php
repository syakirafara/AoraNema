<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;

Route::get('/', [HomeController::class, 'index']);

Route::get('/film', [MovieController::class, 'index']);

Route::get('/film/{slug}', [MovieController::class, 'show'])->where('slug', '[a-z0-9-]+');

Route::middleware(['auth', \App\Http\Middleware\IsUser::class])->group(function () {
    Route::get('/kursi/{slug}', [BookingController::class, 'pilihKursi'])->where('slug', '[a-z0-9-]+');
    Route::get('/bayar/{slug}', [BookingController::class, 'halamanBayar'])->where('slug', '[a-z0-9-]+');
    Route::post('/proses-bayar/{slug}', [BookingController::class, 'prosesBayar']);
    Route::get('/tiket-saya', [BookingController::class, 'tiketSaya']);
    Route::post('/tiket-saya/nilai', [BookingController::class, 'nilaiFilm']);
    Route::get('/feedback', [\App\Http\Controllers\FeedbackController::class, 'create']);
    Route::post('/feedback', [\App\Http\Controllers\FeedbackController::class, 'store']);
});

// Pemberitahuan pembayaran dari server Midtrans. Tidak butuh login, keasliannya dicek lewat tanda tangan.
Route::post('/midtrans/notifikasi', [BookingController::class, 'notifikasiMidtrans']);

Route::middleware('auth')->group(function () {
    Route::get('/tiket/{booking_code}', [BookingController::class, 'halamanTiket']);
    Route::post('/keluar', [AuthController::class, 'logout']);
});

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login']);

    Route::get('/daftar', [AuthController::class, 'showRegisterForm']);
    Route::post('/daftar', [AuthController::class, 'register']);
});

// Halaman admin, hanya untuk akun dengan role admin.
Route::prefix('admin')->middleware(['auth', IsAdmin::class])->group(function () {
    Route::redirect('/', '/admin/film');

    Route::get('/film', [Admin\FilmController::class, 'index']);
    Route::get('/film/baru', [Admin\FilmController::class, 'create']);
    Route::post('/film', [Admin\FilmController::class, 'store']);
    Route::get('/film/{movie}/ubah', [Admin\FilmController::class, 'edit']);
    Route::put('/film/{movie}', [Admin\FilmController::class, 'update']);
    Route::delete('/film/{movie}', [Admin\FilmController::class, 'destroy']);
    Route::post('/film/{movie}/arsip', [Admin\FilmController::class, 'arsip']);

    Route::get('/studio', [Admin\StudioController::class, 'index']);
    Route::get('/studio/baru', [Admin\StudioController::class, 'create']);
    Route::post('/studio', [Admin\StudioController::class, 'store']);
    Route::get('/studio/{studio}/ubah', [Admin\StudioController::class, 'edit']);
    Route::put('/studio/{studio}', [Admin\StudioController::class, 'update']);
    Route::delete('/studio/{studio}', [Admin\StudioController::class, 'destroy']);

    Route::get('/jadwal', [Admin\JadwalController::class, 'index']);
    Route::get('/jadwal/baru', [Admin\JadwalController::class, 'create']);
    Route::post('/jadwal', [Admin\JadwalController::class, 'store']);
    Route::get('/jadwal/{showtime}/ubah', [Admin\JadwalController::class, 'edit']);
    Route::put('/jadwal/{showtime}', [Admin\JadwalController::class, 'update']);
    Route::delete('/jadwal/{showtime}', [Admin\JadwalController::class, 'destroy']);

    Route::get('/pesanan', [Admin\PesananController::class, 'index']);

    Route::get('/feedback', [\App\Http\Controllers\FeedbackController::class, 'indexAdmin']);
});
