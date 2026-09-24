<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class JadwalController extends Controller
{
    public function index()
    {
        $adaFilm = Movie::exists();
        $adaStudio = Studio::exists();

        return view('admin.jadwal.index', [
            'jadwal' => Showtime::with(['movie', 'studio', 'bookings'])
                ->orderByDesc('show_time')
                ->paginate(20)
                // Kursi yang sudah masuk pesanan jadwal ini. Satu pesanan bisa berisi beberapa kursi.
                ->through(fn ($j) => $j->setAttribute('kursi_terisi', $j->bookings->sum(fn ($b) => count($b->kursi)))),
            'adaFilm' => $adaFilm,
            'adaStudio' => $adaStudio,
            'bisaTambah' => $adaFilm && $adaStudio,
        ]);
    }

    public function create()
    {
        return view('admin.jadwal.form', [
            'jadwal' => new Showtime(),
            'film' => Movie::where('is_showing', true)->orderBy('title')->get(),
            'studio' => Studio::orderBy('name')->get(),
        ]);
    }

    // Satu film di satu studio, untuk beberapa hari dan sampai lima jam sekaligus. Jam yang
    // bertabrakan atau sudah lewat dilewati, sisanya tetap disimpan, lalu admin diberi tahu.
    public function store(Request $request)
    {
        $data = $request->validate([
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->where('is_showing', true)],
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

        $studio = Studio::find($data['studio_id']);
        $mulai = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai'] ?? $data['tanggal_mulai']);
        $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $dibuat = 0;
        $dilewati = [];

        for ($hari = $mulai->copy(); $hari->lte($selesai); $hari->addDay()) {
            foreach ($jam as $j) {
                $waktu = Carbon::parse($hari->format('Y-m-d') . ' ' . $j);
                $label = $namaHari[$waktu->dayOfWeek] . ' ' . $waktu->format('d/m H:i');

                if ($waktu->isPast()) {
                    $dilewati[] = $label . ' sudah lewat';
                    continue;
                }

                if ($bentrok = Showtime::bentrokDengan($studio->id, $data['movie_id'], $waktu)) {
                    $dilewati[] = $label . ' bentrok dengan "' . ($bentrok->movie?->title ?? 'film lain') . '" jam ' . $bentrok->show_time->format('H:i');
                    continue;
                }

                Showtime::create([
                    'movie_id' => $data['movie_id'],
                    'studio_id' => $studio->id,
                    'show_time' => $waktu,
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
    }

    public function edit(Showtime $showtime)
    {
        return view('admin.jadwal.form', [
            'jadwal' => $showtime,
            'film' => Movie::where('is_showing', true)->orWhere('id', $showtime->movie_id)->orderBy('title')->get(),
            'studio' => Studio::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Showtime $showtime)
    {
        // Jadwal yang sudah dipesan tidak boleh dipindah film, studio, atau jamnya. Kursi pesanan
        // menunjuk ke kursi studio ini, dan penonton sudah memegang tiket untuk jam ini.
        if ($showtime->bookings()->exists()) {
            return back()->with('gagal', 'Jadwal ini sudah punya pesanan, jadi tidak bisa diubah. '
                . 'Buat jadwal baru kalau perlu jam atau studio lain.');
        }

        $data = $request->validate([
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->where('is_showing', true)],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'show_time' => ['required', 'date', 'after:now'],
        ], [], [
            'movie_id' => 'film',
            'studio_id' => 'studio',
            'show_time' => 'waktu tayang',
        ]);

        if ($bentrok = $this->bentrok($data['studio_id'], $data['movie_id'], $data['show_time'], $showtime->id)) {
            return back()->withInput()->with('gagal', $bentrok);
        }

        $showtime->update($data);

        return redirect('/admin/jadwal')->with('sukses', 'Jadwal disimpan.');
    }

    public function destroy(Showtime $showtime)
    {
        // Menghapus jadwal ikut menghapus pesanannya (cascadeOnDelete), jadi jadwal
        // yang sudah dipesan dibiarkan. Tiket penonton harus tetap ada.
        if ($showtime->bookings()->exists()) {
            return redirect('/admin/jadwal')->with('gagal', 'Jadwal ini sudah punya pesanan, jadi tidak dihapus. '
                . 'Tiket penonton akan ikut hilang kalau jadwalnya dihapus.');
        }

        $showtime->delete();

        return redirect('/admin/jadwal')->with('sukses', 'Jadwal dihapus.');
    }

    // Satu studio tidak boleh memutar dua film yang waktunya bertabrakan, termasuk jeda bersih-bersih.
    private function bentrok(int $studioId, int $movieId, string $waktu, ?int $kecuali = null): ?string
    {
        $bentrok = Showtime::bentrokDengan($studioId, $movieId, Carbon::parse($waktu), $kecuali);

        if (! $bentrok) {
            return null;
        }

        return 'Studio itu masih dipakai "' . ($bentrok->movie?->title ?? 'film lain') . '" yang mulai jam '
            . $bentrok->show_time->format('H:i') . '. Pilih jam atau studio lain.';
    }
}
