<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Akun dan studio dibuat lebih dulu karena tidak butuh internet. Kalau TMDB tidak bisa
            // dihubungi, akun contoh tetap ada dan aplikasinya tetap bisa dibuka.
            UserSeeder::class,
            CinemaSeeder::class,
            MovieSeeder::class,
            FilmAkanTayangSeeder::class,
            LengkapiFilmSeeder::class,
            JadwalSeeder::class,
        ]);
    }
}
