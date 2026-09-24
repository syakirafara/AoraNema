<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    protected $guarded = ['id'];

    // Format layar yang bisa dipilih admin, sekaligus urutan tampilnya di halaman film.
    public const FORMAT = ['Regular 2D', 'Regular 3D', 'IMAX'];

    // Nomor kursi di studio ini, baris demi baris: A1, A2, ..., lalu B1, dan seterusnya.
    public function daftarKursi(): array
    {
        $kursi = [];

        for ($b = 0; $b < $this->baris; $b++) {
            for ($n = 1; $n <= $this->kursi_per_baris; $n++) {
                $kursi[] = chr(65 + $b) . $n;
            }
        }

        return $kursi;
    }

    // Jumlah kursi di studio ini.
    public function kapasitas(): int
    {
        return $this->baris * $this->kursi_per_baris;
    }

    // Satu studio dipakai untuk banyak jadwal tayang
    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    // Label untuk penonton: format dan nama studionya, misalnya "IMAX, Studio 12", supaya tahu
    // pintu mana yang dituju. Kalau nama studio sama dengan formatnya, cukup ditulis sekali.
    public function label(): string
    {
        return $this->name === $this->format ? $this->name : $this->format . ', ' . $this->name;
    }

    // Harga per kursi di studio ini pada tanggal tertentu. Akhir pekan dihitung Jumat sampai
    // Minggu, seperti kebanyakan bioskop di Indonesia.
    public function hargaUntuk(\Carbon\CarbonInterface $tanggal): int
    {
        return in_array($tanggal->dayOfWeek, [5, 6, 0]) ? $this->harga_akhir_pekan : $this->harga_biasa;
    }
}
