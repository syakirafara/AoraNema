<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

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
        $apiKey = config('services.tmdb.key');

        //cek, apakah API Key TMDB sudah dipasang di .env atau belum
        if (!$apiKey) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di .env');
            return;
        }

        $this->command->info('Mengambil daftar Master Genre dari TMDB...');

        //1. ambil data genre
        // sebelumnya &language=id-ID, sekarang di ubah ke &language=en-US (pada bagian genre)
        $genreResponse = Http::timeout(30)->get("https://api.themoviedb.org/3/genre/movie/list?api_key={$apiKey}&language=en-US");

        if ($genreResponse->failed()) {
            $this->command->error('GAGAL: Tidak dapat terhubung ke API TMDB');
            return;
        }

        foreach ($genreResponse->json('genres') as $tmdbGenre) {
            //updateOrCreate biar data tidak double misal seeder nya 2 kali
            Genre::updateOrCreate(
                ['id' => $tmdbGenre['id']],
                ['name' => $tmdbGenre['name']]
            );
        }

        $this->command->info('Mengambil daftar Film (Now Playing) dari TMDB...');

        // 2. Mengambil data Film yang sedang tayang
        $movieResponse = Http::timeout(30)->get("https://api.themoviedb.org/3/movie/now_playing?api_key={$apiKey}&language=id-ID&page=1");

        if ($movieResponse->failed()) {
            $this->command->error('GAGAL: Tidak dapat terhubung ke API TMDB (Movies).');
            return;
        }

        // Daftar TMDB sudah urut dari film paling populer.
        $movies = array_slice($movieResponse->json('results'), 0, self::JUMLAH);

        foreach ($movies as $movie) {
            // Proteksi 2: Ambil detail film satu per satu HANYA untuk mendapatkan durasi (runtime)
            $detail = Http::timeout(30)->get("https://api.themoviedb.org/3/movie/{$movie['id']}?api_key={$apiKey}&language=id-ID")->json();

            $judul = $movie['title'];
            $sinopsis = $movie['overview'];

            // Sinopsis bahasa Indonesia sering belum ada di TMDB, dan film yang belum diterjemahkan
            // judulnya masih memakai huruf aslinya, misalnya huruf Korea. Untuk film seperti itu
            // dipakai versi bahasa Inggrisnya.
            if (! $sinopsis || ! preg_match('/\p{Latin}/u', $judul)) {
                $inggris = Http::timeout(30)->get("https://api.themoviedb.org/3/movie/{$movie['id']}?api_key={$apiKey}&language=en-US")->json();
                $sinopsis = $sinopsis ?: ($inggris['overview'] ?? null);
                $judul = preg_match('/\p{Latin}/u', $judul) ? $judul : ($inggris['title'] ?? $judul);
            }

            // Film dicari berdasarkan tmdb_id, supaya seeder yang dijalankan dua kali tidak membuat data ganda.
            $movieModel = Movie::firstOrNew(['tmdb_id' => $movie['id']]);

            $movieModel->fill([
                'title' => $judul,
                'synopsis' => $sinopsis ?: null,
                'poster_url' => $movie['poster_path'] ? "https://image.tmdb.org/t/p/w500{$movie['poster_path']}" : null,
                'release_date' => $movie['release_date'] ?: null,
            ]);

            // Durasi dan status tayang hanya diisi untuk film baru. Film yang sudah ada mungkin sudah
            // punya jadwal, dan durasi yang berubah bisa membuat jadwalnya bertabrakan. Film yang
            // diarsipkan admin juga tidak ikut ditayangkan lagi.
            if (! $movieModel->exists) {
                // TMDB kadang tidak punya durasi (runtime 0). Film seperti itu dianggap dua jam.
                $movieModel->duration_minutes = ($detail['runtime'] ?? 0) ?: Movie::DURASI_BAWAAN;
                $movieModel->is_showing = true;
            }

            $movieModel->save();

            // 3. Merajut relasi Many-to-Many (Film <-> Genre)
            if (isset($movie['genre_ids'])) {
                $movieModel->genres()->sync($movie['genre_ids']);
            }

            $this->command->info("Tersimpan: {$judul}");
        }

        $this->command->info('SELESAI: Data Film dan Genre berhasil ditarik dan disimpan!');
    }
}
