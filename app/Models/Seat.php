<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Seat extends Model
{
    protected $guarded = ['id'];

    // Kursi ini milik satu studio tertentu
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    // Pesanan yang memakai kursi ini, dari jadwal mana pun
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class);
    }
}
