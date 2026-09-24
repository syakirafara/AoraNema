<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\Request;

class FilmController extends Controller
{
    public function index(Request $request)
    {
        $saringan = in_array($request->query('status'), ['tayang', 'arsip']) ? $request->query('status') : 'semua';

        $film = Movie::with(['genres', 'showtimes.bookings' => fn ($q) => $q->where('status', 'paid')])
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
        $movie->update($this->aturan($request));
        $movie->genres()->sync($request->input('genre', []));

        return redirect('/admin/film')->with('sukses', 'Film "' . $movie->title . '" disimpan.');
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
}
