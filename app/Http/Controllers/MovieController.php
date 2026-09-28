<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $cari = is_string($request->query('cari')) ? trim($request->query('cari')) : '';
        $genre = $request->query('genre');
        $status = in_array($request->query('status'), ['tayang', 'segera']) ? $request->query('status') : 'semua';
        $urut = in_array($request->query('urut'), ['az', 'za']) ? $request->query('urut') : 'terbaru';
        $tampilan = $request->query('tampilan') === 'baris' ? 'baris' : 'kotak';

        $film = Movie::with(['genres', 'jadwalMendatang.studio'])
            // Film yang diarsipkan admin (is_showing = false) tidak ditawarkan ke penonton.
            ->where('is_showing', true)
            // Sedang tayang berarti sudah rilis dan punya jadwal yang bisa dipesan. Segera tayang berarti
            // tanggal rilisnya belum tiba. Film yang sudah rilis tapi tidak punya jadwal tidak ditampilkan.
            ->when($status === 'tayang', fn ($query) => $query->sedangTayang())
            ->when($status === 'segera', fn ($query) => $query->segeraTayang())
            ->when($status === 'semua', fn ($query) => $query->where(fn ($q) => $q->sedangTayang()->orWhere(fn ($q) => $q->segeraTayang())))
            ->when($genre, function ($query, $genre) {
                $query->whereHas('genres', function ($q) use ($genre) {
                    $q->where('name', $genre);
                });
            })
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where('title', 'like', '%' . $cari . '%');
            })
            ->get()
            ->map(fn (Movie $movie) => $movie->kartu());

        // "Terbaru" menaruh film yang sedang tayang di atas, rilis paling baru dulu, lalu film yang
        // akan tayang, tanggal paling dekat dulu. Tanpa pemisahan ini, film yang belum tayang
        // selalu naik ke puncak karena tanggal rilisnya di masa depan.
        $film = match ($urut) {
            'az' => $film->sortBy('judul', SORT_NATURAL | SORT_FLAG_CASE),
            'za' => $film->sortByDesc('judul', SORT_NATURAL | SORT_FLAG_CASE),
            default => $film->where('tayang', true)->sortByDesc('rilis')
                ->concat($film->where('tayang', false)->sortBy('rilis')),
        };

        $film = $film->values()->all();

        return view('daftar-film', compact('film', 'cari', 'status', 'urut', 'tampilan'));
    }

    public function show(Request $request, string $slug, MidtransService $midtrans)
    {
        // 1. Ambil id film dari URL (misal: 'coyote-vs-acme-3' -> kita ambil angka 3), lalu
        // cari filmnya. findOrFail() otomatis menampilkan halaman 404 kalau id itu tidak ada.
        $movie = Movie::with('genres')->findOrFail(\Illuminate\Support\Str::afterLast($slug, '-'));

        // Film yang diarsipkan admin sudah tidak ditawarkan, jadi halamannya tidak dibuka lagi.
        abort_unless($movie->is_showing, 404);

        // 3. Format data persis seperti yang diharapkan oleh film.blade.php
        $film = $movie->kartu() + [
            'slug' => $slug,
            'sinopsis' => $movie->synopsis ?? 'Sinopsis belum tersedia.',
            // Diisi di bawah, setelah jadwalnya diketahui.
            'mulai' => null,
        ];

        // 4. Tanggal dibatasi ke hari-hari yang dijual, supaya parameter di URL tidak bisa dipakai
        // meminta jadwal sembarang tanggal.
        $hariIni = today();
        $akhir = Showtime::tanggalTerakhir()->endOfDay();
        $tanggal = null;

        for ($i = 0; $i < Showtime::HARI_DIJUAL; $i++) {
            if ($hariIni->copy()->addDays($i)->format('Y-m-d') === $request->query('tanggal')) {
                $tanggal = $hariIni->copy()->addDays($i);
            }
        }

        // Semua jam tayang di hari-hari itu, termasuk yang hari ini sudah lewat.
        $semuaJam = $movie->showtimes()
            ->whereBetween('show_time', [$hariIni, $akhir])
            ->pluck('show_time');

        // Tanggal yang punya jadwal sama sekali, dan tanggal yang masih punya jam yang bisa dipesan.
        $tanggalAda = $semuaJam->map(fn ($w) => $w->format('Y-m-d'))->unique()->sort()->values()->all();
        $tanggalBerjadwal = $semuaJam->filter(fn ($w) => $w->isFuture())
            ->map(fn ($w) => $w->format('Y-m-d'))->unique()->sort()->values()->all();

        // Tanpa pilihan tanggal, halaman membuka hari ini selama hari ini punya jadwal, walaupun
        // jamnya sudah lewat semua. Kalau hari ini tidak ada jadwal sama sekali, dibuka tanggal
        // terdekat yang ada jadwalnya.
        $tanggal ??= in_array($hariIni->format('Y-m-d'), $tanggalAda) || ! $tanggalBerjadwal
            ? $hariIni->copy()
            : Carbon::parse($tanggalBerjadwal[0]);

        // Pesanan yang ditinggal tanpa dibayar dilepas dulu, supaya sisa kursi dan tanda Penuh benar.
        $midtrans->lepasKedaluwarsa();

        // 5. Jadwal tayang dari tabel showtimes pada tanggal itu, dikelompokkan per format layar.
        // Beberapa studio bisa berformat sama; jam dari studio-studio itu digabung dalam satu baris.
        // Jam yang sudah lewat atau kursinya habis tetap ditampilkan, tapi tidak bisa dipilih.
        // Pesanan yang belum batal ikut dimuat untuk menghitung sisa kursi.
        $jadwal = $movie->showtimes()
            ->with(['studio', 'bookings' => fn ($q) => $q->where('status', '!=', Booking::BATAL)])
            ->whereBetween('show_time', [$tanggal->copy(), $tanggal->copy()->endOfDay()])
            ->orderBy('show_time')
            ->get()
            ->groupBy(fn ($s) => $s->studio->format)
            // Urutan format tetap: Regular 2D, Regular 3D, IMAX. Tanpa ini urutannya ikut
            // jam tayang pertama dan berpindah-pindah tiap hari.
            ->sortBy(fn ($jam, $format) => array_search($format, Studio::FORMAT));

        // Film yang belum rilis dan belum punya jadwal menampilkan tanggal rilisnya, bukan jadwal.
        // Kalau admin sudah membuka jadwal lebih dulu (pra-penjualan), jadwalnya tetap ditampilkan.
        if ($movie->akanTayang() && ! $tanggalBerjadwal) {
            $film['mulai'] = $movie->release_date;
        }

        return view('film', [
            'film' => $film,
            'tanggal' => $tanggal,
            'jadwal' => $jadwal,
            'tanggalBerjadwal' => $tanggalBerjadwal,
            'daftarTanggal' => collect(range(0, Showtime::HARI_DIJUAL - 1))->map(fn ($i) => $hariIni->copy()->addDays($i)),
        ]);
    }
}