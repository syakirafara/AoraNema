<?php

namespace Database\Seeders;

use App\Models\Studio;
use Illuminate\Database\Seeder;

// Studio bioskop contoh. Jadwal tayangnya dibuat JadwalSeeder.
class CinemaSeeder extends Seeder
{
    // Enam studio reguler 2D, satu studio 3D, dan satu IMAX yang lebih besar, seperti bioskop
    // ukuran sedang. Tarif mengikuti kisaran harga bioskop di Indonesia; akhir pekan lebih mahal.
    // Urutan isian: nama, format, jumlah baris, kursi per baris, tarif hari biasa, tarif akhir pekan.
    private const STUDIO = [
        ['Studio 1', 'Regular 2D', 8, 10, 45000, 55000],
        ['Studio 2', 'Regular 2D', 8, 10, 45000, 55000],
        ['Studio 3', 'Regular 2D', 8, 10, 45000, 55000],
        ['Studio 4', 'Regular 2D', 8, 10, 45000, 55000],
        ['Studio 5', 'Regular 2D', 6, 10, 45000, 55000],
        ['Studio 6', 'Regular 2D', 6, 10, 45000, 55000],
        ['Studio 7', 'Regular 3D', 8, 10, 55000, 65000],
        ['Studio 8', 'IMAX', 10, 12, 75000, 90000],
    ];

    public function run(): void
    {
        foreach (self::STUDIO as [$nama, $format, $baris, $perBaris, $biasa, $akhirPekan]) {
            // Dicari berdasarkan nama, supaya seeder yang dijalankan dua kali tidak membuat studio ganda.
            // Studio yang sudah ada tidak diubah, karena kursinya mungkin sudah terjual.
            Studio::firstOrCreate(['name' => $nama], [
                'format' => $format,
                'baris' => $baris,
                'kursi_per_baris' => $perBaris,
                'harga_biasa' => $biasa,
                'harga_akhir_pekan' => $akhirPekan,
            ]);
        }

        $this->command->info('SELESAI: ' . count(self::STUDIO) . ' studio disiapkan.');
    }
}
