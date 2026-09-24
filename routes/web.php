<?php

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

// ---------------------------------------------------------------------------
// Halaman admin, hanya untuk akun dengan role admin.
// Ditulis sebagai closure, bukan file controller, supaya tidak menabrak
// controller yang dulu dikerjakan di branch adam/controller.
// ---------------------------------------------------------------------------

Route::prefix('admin')->middleware(['auth', IsAdmin::class])->group(function () {

    Route::get('/', function () {
        return redirect('/admin/film');
    });

    // ----- Film ------------------------------------------------------------

    Route::get('/film', function () {
        $saringan = in_array(request('status'), ['tayang', 'arsip']) ? request('status') : 'semua';

        $film = \App\Models\Movie::with('genres')
            // Jadwal yang belum lewat. Ini yang menunjukkan film mana masih memakan slot studio.
            ->withCount(['showtimes as jadwal_mendatang' => fn ($q) => $q->where('show_time', '>=', now())])
            // Tiket terjual dihitung dari kursi di pesanan yang sudah dibayar, lewat jadwalnya.
            ->addSelect(['tiket_terjual' => \App\Models\Booking::query()
                ->selectRaw('count(*)')
                ->join('booking_seat', 'booking_seat.booking_id', '=', 'bookings.id')
                ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
                ->whereColumn('showtimes.movie_id', 'movies.id')
                ->where('bookings.status', 'paid'),
            ])
            ->when($saringan === 'tayang', fn ($q) => $q->where('is_showing', true))
            ->when($saringan === 'arsip', fn ($q) => $q->where('is_showing', false))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.film.index', [
            'film' => $film,
            'saringan' => $saringan,
            'jumlahTayang' => \App\Models\Movie::where('is_showing', true)->count(),
            'jumlahArsip' => \App\Models\Movie::where('is_showing', false)->count(),
        ]);
    });

    // Mengarsipkan film, bukan menghapusnya. Riwayat penjualan dan jadwal lamanya tetap utuh,
    // film cuma berhenti ditawarkan ke pengunjung.
    Route::post('/film/{movie}/arsip', function (\App\Models\Movie $movie) {
        $movie->update(['is_showing' => ! $movie->is_showing]);

        if ($movie->is_showing) {
            return redirect()->back()->with('sukses', 'Film "' . $movie->title . '" ditayangkan lagi.');
        }

        $mendatang = $movie->showtimes()->where('show_time', '>=', now())->count();

        return redirect()->back()->with(
            'sukses',
            'Film "' . $movie->title . '" diarsipkan.'
                . ($mendatang
                    ? ' Masih ada ' . $mendatang . ' jadwal mendatang yang perlu kamu hapus sendiri di halaman Jadwal Tayang.'
                    : '')
        );
    });

    Route::get('/film/baru', function () {
        $movie = new \App\Models\Movie();
        $genre = \App\Models\Genre::orderBy('name')->get();

        return view('admin.film.form', compact('movie', 'genre'));
    });

    Route::get('/film/{movie}/ubah', function (\App\Models\Movie $movie) {
        $genre = \App\Models\Genre::orderBy('name')->get();

        return view('admin.film.form', compact('movie', 'genre'));
    });

    Route::post('/film', function (\Illuminate\Http\Request $request) {
        $movie = \App\Models\Movie::create(aturanFilm($request));
        $movie->genres()->sync($request->input('genre', []));

        return redirect('/admin/film')->with('sukses', 'Film "' . $movie->title . '" ditambahkan.');
    });

    Route::put('/film/{movie}', function (\Illuminate\Http\Request $request, \App\Models\Movie $movie) {
        $movie->update(aturanFilm($request));
        $movie->genres()->sync($request->input('genre', []));

        return redirect('/admin/film')->with('sukses', 'Film "' . $movie->title . '" disimpan.');
    });

    Route::delete('/film/{movie}', function (\App\Models\Movie $movie) {
        // Menghapus film ikut menghapus jadwal, pesanan, dan pembayarannya, karena
        // semua foreign key memakai cascadeOnDelete. Film yang sudah punya jadwal
        // sebaiknya dimatikan statusnya, bukan dihapus.
        if ($movie->showtimes()->exists()) {
            return redirect('/admin/film')->with(
                'gagal',
                'Film "' . $movie->title . '" punya jadwal tayang, jadi tidak dihapus. '
                    . 'Hilangkan centang "Sedang tayang" kalau mau menariknya dari peredaran.'
            );
        }

        $judul = $movie->title;
        $movie->delete();

        return redirect('/admin/film')->with('sukses', 'Film "' . $judul . '" dihapus.');
    });

    // ----- Studio ----------------------------------------------------------

    Route::get('/studio', function () {
        $studio = \App\Models\Studio::withCount(['seats', 'showtimes'])->orderBy('name')->get();

        return view('admin.studio.index', compact('studio'));
    });

    Route::get('/studio/baru', function () {
        return view('admin.studio.form', [
            'studio' => new \App\Models\Studio(),
            'baris' => 8,
            'perBaris' => 10,
            'terkunci' => false,
        ]);
    });

    Route::get('/studio/{studio}/ubah', function (\App\Models\Studio $studio) {
        // Baris dan kursi per baris tidak disimpan sebagai kolom, jadi dibaca balik
        // dari kursi yang ada: berapa huruf berbeda, dan angka terbesarnya.
        $nomor = $studio->seats()->pluck('seat_number');

        return view('admin.studio.form', [
            'studio' => $studio,
            'baris' => $nomor->map(fn ($k) => substr($k, 0, 1))->unique()->count() ?: 8,
            'perBaris' => $nomor->map(fn ($k) => (int) substr($k, 1))->max() ?: 10,
            'terkunci' => studioTerkunci($studio),
        ]);
    });

    Route::post('/studio', function (\Illuminate\Http\Request $request) {
        $data = aturanStudio($request);

        $studio = \App\Models\Studio::create([
            'name' => $data['name'],
            'format' => $data['format'],
            'capacity' => $data['baris'] * $data['per_baris'],
            'harga_biasa' => $data['harga_biasa'],
            'harga_akhir_pekan' => $data['harga_akhir_pekan'],
        ]);

        susunKursi($studio, $data['baris'], $data['per_baris']);

        return redirect('/admin/studio')->with(
            'sukses',
            'Studio "' . $studio->name . '" dibuat dengan ' . $studio->capacity . ' kursi.'
        );
    });

    Route::put('/studio/{studio}', function (\Illuminate\Http\Request $request, \App\Models\Studio $studio) {
        $terkunci = studioTerkunci($studio);
        $data = aturanStudio($request, $terkunci);

        $studio->update([
            'name' => $data['name'],
            'format' => $data['format'],
            'harga_biasa' => $data['harga_biasa'],
            'harga_akhir_pekan' => $data['harga_akhir_pekan'],
        ]);

        // Jadwal yang belum lewat ikut memakai tarif baru.
        $studio->sesuaikanHargaJadwal();

        if (! $terkunci) {
            $studio->update(['capacity' => $data['baris'] * $data['per_baris']]);
            susunKursi($studio, $data['baris'], $data['per_baris']);
        }

        return redirect('/admin/studio')->with(
            'sukses',
            'Studio "' . $studio->name . '" disimpan.'
                . ($terkunci ? ' Susunan kursinya dibiarkan karena sudah ada pesanan.' : '')
        );
    });

    Route::delete('/studio/{studio}', function (\App\Models\Studio $studio) {
        if ($studio->showtimes()->exists()) {
            return redirect('/admin/studio')->with(
                'gagal',
                'Studio "' . $studio->name . '" masih dipakai jadwal tayang, jadi tidak dihapus. '
                    . 'Hapus jadwalnya dulu.'
            );
        }

        $nama = $studio->name;
        $studio->delete();

        return redirect('/admin/studio')->with('sukses', 'Studio "' . $nama . '" dihapus.');
    });

    // ----- Jadwal tayang ---------------------------------------------------

    Route::get('/jadwal', function () {
        $adaFilm = \App\Models\Movie::exists();
        $adaStudio = \App\Models\Studio::exists();

        return view('admin.jadwal.index', [
            'jadwal' => \App\Models\Showtime::with(['movie', 'studio'])
                // Kursi yang sudah masuk pesanan jadwal ini. Satu pesanan bisa berisi beberapa kursi.
                ->addSelect(['kursi_terisi' => \App\Models\Booking::query()
                    ->selectRaw('count(*)')
                    ->join('booking_seat', 'booking_seat.booking_id', '=', 'bookings.id')
                    ->whereColumn('bookings.showtime_id', 'showtimes.id'),
                ])
                ->orderByDesc('show_time')
                ->paginate(20),
            'adaFilm' => $adaFilm,
            'adaStudio' => $adaStudio,
            'bisaTambah' => $adaFilm && $adaStudio,
        ]);
    });

    Route::get('/jadwal/baru', function () {
        return view('admin.jadwal.form', [
            'jadwal' => new \App\Models\Showtime(),
            'film' => \App\Models\Movie::where('is_showing', true)->orderBy('title')->get(),
            'studio' => \App\Models\Studio::orderBy('name')->get(),
        ]);
    });

    Route::get('/jadwal/{showtime}/ubah', function (\App\Models\Showtime $showtime) {
        return view('admin.jadwal.form', [
            'jadwal' => $showtime,
            'film' => \App\Models\Movie::where('is_showing', true)->orWhere('id', $showtime->movie_id)->orderBy('title')->get(),
            'studio' => \App\Models\Studio::orderBy('name')->get(),
        ]);
    });

    // Satu film di satu studio, untuk beberapa hari dan sampai lima jam sekaligus. Jam yang
    // bertabrakan atau sudah lewat dilewati, sisanya tetap disimpan, lalu admin diberi tahu.
    Route::post('/jadwal', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'movie_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('movies', 'id')->where('is_showing', true)],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai', 'before_or_equal:' . now()->addDays(30)->format('Y-m-d')],
            'jam' => ['required', 'array', 'max:5'],
            'jam.*' => ['nullable', 'date_format:H:i'],
        ], [], [
            'movie_id' => 'film',
            'studio_id' => 'studio',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'jam.*' => 'jam tayang',
        ]);

        $jam = collect($data['jam'])->filter()->unique()->sort()->values();

        if ($jam->isEmpty()) {
            return back()->withInput()->withErrors(['jam' => 'Isi minimal satu jam tayang.']);
        }

        $studio = \App\Models\Studio::find($data['studio_id']);
        $mulai = \Illuminate\Support\Carbon::parse($data['tanggal_mulai']);
        $selesai = \Illuminate\Support\Carbon::parse($data['tanggal_selesai'] ?? $data['tanggal_mulai']);
        $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $dibuat = 0;
        $dilewati = [];

        for ($hari = $mulai->copy(); $hari->lte($selesai); $hari->addDay()) {
            foreach ($jam as $j) {
                $waktu = \Illuminate\Support\Carbon::parse($hari->format('Y-m-d') . ' ' . $j);
                $label = $namaHari[$waktu->dayOfWeek] . ' ' . $waktu->format('d/m H:i');

                if ($waktu->isPast()) {
                    $dilewati[] = $label . ' sudah lewat';
                    continue;
                }

                if ($bentrok = \App\Models\Showtime::bentrokDengan($studio->id, $data['movie_id'], $waktu)) {
                    $dilewati[] = $label . ' bentrok dengan "' . ($bentrok->movie?->title ?? 'film lain') . '" jam ' . $bentrok->show_time->format('H:i');
                    continue;
                }

                \App\Models\Showtime::create([
                    'movie_id' => $data['movie_id'],
                    'studio_id' => $studio->id,
                    'show_time' => $waktu,
                    'price' => $studio->hargaUntuk($waktu),
                ]);
                $dibuat++;
            }
        }

        if ($dibuat === 0) {
            return back()->withInput()->with('gagal', 'Tidak ada jadwal yang disimpan: ' . implode('; ', $dilewati) . '.');
        }

        return redirect('/admin/jadwal')
            ->with('sukses', $dibuat . ' jadwal ditambahkan.')
            ->with('gagal', $dilewati ? count($dilewati) . ' jam dilewati: ' . implode('; ', $dilewati) . '.' : null);
    });

    Route::put('/jadwal/{showtime}', function (\Illuminate\Http\Request $request, \App\Models\Showtime $showtime) {
        // Jadwal yang sudah dipesan tidak boleh dipindah film, studio, atau jamnya. Kursi pesanan
        // menunjuk ke kursi studio ini, dan penonton sudah memegang tiket untuk jam ini.
        if ($showtime->bookings()->exists()) {
            return back()->with('gagal', 'Jadwal ini sudah punya pesanan, jadi tidak bisa diubah. '
                . 'Buat jadwal baru kalau perlu jam atau studio lain.');
        }

        $data = aturanJadwal($request);

        if ($bentrok = jadwalBentrok($data['studio_id'], $data['movie_id'], $data['show_time'], $showtime->id)) {
            return back()->withInput()->with('gagal', $bentrok);
        }

        $showtime->update($data);

        return redirect('/admin/jadwal')->with('sukses', 'Jadwal disimpan.');
    });

    Route::delete('/jadwal/{showtime}', function (\App\Models\Showtime $showtime) {
        // Menghapus jadwal ikut menghapus pesanan dan pembayarannya (cascadeOnDelete),
        // jadi jadwal yang sudah dipesan dibiarkan. Tiket penonton harus tetap ada.
        if ($showtime->bookings()->exists()) {
            return redirect('/admin/jadwal')->with('gagal', 'Jadwal ini sudah punya pesanan, jadi tidak dihapus. '
                . 'Tiket penonton akan ikut hilang kalau jadwalnya dihapus.');
        }

        $showtime->delete();

        return redirect('/admin/jadwal')->with('sukses', 'Jadwal dihapus.');
    });

    // ----- Pesanan ---------------------------------------------------------

    Route::get('/pesanan', function () {
        return view('admin.pesanan.index', [
            'pesanan' => \App\Models\Booking::with(['user', 'seats', 'showtime.movie', 'showtime.studio'])
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    });

    // ----- Feedback --------------------------------------------------------

    Route::get('/feedback', [\App\Http\Controllers\FeedbackController::class, 'indexAdmin']);
});

