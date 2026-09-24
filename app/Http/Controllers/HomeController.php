<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Movie;
use Illuminate\Support\Facades\Auth;
use App\Services\AoranemaMlService;

class HomeController extends Controller
{
    public function index()
    {
        // 1. Ambil film yang sedang tayang dan yang akan tayang beserta genrenya (mencegah N+1 Query).
        // Film yang sudah rilis tapi tidak punya jadwal tidak ditampilkan, karena tiketnya tidak bisa dipesan.
        $dbMovies = Movie::with(['genres', 'jadwalMendatang.studio'])
            ->where('is_showing', true)
            ->where(fn ($q) => $q->sedangTayang()->orWhere(fn ($q) => $q->segeraTayang()))
            ->get();

        // 2. Ubah/Map objek database menjadi format array statis yang dikenali oleh beranda.blade.php
        $semuaFilm = $dbMovies->map(function (Movie $movie) {
            // Isi kartu (judul, poster, durasi, tagline, pilihan pengelola, dan lainnya) diambil
            // dari Movie::kartu() supaya sama persis dengan kartu di halaman /film.
            return $movie->kartu() + [
                'sinopsis' => $movie->synopsis ?? 'Sinopsis belum tersedia.',

                // Film yang tanggal rilisnya belum tiba masuk bagian Akan Tayang.
                'mulai' => $movie->akanTayang() ? $movie->release_date : null,
                'id' => $movie->id,
            ];
        })->toArray();

        $rekomendasi = [];
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if ($user && $user->isUser()) {
            $mlService = new AoranemaMlService();

            // Film yang sudah pernah ia beli tiketnya tidak disarankan lagi.
            $sudahDitonton = Booking::where('user_id', $user->id)
                ->where('status', Booking::LUNAS)
                ->with('showtime')
                ->get()
                ->pluck('showtime.movie_id')
                ->unique();

            // candidates: film yang sedang tayang dan bisa dipesan sekarang
            $candidates = $dbMovies->filter(fn ($m) => ! $m->akanTayang() && $m->jadwalMendatang->isNotEmpty())
                ->whereNotIn('id', $sudahDitonton)
                ->values();
            // movieCatalog: semua film di DB untuk mencocokkan riwayat user
            $movieCatalog = Movie::with('genres')->get();

            $recommendedIds = $candidates->isEmpty() ? [] : $mlService->getRecommendationsForUser($user, $candidates, $movieCatalog);

            if (!empty($recommendedIds)) {
                $semuaFilmCollection = collect($semuaFilm);
                foreach ($recommendedIds as $id) {
                    $film = $semuaFilmCollection->firstWhere('id', $id);
                    if ($film) {
                        $rekomendasi[] = $film;
                    }
                }

                // Ambil 5 teratas saja
                $rekomendasi = array_slice($rekomendasi, 0, 5);
            }
        }

        // 3. Lempar variabel $semuaFilm ke view
        return view('beranda', compact('semuaFilm', 'rekomendasi'));
    }
}
