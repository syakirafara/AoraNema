<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Showtime extends Model
{
    // Aturan jadwal bioskop. Halaman admin, halaman penonton, dan seeder memakai angka yang sama dari sini.

    // Jam tayang di tiket adalah saat studio dibuka dan iklan serta cuplikan film mulai diputar.
    // Filmnya sendiri mulai sekitar sepuluh menit kemudian.
    public const IKLAN_MENIT = 10;

    // Jeda setelah film selesai untuk mengosongkan dan membersihkan studio sebelum tayangan berikutnya.
    public const JEDA_MENIT = 15;

    // Jam operasional: tayangan pertama paling pagi dan tayangan terakhir paling malam.
    public const JAM_BUKA = '10:00';
    public const JAM_TERAKHIR = '22:00';

    // Jadwal dijual untuk tujuh hari, mulai hari ini. Admin juga hanya bisa menyusun jadwal sejauh itu,
    // supaya tidak ada jadwal yang sudah dibuat tapi belum bisa dilihat penonton.
    public const HARI_DIJUAL = 7;

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

    // relasi ke tabel bookings (1 jadwal bisa dibooking banyak tiket)
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // Harga per kursi jadwal ini, dari tarif studionya pada hari tayang.
    // Muat relasi studio di query supaya tidak ada query tambahan per jadwal.
    public function harga(): int
    {
        return $this->studio->hargaUntuk($this->show_time);
    }

    // Saat filmnya selesai: jam tayang, ditambah iklan, ditambah durasi film.
    public function selesai(): Carbon
    {
        return $this->show_time->copy()->addMinutes(self::IKLAN_MENIT + $this->movie->durasi());
    }

    // Saat studio siap dipakai tayangan berikutnya: film selesai, ditambah jeda bersih-bersih.
    public function studioSiap(): Carbon
    {
        return $this->selesai()->addMinutes(self::JEDA_MENIT);
    }

    // Tiket dijual sampai jam tayang tiba.
    public function masihDijual(): bool
    {
        return $this->show_time->isFuture();
    }

    // Nomor kursi yang sudah diambil: milik pesanan lunas dan pesanan yang masih menunggu pembayaran.
    // Kursi dari pesanan yang batal sudah dilepas dan bisa dipilih lagi.
    public function kursiTerisi(): array
    {
        $pesanan = $this->relationLoaded('bookings') ? $this->bookings : $this->bookings()->get();

        return $pesanan->where('status', '!=', Booking::BATAL)->pluck('kursi')->flatten()->all();
    }

    public function sisaKursi(): int
    {
        return $this->studio->kapasitas() - count($this->kursiTerisi());
    }

    // Jadwal yang masih punya pesanan lunas atau menunggu pembayaran tidak boleh diubah atau dihapus,
    // karena penontonnya sudah memegang tiket untuk jam dan studio ini.
    public function sudahDipesan(): bool
    {
        return $this->bookings()->where('status', '!=', Booking::BATAL)->exists();
    }

    // Jam mulai berada di dalam jam operasional bioskop.
    public static function dalamJamBuka(CarbonInterface $mulai): bool
    {
        $jam = $mulai->format('H:i');

        return $jam >= self::JAM_BUKA && $jam <= self::JAM_TERAKHIR;
    }

    // Tanggal terakhir yang boleh punya jadwal.
    public static function tanggalTerakhir(): Carbon
    {
        return today()->addDays(self::HARI_DIJUAL - 1);
    }

    // Jadwal lain di studio yang sama yang waktunya bertabrakan dengan film ini, atau null.
    // Satu jadwal memakai studio dari jam tayang sampai studio siap lagi (iklan, film, dan jeda).
    // Dua jadwal bertabrakan kalau salah satunya mulai sebelum yang lain selesai memakai studio.
    public static function bentrokDengan(int $studioId, Movie $film, CarbonInterface $mulai, ?int $kecuali = null): ?self
    {
        $siap = $mulai->copy()->addMinutes(self::IKLAN_MENIT + $film->durasi() + self::JEDA_MENIT);

        return self::with('movie')
            ->where('studio_id', $studioId)
            // Film paling lama lima jam, jadi jadwal yang mulai lebih dari sehari sebelumnya pasti sudah selesai.
            ->whereBetween('show_time', [$mulai->copy()->subDay(), $siap])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->orderBy('show_time')
            ->get()
            ->first(fn ($lain) => $lain->show_time->lt($siap) && $lain->studioSiap()->gt($mulai));
    }
}
