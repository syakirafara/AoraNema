<?php

namespace Database\Seeders;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Permintaan ke API TMDB untuk seeder film. Koneksi yang putus sebentar dicoba lagi sampai tiga kali.
// Kalau tetap gagal, hasilnya null dan seeder melewatinya, bukan berhenti di tengah jalan. Galat dari
// Laravel memuat alamat lengkap beserta kunci API, jadi galat itu sengaja tidak diteruskan.
class Tmdb
{
    public static function ambil(string $jalur, array $parameter = []): ?array
    {
        try {
            $respons = Http::retry(3, 2000, throw: false)
                ->connectTimeout(10)
                ->timeout(30)
                ->get('https://api.themoviedb.org/3/' . $jalur, $parameter + ['api_key' => config('services.tmdb.key')]);
        } catch (ConnectionException) {
            return null;
        }

        return $respons->successful() ? $respons->json() : null;
    }
}
