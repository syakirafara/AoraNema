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
        // Akun dicari berdasarkan email, supaya seeder yang dijalankan dua kali tidak gagal.
        // Kata sandi contoh ini hanya untuk belajar. Ganti sebelum aplikasi dipasang di internet.

        // Buat akun Admin. role tidak bisa diisi lewat create() (lihat $fillable di model User),
        // jadi diisi terpisah dengan forceFill.
        User::updateOrCreate(['email' => 'admin@aoranema.com'], [
            'name' => 'Admin AoraNema',
            'password' => Hash::make('password123'),
        ])->forceFill(['role' => 'admin'])->save();

        // Buat akun Kasir untuk menjual tiket di loket.
        User::updateOrCreate(['email' => 'kasir@aoranema.com'], [
            'name' => 'Kasir Loket 1',
            'password' => Hash::make('password123'),
        ])->forceFill(['role' => 'cashier'])->save();

        // Buat akun Penonton. Genre favorit diisi supaya rekomendasi di beranda langsung
        // muncul, karena layanan ML butuh minimal satu genre favorit atau satu nilai film.
        User::updateOrCreate(['email' => 'user@aoranema.com'], [
            'name' => 'Penonton Setia',
            'password' => Hash::make('password123'),
            'favorite_genres' => ['Action', 'Adventure', 'Science Fiction'],
        ]);
    }
}
