<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FilmController extends Controller
{
    public function index(Request $request)
    {
        $saringan = in_array($request->query('status'), ['tayang', 'arsip']) ? $request->query('status') : 'semua';

        $film = Movie::with(['genres', 'showtimes.bookings' => fn ($q) => $q->where('status', Booking::LUNAS)])
            // Jadwal yang belum lewat. Ini yang menunjukkan film mana masih memakan slot studio.
            ->withCount(['showtimes as jadwal_mendatang' => fn ($q) => $q->where('show_time', '>=', now())])
            ->when($saringan === 'tayang', fn ($q) => $q->where('is_showing', true))
            ->when($saringan === 'arsip', fn ($q) => $q->where('is_showing', false))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            // Tiket terjual dihitung dari kursi di pesanan yang sudah dibayar, lewat jadwalnya.
            ->through(fn ($f) => $f->setAttribute('tiket_terjual', $f->showtimes->flatMap->bookings->sum(fn ($b) => count($b->kursi))));

        return view('admin.film.index', [
            'film' => $film,
            'saringan' => $saringan,
            'jumlahTayang' => Movie::where('is_showing', true)->count(),
            'jumlahArsip' => Movie::where('is_showing', false)->count(),
        ]);
    }

    public function create()
    {
        return view('admin.film.form', [
            'movie' => new Movie(),
            'genre' => Genre::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $movie = Movie::create($this->aturan($request));
        $movie->genres()->sync($request->input('genre', []));

        return redirect('/admin/film')->with('sukses', 'Film "' . $movie->title . '" ditambahkan.');
    }

    public function edit(Movie $movie)
    {
        return view('admin.film.form', [
            'movie' => $movie,
            'genre' => Genre::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Movie $movie)
    {
        $data = $this->aturan($request);
        $statusBerubah = (bool) $movie->is_showing !== $data['is_showing'];

        // Durasi dan tanggal rilis menentukan jadwal. Perubahannya disimpan dalam transaksi, lalu jadwal
        // mendatang film ini diperiksa ulang. Kalau ada yang jadi bertabrakan atau tayang sebelum
        // tanggal rilis, semua perubahan dibatalkan dan admin diberi tahu jadwal mana yang bermasalah.
        try {
            DB::transaction(function () use ($movie, $data, $request) {
                // Studio yang dipakai film ini dikunci, supaya admin lain tidak menambah jadwal di
                // studio itu selagi jadwal film ini diperiksa ulang.
                Studio::whereIn('id', $movie->showtimes()->select('studio_id'))->lockForUpdate()->get();

                $movie->update($data);
                $movie->genres()->sync($request->input('genre', []));

                if ($movie->wasChanged(['duration_minutes', 'release_date']) && ($masalah = $this->jadwalBermasalah($movie))) {
                    throw new \DomainException($masalah);
                }
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect('/admin/film')->with('sukses', $statusBerubah ? $this->pesanStatus($movie) : 'Film "' . $movie->title . '" disimpan.');
    }

    public function destroy(Movie $movie)
    {
        // Menghapus film ikut menghapus jadwal dan pesanannya, karena semua foreign key
        // memakai cascadeOnDelete. Film yang sudah punya jadwal sebaiknya diarsipkan, bukan dihapus.
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
    }

    // Mengarsipkan film, bukan menghapusnya. Riwayat penjualan dan jadwal lamanya tetap utuh,
    // film cuma berhenti ditawarkan ke pengunjung.
    public function arsip(Movie $movie)
    {
        $movie->update(['is_showing' => ! $movie->is_showing]);

        return redirect()->back()->with('sukses', $this->pesanStatus($movie));
    }

    // Keterangan untuk admin setelah film diarsipkan atau ditayangkan lagi. Mengarsipkan hanya
    // menyembunyikan film dari penonton dan menghentikan penjualan tiketnya. Jadwalnya tidak
    // dihapus, jadi film yang ditayangkan lagi langsung muncul bersama jadwalnya.
    private function pesanStatus(Movie $movie): string
    {
        $mendatang = $movie->showtimes()->where('show_time', '>=', now())->count();

        if ($movie->is_showing) {
            return 'Film "' . $movie->title . '" ditayangkan lagi.' . match (true) {
                $mendatang > 0 => '',
                $movie->akanTayang() => ' Film ini tampil di bagian Akan Tayang. Jadwalnya bisa ditambahkan mulai tanggal rilisnya.',
                default => ' Film ini belum punya jadwal mendatang, jadi belum muncul di halaman penonton. Tambahkan jadwalnya di halaman Jadwal Tayang.',
            };
        }

        return 'Film "' . $movie->title . '" diarsipkan: tidak tampil di halaman penonton dan tiketnya berhenti dijual.'
            . ($mendatang ? ' ' . $mendatang . ' jadwal mendatangnya tetap disimpan. Tiket yang sudah terjual tetap berlaku. '
                . 'Hapus jadwal yang belum dipesan di halaman Jadwal Tayang kalau studionya mau dipakai film lain.' : '');
    }

    // Pesan kalau ada jadwal film ini yang bertabrakan dengan jadwal lain di studionya, atau yang
    // tayang sebelum tanggal rilis. Null kalau semuanya masih sah. Yang diperiksa adalah jadwal yang
    // belum selesai memakai studio menurut durasi baru, termasuk tayangan yang sedang berjalan.
    private function jadwalBermasalah(Movie $movie): ?string
    {
        $masalah = [];
        $lamaPakai = Showtime::IKLAN_MENIT + $movie->durasi() + Showtime::JEDA_MENIT;

        foreach ($movie->showtimes()->with('studio')->where('show_time', '>', now()->subMinutes($lamaPakai))->orderBy('show_time')->get() as $j) {
            $label = $j->show_time->format('d/m H:i') . ' di ' . $j->studio->name;

            if ($movie->release_date && $j->show_time->format('Y-m-d') < $movie->release_date) {
                $masalah[] = $label . ' jadi lebih awal dari tanggal rilis ' . Carbon::parse($movie->release_date)->format('d/m/Y');
            } elseif ($bentrok = Showtime::bentrokDengan($j->studio_id, $movie, $j->show_time, $j->id)) {
                $masalah[] = $label . ' jadi bertabrakan dengan "' . $bentrok->movie->title . '" jam ' . $bentrok->show_time->format('H:i');
            }
        }

        return $masalah
            ? 'Perubahan tidak disimpan karena jadwal film ini jadi bermasalah: ' . implode('; ', $masalah)
                . '. Pindahkan atau hapus jadwal itu dulu di halaman Jadwal Tayang.'
            : null;
    }

    // Aturan isian formulir film, dipakai saat menambah maupun mengubah.
    private function aturan(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'usia' => ['nullable', 'in:SU,13+,17+,21+'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'poster_url' => ['nullable', 'string', 'max:255'],
            // Durasi wajib diisi, karena dipakai untuk menghitung kapan studio kosong lagi.
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:300'],
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
}