// ---------------------------------------------------------------------------
// Penolong untuk halaman admin
// ---------------------------------------------------------------------------

// Aturan isian formulir film, dipakai saat menambah maupun mengubah.
function aturanFilm(\Illuminate\Http\Request $request): array
{
    $data = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'tagline' => ['nullable', 'string', 'max:255'],
        'usia' => ['nullable', 'in:SU,13+,17+,21+'],
        'synopsis' => ['nullable', 'string', 'max:5000'],
        'poster_url' => ['nullable', 'string', 'max:255'],
        'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        'release_date' => ['nullable', 'date'],
        'genre' => ['nullable', 'array'],
        'genre.*' => ['integer', 'exists:genres,id'],
    ], [], [
        'title' => 'judul',
        'usia' => 'batas usia',
        'synopsis' => 'sinopsis',
        'poster_url' => 'alamat poster',
        'duration_minutes' => 'durasi',
        'release_date' => 'tanggal rilis',
    ]);

    unset($data['genre']);

    // Kotak centang tidak terkirim sama sekali kalau tidak dicentang,
    // jadi nilainya diambil terpisah, bukan lewat validate.
    $data['is_showing'] = $request->boolean('is_showing');
    $data['pilihan'] = $request->boolean('pilihan');

    return $data;
}

