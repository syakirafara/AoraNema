<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;

// Melengkapi data film yang masih kosong dari TMDB:
// - Durasi, untuk film yang waktu ditambahkan belum punya durasi di TMDB (biasanya film akan tayang).
// - Batas usia. Urutannya: rating Indonesia (LSF) di TMDB, yang sudah berbentuk SU, 13+, 17+, atau 21+,
//   lalu rating Amerika yang disetarakan, lalu perkiraan dari genre.
// Isian yang sudah ada, termasuk yang diisi admin, tidak ditimpa. Film yang datanya gagal diambil
// dilewati, dan akan dicoba lagi saat seeder ini dijalankan berikutnya.
// Dipanggil DatabaseSeeder setelah data film diambil, dan bisa dijalankan sendiri:
// php artisan db:seed --class=LengkapiFilmSeeder
class LengkapiFilmSeeder extends Seeder
{
    private const USIA = ['SU', '13+', '17+', '21+'];

    // Rating Amerika dan padanannya di Indonesia.
    private const RATING_AMERIKA = ['G' => 'SU', 'PG' => 'SU', 'PG-13' => '13+', 'R' => '17+', 'NC-17' => '21+'];

    public function run(): void
    {
        if (! config('services.tmdb.key')) {
            $this->command->error('GAGAL: TMDB_API_KEY belum dipasang di .env');

            return;
        }

        $film = Movie::with('genres')
            ->whereNotNull('tmdb_id')
            ->where(fn ($q) => $q->whereNull('usia')->orWhereNull('duration_minutes'))
            ->get();

        foreach ($film as $f) {
            $isi = [];

            if (! $f->duration_minutes) {
                $runtime = Tmdb::ambil("movie/{$f->tmdb_id}")['runtime'] ?? 0;

                if ($runtime > 0) {
                    $isi['duration_minutes'] = $runtime;
                }
            }

            if (! $f->usia) {
                $hasil = Tmdb::ambil("movie/{$f->tmdb_id}/release_dates");

                if ($hasil === null) {
                    $this->command->warn("Dilewati, rating usianya gagal diambil: {$f->title}");
                } else {
                    $isi['usia'] = $this->batasUsia($f, $hasil['results'] ?? []);
                }
            }

            if ($isi) {
                $f->update($isi);
                $this->command->info($f->title . ': ' . collect($isi)->map(fn ($v, $k) => $k === 'usia' ? $v : $v . ' menit')->implode(', '));
            }
        }
    }

    // Batas usia dari daftar rating per negara, misalnya ['ID' => ['17+'], 'US' => ['R']].
    private function batasUsia(Movie $film, array $perNegara): string
    {
        $rating = collect($perNegara)
            ->mapWithKeys(fn ($negara) => [$negara['iso_3166_1'] => collect($negara['release_dates'])->pluck('certification')->filter()]);

        $indonesia = $rating->get('ID', collect())->first(fn ($r) => in_array($r, self::USIA));
        $amerika = $rating->get('US', collect())->map(fn ($r) => self::RATING_AMERIKA[$r] ?? null)->filter()->first();

        return $indonesia ?? $amerika ?? $this->perkiraanDariGenre($film);
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
