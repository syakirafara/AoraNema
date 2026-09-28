<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    // Jumlah film yang sedang tayang. Bioskop biasanya memutar sekitar sepuluh judul sekaligus,
    // jadi tiap film kebagian beberapa jam tayang dalam sehari.
    private const JUMLAH = 10;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //cek, apakah API Key TMDB sudah dipasang di .env atau belum
        if (! config('services.tmdb.key')) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di .env, jadi data film tidak diambil.');
            return;
        }

        $this->command->info('Mengambil daftar Master Genre dari TMDB...');

        //1. ambil data genre dalam bahasa Inggris, karena model rekomendasi memakai nama genre bahasa Inggris
        $genreResponse = Tmdb::ambil('genre/movie/list', ['language' => 'en-US']);

        if (! $genreResponse) {
            $this->command->error('GAGAL: TMDB tidak bisa dihubungi. Periksa internet dan TMDB_API_KEY, lalu jalankan lagi: php artisan db:seed --class=MovieSeeder');
            return;
        }

        foreach ($genreResponse['genres'] as $tmdbGenre) {
            //updateOrCreate biar data tidak double misal seeder nya 2 kali
            Genre::updateOrCreate(
                ['id' => $tmdbGenre['id']],
                ['name' => $tmdbGenre['name']]
            );
        }

        $this->command->info('Mengambil daftar Film (Now Playing) dari TMDB...');

        // 2. Mengambil data Film yang sedang tayang. Daftar TMDB sudah urut dari film paling populer.
        $movieResponse = Tmdb::ambil('movie/now_playing', ['language' => 'id-ID', 'page' => 1]);

        if (! $movieResponse) {
            $this->command->error('GAGAL: daftar film tidak bisa diambil dari TMDB. Jalankan lagi nanti: php artisan db:seed --class=MovieSeeder');
            return;
        }

        foreach (array_slice($movieResponse['results'] ?? [], 0, self::JUMLAH) as $movie) {
            // Film dicari berdasarkan tmdb_id, supaya seeder yang dijalankan dua kali tidak membuat data ganda.
            // Film yang sudah ada dibiarkan: judul, sinopsis, tanggal rilis, dan genre yang mungkin sudah
            // diubah admin tidak tertimpa, dan jadwalnya tetap sah. Durasi atau batas usia yang masih
            // kosong dilengkapi LengkapiFilmSeeder.
            if (Movie::where('tmdb_id', $movie['id'])->exists()) {
                continue;
            }

            // Proteksi 2: Ambil detail film satu per satu untuk mendapatkan durasi (runtime)
            $detail = Tmdb::ambil("movie/{$movie['id']}", ['language' => 'id-ID']);

            if (! $detail) {
                $this->command->warn("Dilewati, detailnya gagal diambil: {$movie['title']}");
                continue;
            }

            $judul = $movie['title'];
            $sinopsis = $movie['overview'];

            // Sinopsis bahasa Indonesia sering belum ada di TMDB, dan film yang belum diterjemahkan
            // judulnya masih memakai huruf aslinya, misalnya huruf Korea. Untuk film seperti itu
            // dipakai versi bahasa Inggrisnya.
            if (! $sinopsis || ! preg_match('/\p{Latin}/u', $judul)) {
                $inggris = Tmdb::ambil("movie/{$movie['id']}", ['language' => 'en-US']) ?? [];
                $sinopsis = $sinopsis ?: ($inggris['overview'] ?? null);
                $judul = preg_match('/\p{Latin}/u', $judul) ? $judul : ($inggris['title'] ?? $judul);
            }

            $movieModel = Movie::create([
                'tmdb_id' => $movie['id'],
                'title' => $judul,
                'synopsis' => $sinopsis ?: null,
                'poster_url' => $movie['poster_path'] ? "https://image.tmdb.org/t/p/w500{$movie['poster_path']}" : null,
                // TMDB kadang tidak punya durasi (runtime 0). Film seperti itu dianggap dua jam.
                'duration_minutes' => ($detail['runtime'] ?? 0) ?: Movie::DURASI_BAWAAN,
                'release_date' => $movie['release_date'] ?: null,
                'is_showing' => true,
            ]);

            // 3. Merajut relasi Many-to-Many (Film <-> Genre)
            $movieModel->genres()->sync($movie['genre_ids'] ?? []);

            $this->command->info("Tersimpan: {$judul}");
        }

        $this->command->info('SELESAI: Data Film dan Genre berhasil ditarik dan disimpan!');
    }
}
