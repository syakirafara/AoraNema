<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Showtime extends Model
{
    // biar kolom 'id' gak bisa diisi sembarangan misalnya lewat form input
    protected $guarded = ['id'];

    // biar nanti kolom show_time otomatis diubah jadi tipe datetime (Carbon PHP)
    protected $casts = [
        'show_time' => 'datetime',
    ];

    // relasi ke tabel movie (1 jadwal hanya untuk 1 film)
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    // relasi ke tabel studios (1 jadwal hanya bisa di 1 ruangan studio)
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    // Harga per kursi jadwal ini, dari tarif studionya pada hari tayang.
    // Muat relasi studio di query supaya tidak ada query tambahan per jadwal.
    public function harga(): int
    {
        return $this->studio->hargaUntuk($this->show_time);
    }

    // relasi ke tabel bookings (1 jadwal bisa dibooking banyak tiket)
    // Jeda antar tayangan untuk membersihkan studio dan mengganti penonton.
    public const JEDA_MENIT = 15;

    // Jadwal lain di studio yang sama yang waktunya bertabrakan dengan film ini, atau null.
    // Dua jadwal bertabrakan kalau satu mulai sebelum yang lain selesai, ditambah jeda.
    // Film tanpa durasi dianggap dua jam.
    public static function bentrokDengan(int $studioId, int $movieId, \Carbon\CarbonInterface $mulai, ?int $kecuali = null): ?self
    {
        $lama = fn ($durasi) => ($durasi ?: 120) + self::JEDA_MENIT;
        $selesai = $mulai->copy()->addMinutes($lama(Movie::find($movieId)?->duration_minutes));

        return self::with('movie')
            ->where('studio_id', $studioId)
            ->whereBetween('show_time', [$mulai->copy()->subDay(), $selesai])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->get()
            ->first(fn ($lain) => $lain->show_time->lt($selesai)
                && $lain->show_time->copy()->addMinutes($lama($lain->movie?->duration_minutes))->gt($mulai));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }    
}