// Saat studio terkunci, ukuran ruangnya tidak ikut diperiksa karena isiannya
// dimatikan di halaman sehingga tidak terkirim.
function aturanStudio(\Illuminate\Http\Request $request, bool $terkunci = false): array
{
    $aturan = [
        'name' => ['required', 'string', 'max:255'],
        'format' => ['required', \Illuminate\Validation\Rule::in(\App\Models\Studio::FORMAT)],
        'harga_biasa' => ['required', 'integer', 'min:0', 'max:1000000'],
        'harga_akhir_pekan' => ['required', 'integer', 'min:0', 'max:1000000'],
    ];

    if (! $terkunci) {
        $aturan['baris'] = ['required', 'integer', 'min:1', 'max:26'];
        $aturan['per_baris'] = ['required', 'integer', 'min:1', 'max:30'];
    }

    return $request->validate($aturan, [], [
        'name' => 'nama studio',
        'harga_biasa' => 'harga hari biasa',
        'harga_akhir_pekan' => 'harga akhir pekan',
        'baris' => 'jumlah baris',
        'per_baris' => 'kursi per baris',
    ]);
}

function aturanJadwal(\Illuminate\Http\Request $request): array
{
    $data = $request->validate([
        'movie_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('movies', 'id')->where('is_showing', true)],
        'studio_id' => ['required', 'integer', 'exists:studios,id'],
        'show_time' => ['required', 'date', 'after:now'],
    ], [], [
        'movie_id' => 'film',
        'studio_id' => 'studio',
        'show_time' => 'waktu tayang',
    ]);

    // Harga tidak diisi admin per jadwal. Diambil dari tarif studio sesuai harinya,
    // jadi semua jam di hari dan studio yang sama pasti berharga sama.
    $data['price'] = \App\Models\Studio::find($data['studio_id'])
        ->hargaUntuk(\Illuminate\Support\Carbon::parse($data['show_time']));

    return $data;
}

