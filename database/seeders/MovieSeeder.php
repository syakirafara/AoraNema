<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class MovieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apiKey = env('TMDB_API_KEY');

        //cek, apakah API Key TMDB sudah dipasang di .env atau belum
        if (!$apiKey) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di env');
            return;
        }

        $this->command->info('Mengambil daftar Master Genre dari TMDB...');

        //1. ambil data genre
        // sebelumnya &language=id-ID, sekarang di ubah ke &language=en-US (pada bagian genre)
        $genreResponse = Http::get("https://api.themoviedb.org/3/genre/movie/list?api_key={$apiKey}&language=en-US");
        
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
        $movieResponse = Http::get("https://api.themoviedb.org/3/movie/now_playing?api_key={$apiKey}&language=id-ID&page=1");

        if ($movieResponse->failed()) {
            $this->command->error('GAGAL: Tidak dapat terhubung ke API TMDB (Movies).');
            return;
        }

        $movies = $movieResponse->json('results');

        foreach ($movies as $movie) {
            // Proteksi 2: Ambil detail film satu per satu HANYA untuk mendapatkan durasi (runtime)
            $detailResponse = Http::get("https://api.themoviedb.org/3/movie/{$movie['id']}?api_key={$apiKey}&language=id-ID");
            $detail = $detailResponse->json();

            // Memasukkan film ke database lokal
            $movieModel = Movie::updateOrCreate(
                ['tmdb_id' => $movie['id']], // Cari berdasarkan tmdb_id
                [
                    'title' => $movie['title'],
                    'synopsis' => $movie['overview'] ?: null,
                    'poster_url' => $movie['poster_path'] ? "https://image.tmdb.org/t/p/w500{$movie['poster_path']}" : null,
                    'duration_minutes' => $detail['runtime'] ?? 120, // Jika TMDB tidak punya durasi, beri default 120 menit
                    'release_date' => $movie['release_date'] ?: null,
                    'is_showing' => true,
                ]
            );

            // 3. Merajut relasi Many-to-Many (Film <-> Genre)
            if (isset($movie['genre_ids'])) {
                $movieModel->genres()->sync($movie['genre_ids']);
            }
            
            $this->command->info("Tersimpan: {$movie['title']}");
        }

        $this->command->info('SELESAI: Data Film dan Genre berhasil ditarik dan disimpan!');
    }
}
