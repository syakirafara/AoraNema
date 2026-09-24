<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    // biar kolom 'id' gak bisa diisi sembarangan misalnya lewat form input
    protected $guarded = ['id'];

    // relasi ke tabel movies (Many-to-Many)
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class);
    }
    
}