// Menyusun ulang kursi sebuah studio, dipanggil saat studio dibuat atau diubah.
function susunKursi(\App\Models\Studio $studio, int $baris, int $perBaris): void
{
    $studio->seats()->delete();

    $kursi = [];
    $sekarang = now();

    for ($b = 0; $b < $baris; $b++) {
        for ($n = 1; $n <= $perBaris; $n++) {
            $kursi[] = [
                'studio_id' => $studio->id,
                'seat_number' => chr(65 + $b) . $n,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }
    }

    \App\Models\Seat::insert($kursi);
}

// Studio yang kursinya sudah dipesan tidak boleh diubah susunannya, karena
// menghapus kursi ikut menghapusnya dari pesanan yang memakainya.
function studioTerkunci(\App\Models\Studio $studio): bool
{
    return $studio->exists
        && \App\Models\Booking::whereHas('seats', fn ($q) => $q->where('studio_id', $studio->id))->exists();
}

// Satu studio tidak boleh memutar dua film yang waktunya bertabrakan, termasuk jeda bersih-bersih.
function jadwalBentrok(int $studioId, int $movieId, string $waktu, ?int $kecuali = null): ?string
{
    $bentrok = \App\Models\Showtime::bentrokDengan($studioId, $movieId, \Illuminate\Support\Carbon::parse($waktu), $kecuali);

    if (! $bentrok) {
        return null;
    }

    return 'Studio itu masih dipakai "' . ($bentrok->movie?->title ?? 'film lain') . '" yang mulai jam '
        . $bentrok->show_time->format('H:i') . '. Pilih jam atau studio lain.';
}
