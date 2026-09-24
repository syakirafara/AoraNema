<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Booking extends Model
{
    protected $guarded = ['id'];

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

    // Kursi yang dipesan, lewat tabel penghubung booking_seat
    public function seats(): BelongsToMany
    {
        return $this->belongsToMany(Seat::class);
    }
}
