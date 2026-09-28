<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

// Melengkapi batas usia film yang masih kosong. Urutannya:
// 1. rating Indonesia (LSF) dari TMDB, yang sudah berbentuk SU, 13+, 17+, atau 21+,
// 2. kalau tidak ada, rating Amerika yang disetarakan,
// 3. kalau tidak ada juga, perkiraan dari genre.
// Batas usia yang sudah diisi admin tidak ditimpa, dan admin tetap bisa mengubahnya di halaman Film.
// Dipanggil DatabaseSeeder setelah data film diambil, dan bisa dijalankan sendiri:
// php artisan db:seed --class=LengkapiFilmSeeder
class LengkapiFilmSeeder extends Seeder
{
    private const USIA = ['SU', '13+', '17+', '21+'];

    // Rating Amerika dan padanannya di Indonesia.
    private const RATING_AMERIKA = ['G' => 'SU', 'PG' => 'SU', 'PG-13' => '13+', 'R' => '17+', 'NC-17' => '21+'];

    public function run(): void
    {
        $kunci = config('services.tmdb.key');

        if (! $kunci) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di .env');

            return;
        }

        foreach (Movie::with('genres')->whereNull('usia')->get() as $film) {
            // Rating tiap negara, misalnya ['ID' => ['17+'], 'US' => ['R']].
            $rating = collect($film->tmdb_id
                ? Http::timeout(30)->get("https://api.themoviedb.org/3/movie/{$film->tmdb_id}/release_dates", ['api_key' => $kunci])->json('results') ?? []
                : [])
                ->mapWithKeys(fn ($negara) => [$negara['iso_3166_1'] => collect($negara['release_dates'])->pluck('certification')->filter()]);

            $indonesia = $rating->get('ID', collect())->first(fn ($r) => in_array($r, self::USIA));
            $amerika = $rating->get('US', collect())->map(fn ($r) => self::RATING_AMERIKA[$r] ?? null)->filter()->first();

            $usia = $indonesia ?? $amerika ?? $this->perkiraanDariGenre($film);
            $film->update(['usia' => $usia]);

            $sumber = $indonesia ? 'LSF' : ($amerika ? 'dari rating Amerika' : 'perkiraan dari genre');
            $this->command->info("{$film->title}: {$usia} ({$sumber})");
        }
    }

    // Perkiraan kasar, hanya dipakai kalau TMDB tidak punya rating sama sekali.
    private function perkiraanDariGenre(Movie $film): string
    {
        $genre = $film->genres->pluck('name');

        return match (true) {
            $genre->contains('Horror') => '17+',
            $genre->intersect(['Family', 'Animation'])->isNotEmpty() => 'SU',
            default => '13+',
        };
    }
}
