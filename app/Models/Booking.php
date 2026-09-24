<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
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
}
