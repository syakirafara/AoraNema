<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    // Isi kolom status. Pesanan dibuat 'pending', lalu menjadi 'paid' setelah dibayar,
    // atau 'cancelled' kalau batal, gagal, atau lewat batas waktu bayar.
    public const MENUNGGU = 'pending';
    public const LUNAS = 'paid';
    public const BATAL = 'cancelled';

    // Nama status untuk ditampilkan di halaman.
    public const NAMA_STATUS = [
        self::MENUNGGU => 'Menunggu pembayaran',
        self::LUNAS => 'Lunas',
        self::BATAL => 'Batal',
    ];

    // Biaya layanan per tiket, ditagihkan di atas harga tiket.
    public const BIAYA_LAYANAN = 3000;

    // Paling banyak enam kursi dalam satu pesanan.
    public const MAKS_KURSI = 6;

    // Batas waktu bayar. Selama itu kursi ditahan untuk pemesan; lewat dari itu kursinya dilepas.
    public const BATAS_BAYAR_MENIT = 15;

    protected $guarded = ['id'];

    // Daftar nomor kursi disimpan sebagai JSON, dibaca kembali sebagai array PHP
    protected $casts = [
        'kursi' => 'array',
    ];

    // Pesanan ini dibuat oleh satu user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Pesanan ini untuk satu jadwal tayang spesifik
    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function namaStatus(): string
    {
        return self::NAMA_STATUS[$this->status] ?? $this->status;
    }
}
