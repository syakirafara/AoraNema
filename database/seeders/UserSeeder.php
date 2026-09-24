<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat akun Admin
        User::create([
            'name' => 'Admin AoraNema',
            'email' => 'admin@aoranema.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // Buat akun Penonton. Genre favorit diisi supaya rekomendasi di beranda langsung
        // muncul, karena layanan ML butuh minimal satu genre favorit atau satu nilai film.
        User::create([
            'name' => 'Penonton Setia',
            'email' => 'user@aoranema.com',
            'password' => Hash::make('password123'),
            'favorite_genres' => ['Action', 'Adventure', 'Science Fiction'],
        ]);
    }
}
