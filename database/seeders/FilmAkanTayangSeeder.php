<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

// Menambah film yang akan tayang dari daftar "Upcoming" TMDB, supaya bagian Akan Tayang di
// beranda dan saringan Segera Tayang di /film ada isinya. MovieSeeder hanya mengambil film yang
// sedang tayang, jadi tanggal rilisnya sudah lewat semua.
// Dipanggil DatabaseSeeder, dan bisa dijalankan sendiri: php artisan db:seed --class=FilmAkanTayangSeeder
// Film yang sudah ada di database tidak diubah, supaya isian admin seperti sinopsis tidak tertimpa.
class FilmAkanTayangSeeder extends Seeder
{
    private const JUMLAH = 8;

    public function run(): void
    {
        $kunci = config('services.tmdb.key');

        if (! $kunci) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di .env');

            return;
        }

        $daftar = collect();

        // Beberapa halaman diambil karena daftar Upcoming TMDB kadang berisi film yang tanggal
        // rilis Indonesianya sudah lewat. Yang disimpan hanya yang benar-benar belum rilis.
        for ($halaman = 1; $halaman <= 3 && $daftar->count() < self::JUMLAH; $halaman++) {
            $respons = Http::timeout(30)->get('https://api.themoviedb.org/3/movie/upcoming', [
                'api_key' => $kunci, 'language' => 'id-ID', 'region' => 'ID', 'page' => $halaman,
            ]);

            if ($respons->failed()) {
                $this->command->error('GAGAL: tidak dapat terhubung ke TMDB.');

                return;
            }

            $daftar = $daftar->concat(collect($respons->json('results'))
                ->filter(fn ($f) => ($f['release_date'] ?? '') > today()->format('Y-m-d') && $f['poster_path']));
        }

        $ditambah = 0;

        foreach ($daftar->unique('id')->take(self::JUMLAH) as $f) {
            if (Movie::where('tmdb_id', $f['id'])->exists()) {
                continue;
            }

            $detail = Http::timeout(30)->get("https://api.themoviedb.org/3/movie/{$f['id']}", ['api_key' => $kunci, 'language' => 'id-ID'])->json();

            $judul = $f['title'];
            $sinopsis = $f['overview'];

            // Sinopsis bahasa Indonesia sering belum ada di TMDB, dan film yang belum diterjemahkan
            // judulnya masih memakai huruf aslinya, misalnya huruf Thailand. Untuk film seperti itu
            // dipakai versi bahasa Inggrisnya.
            if (! $sinopsis || ! preg_match('/\p{Latin}/u', $judul)) {
                $inggris = Http::timeout(30)->get("https://api.themoviedb.org/3/movie/{$f['id']}", ['api_key' => $kunci, 'language' => 'en-US'])->json();
                $sinopsis = $sinopsis ?: ($inggris['overview'] ?? null);
                $judul = preg_match('/\p{Latin}/u', $judul) ? $judul : ($inggris['title'] ?? $judul);
            }

            $film = Movie::create([
                'tmdb_id' => $f['id'],
                'title' => $judul,
                'synopsis' => $sinopsis ?: null,
                'poster_url' => "https://image.tmdb.org/t/p/w500{$f['poster_path']}",
                'duration_minutes' => ($detail['runtime'] ?? 0) ?: null,
                'release_date' => $f['release_date'],
                'is_showing' => true,
            ]);

            $film->genres()->sync($f['genre_ids'] ?? []);
            $ditambah++;
            $this->command->info("Tersimpan: {$judul} (rilis {$f['release_date']})");
        }

        $this->command->info("SELESAI: {$ditambah} film akan tayang ditambahkan.");
    }
}
