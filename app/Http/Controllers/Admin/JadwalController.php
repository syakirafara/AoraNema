<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JadwalController extends Controller
{
    // Jadwal dilihat per hari, dikelompokkan per studio dan diurutkan dari jam paling pagi, seperti
    // papan jadwal di bioskop. Dari sini admin bisa melihat jam kosong di tiap studio.
    public function index(Request $request)
    {
        try {
            $tanggal = Carbon::createFromFormat('!Y-m-d', (string) $request->query('tanggal'));
        } catch (\Throwable) {
            $tanggal = today();
        }

        $adaFilm = Movie::where('is_showing', true)->exists();
        $studio = Studio::all()->sortBy('name', SORT_NATURAL)->values();

        // Pesanan yang belum batal ikut dimuat untuk menghitung kursi terisi.
        $jadwal = Showtime::with(['movie', 'studio', 'bookings' => fn ($q) => $q->where('status', '!=', Booking::BATAL)])
            ->whereBetween('show_time', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()])
            ->orderBy('show_time')
            ->get()
            ->groupBy('studio_id');

        return view('admin.jadwal.index', [
            'tanggal' => $tanggal,
            'studio' => $studio,
            'jadwal' => $jadwal,
            'jumlah' => $jadwal->flatten()->count(),
            'adaFilm' => $adaFilm,
            'adaStudio' => $studio->isNotEmpty(),
            'bisaTambah' => $adaFilm && $studio->isNotEmpty(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.jadwal.form', [
            'jadwal' => new Showtime(),
            'film' => Movie::where('is_showing', true)->orderBy('title')->get(),
            'studio' => Studio::all()->sortBy('name', SORT_NATURAL),
            // Tombol Tambah di halaman daftar membawa tanggal yang sedang dilihat. Tanggal yang sudah
            // lewat atau di luar hari penjualan diganti hari ini.
            'tanggalAwal' => $this->tanggalDijual($request->query('tanggal')),
        ]);
    }

    // Satu film di satu studio, untuk beberapa hari dan sampai lima jam sekaligus. Jam yang
    // bertabrakan, sudah lewat, atau sebelum tanggal rilis dilewati, sisanya tetap disimpan,
    // lalu admin diberi tahu.
    public function store(Request $request)
    {
        $data = $request->validate([
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->where('is_showing', true)],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . Showtime::tanggalTerakhir()->format('Y-m-d')],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai', 'before_or_equal:' . Showtime::tanggalTerakhir()->format('Y-m-d')],
            'jam' => ['required', 'array', 'max:5'],
            'jam.*' => ['nullable', 'date_format:H:i', 'after_or_equal:' . Showtime::JAM_BUKA, 'before_or_equal:' . Showtime::JAM_TERAKHIR],
        ], $this->pesan(), [
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

        $film = Movie::find($data['movie_id']);

        if (! $film->duration_minutes) {
            return back()->withInput()->with('gagal', $this->tanpaDurasi($film));
        }

        $mulai = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai'] ?? $data['tanggal_mulai']);
        $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $dibuat = 0;
        $dilewati = [];

        // Studionya dikunci selama pemeriksaan dan penyimpanan, supaya dua admin yang menyimpan
        // jadwal di studio yang sama pada saat bersamaan diproses bergantian dan tidak bertabrakan.
        DB::transaction(function () use ($data, $film, $jam, $mulai, $selesai, $namaHari, &$dibuat, &$dilewati) {
            $studio = Studio::whereKey($data['studio_id'])->lockForUpdate()->first();

            for ($hari = $mulai->copy(); $hari->lte($selesai); $hari->addDay()) {
                foreach ($jam as $j) {
                    $waktu = Carbon::parse($hari->format('Y-m-d') . ' ' . $j);
                    $label = $namaHari[$waktu->dayOfWeek] . ' ' . $waktu->format('d/m H:i');

                    if ($masalah = $this->masalah($studio->id, $film, $waktu)) {
                        $dilewati[] = $label . ' ' . $masalah;
                        continue;
                    }

                    Showtime::create([
                        'movie_id' => $film->id,
                        'studio_id' => $studio->id,
                        'show_time' => $waktu,
                    ]);
                    $dibuat++;
                }
            }
        });

        if ($dibuat === 0) {
            return back()->withInput()->with('gagal', 'Tidak ada jadwal yang disimpan: ' . implode('; ', $dilewati) . '.');
        }

        return redirect('/admin/jadwal?tanggal=' . $mulai->format('Y-m-d'))
            ->with('sukses', $dibuat . ' jadwal "' . $film->title . '" ditambahkan.')
            ->with('gagal', $dilewati ? count($dilewati) . ' jam dilewati: ' . implode('; ', $dilewati) . '.' : null);
    }

    public function edit(Showtime $showtime)
    {
        if ($showtime->sudahDipesan()) {
            return redirect('/admin/jadwal?tanggal=' . $showtime->show_time->format('Y-m-d'))->with('gagal', $this->terkunci());
        }

        return view('admin.jadwal.form', [
            'jadwal' => $showtime,
            'film' => Movie::where('is_showing', true)->orderBy('title')->get(),
            'studio' => Studio::all()->sortBy('name', SORT_NATURAL),
            'tanggalAwal' => $showtime->show_time->format('Y-m-d'),
        ]);
    }

    public function update(Request $request, Showtime $showtime)
    {
        // Jadwal yang sudah dipesan tidak boleh dipindah film, studio, atau jamnya. Kursi pesanan
        // menunjuk ke kursi studio ini, dan penonton sudah memegang tiket untuk jam ini.
        if ($showtime->sudahDipesan()) {
            return back()->with('gagal', $this->terkunci());
        }

        $data = $request->validate([
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->where('is_showing', true)],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'show_time' => ['required', 'date_format:Y-m-d\TH:i', 'after:now', 'before_or_equal:' . Showtime::tanggalTerakhir()->endOfDay()->format('Y-m-d\TH:i')],
        ], $this->pesan(), [
            'movie_id' => 'film',
            'studio_id' => 'studio',
            'show_time' => 'waktu tayang',
        ]);

        $film = Movie::find($data['movie_id']);
        $waktu = Carbon::parse($data['show_time']);

        if (! $film->duration_minutes) {
            return back()->withInput()->with('gagal', $this->tanpaDurasi($film));
        }

        $masalah = DB::transaction(function () use ($data, $film, $waktu, $showtime) {
            Studio::whereKey($data['studio_id'])->lockForUpdate()->first();

            if ($masalah = $this->masalah($data['studio_id'], $film, $waktu, $showtime->id)) {
                return $masalah;
            }

            $showtime->update(['movie_id' => $film->id, 'studio_id' => $data['studio_id'], 'show_time' => $waktu]);

            return null;
        });

        if ($masalah) {
            return back()->withInput()->with('gagal', 'Jadwal tidak disimpan: jam ' . $waktu->format('H:i') . ' ' . $masalah . '.');
        }

        return redirect('/admin/jadwal?tanggal=' . $waktu->format('Y-m-d'))->with('sukses', 'Jadwal disimpan.');
    }

    public function destroy(Showtime $showtime)
    {
        $kembali = '/admin/jadwal?tanggal=' . $showtime->show_time->format('Y-m-d');

        // Menghapus jadwal ikut menghapus pesanannya (cascadeOnDelete), jadi jadwal
        // yang sudah dipesan dibiarkan. Tiket penonton harus tetap ada.
        if ($showtime->sudahDipesan()) {
            return redirect($kembali)->with('gagal', $this->terkunci());
        }

        $showtime->delete();

        return redirect($kembali)->with('sukses', 'Jadwal dihapus.');
    }

    // Alasan satu jam tayang tidak bisa dipakai, atau null kalau boleh. Aturannya sama untuk
    // menambah dan mengubah jadwal.
    private function masalah(int $studioId, Movie $film, Carbon $waktu, ?int $kecuali = null): ?string
    {
        if ($waktu->isPast()) {
            return 'sudah lewat';
        }

        if (! Showtime::dalamJamBuka($waktu)) {
            return 'di luar jam operasional ' . Showtime::JAM_BUKA . '–' . Showtime::JAM_TERAKHIR;
        }

        if ($film->release_date && $waktu->format('Y-m-d') < $film->release_date) {
            return 'sebelum tanggal rilis film (' . Carbon::parse($film->release_date)->format('d/m/Y') . ')';
        }

        // Satu studio tidak boleh memutar dua film yang waktunya bertabrakan, termasuk iklan dan jeda bersih-bersih.
        if ($bentrok = Showtime::bentrokDengan($studioId, $film, $waktu, $kecuali)) {
            return 'bentrok dengan "' . ($bentrok->movie?->title ?? 'film lain') . '" yang memakai studio '
                . $bentrok->show_time->format('H:i') . '–' . $bentrok->studioSiap()->format('H:i');
        }

        return null;
    }

    private function pesan(): array
    {
        return [
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'show_time.after' => 'Waktu tayang harus setelah saat ini.',
            'jam.*.after_or_equal' => 'Jam tayang paling pagi pukul ' . Showtime::JAM_BUKA . '.',
            'jam.*.before_or_equal' => 'Jam tayang paling malam pukul ' . Showtime::JAM_TERAKHIR . '.',
            'tanggal_mulai.before_or_equal' => 'Jadwal hanya bisa dibuat sampai ' . Showtime::tanggalTerakhir()->format('d/m/Y') . ', sesuai hari penjualan tiket.',
            'tanggal_selesai.before_or_equal' => 'Jadwal hanya bisa dibuat sampai ' . Showtime::tanggalTerakhir()->format('d/m/Y') . ', sesuai hari penjualan tiket.',
            'show_time.before_or_equal' => 'Jadwal hanya bisa dibuat sampai ' . Showtime::tanggalTerakhir()->format('d/m/Y') . ', sesuai hari penjualan tiket.',
        ];
    }

    // Tanggal dari alamat halaman kalau berada di hari penjualan, selain itu hari ini.
    private function tanggalDijual(mixed $tanggal): string
    {
        $tanggal = is_string($tanggal) ? $tanggal : '';

        return $tanggal >= today()->format('Y-m-d') && $tanggal <= Showtime::tanggalTerakhir()->format('Y-m-d')
            ? $tanggal
            : today()->format('Y-m-d');
    }

    private function tanpaDurasi(Movie $film): string
    {
        return 'Film "' . $film->title . '" belum punya durasi. Isi durasinya dulu di halaman Film, '
            . 'supaya sistem tahu kapan studionya kosong lagi.';
    }

    private function terkunci(): string
    {
        return 'Jadwal ini sudah punya pesanan, jadi tidak bisa diubah atau dihapus. '
            . 'Penontonnya sudah memegang tiket untuk jam dan studio ini.';
    }
}
