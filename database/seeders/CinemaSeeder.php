<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Membangun Studio dan menyusun Kursi...');

        // 1. Buat 2 Studio Fisik
        $studios = [
            // Nama studio dipakai langsung sebagai nama format di halaman detail film
            ['name' => 'Regular 2D', 'capacity' => 50],
            ['name' => 'IMAX', 'capacity' => 50],
        ];

        foreach ($studios as $studioData) {
            // 2. Kursi baris A sampai E, masing-masing 10 nomor (Total 50 kursi per studio)
            Studio::create([
                'name' => $studioData['name'],
                'capacity' => $studioData['capacity'],
                'baris' => 5,
                'kursi_per_baris' => 10,
            ]);
        }

        $this->command->info('Membuat Jadwal Tayang Film...');

        // Ambil 5 film pertama dari database (yang ditarik dari TMDB)
        $movies = Movie::where('is_showing', true)->take(5)->get();
        $semuaStudio = Studio::all();
        $hariIni = Carbon::today();

        // 3. Jodohkan Film dengan Studio dan Jam Tayang
        foreach ($movies as $movie) {
            foreach ($semuaStudio as $studio) {
                // Buat jadwal tayang Siang (Harga murah)
                Showtime::create([
                    'movie_id' => $movie->id,
                    'studio_id' => $studio->id,
                    'show_time' => $hariIni->copy()->setHour(13)->setMinute(0),
                    'price' => 35000,
                ]);
                
                // Buat jadwal tayang Malam (Harga mahal)
                Showtime::create([
                    'movie_id' => $movie->id,
                    'studio_id' => $studio->id,
                    'show_time' => $hariIni->copy()->setHour(19)->setMinute(30),
                    'price' => 50000,
                ]);
            }
        }
        
        $this->command->info('SELESAI: Studio, Kursi, dan Jadwal berhasil disiapkan!');
    }
}