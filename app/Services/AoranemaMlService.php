<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AoranemaMlService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        // Tetap menggunakan config('services.ml.url') agar tidak perlu mengubah env yang sudah ada.
        $this->baseUrl = rtrim(
            config('services.ml.url', 'http://127.0.0.1:8001'),
            '/'
        );

        // Gunakan timeout default 60 detik jika tidak ada
        $this->timeout = config('services.ml.timeout', 60);
    }

    /**
     * Dapatkan rekomendasi untuk user tertentu.
     * Mengembalikan daftar ID movie (movie_id) yang direkomendasikan secara berurutan.
     * Jika terjadi kegagalan (timeout/error), kembalikan array kosong.
     */
    public function getRecommendationsForUser(User $user, $candidates, $movieCatalog): array
    {
        // 1. Cek Riwayat Interaksi (nilai bintang yang diberikan user di pesanannya)
        $pesananDinilai = Booking::with('showtime')
                               ->where('user_id', $user->id)
                               ->whereNotNull('rating')
                               ->get();
                               
        $interactions = [];
        $favorite_movie_ids = [];

        foreach ($pesananDinilai->groupBy('showtime.movie_id') as $movieId => $nilaiFilm) {
            $interactions[] = [
                'movie_id' => $movieId,
                'rating' => round($nilaiFilm->avg('rating'), 2),
            ];
            $favorite_movie_ids[] = $movieId;
        }
        $favorite_movie_ids = array_unique($favorite_movie_ids);

        // 2. Tentukan Mode
        $mode = count($interactions) > 0 ? 'history' : 'onboarding';

        // 3. Susun Payload
        $payload = [
            'mode' => $mode,
            'movie_catalog' => $this->formatCatalog($movieCatalog),
            'candidates' => $this->formatCatalog($candidates),
            'top_k' => 10,
            'snapshot_year' => (int) date('Y'),
        ];

        if ($mode === 'history') {
            $payload['interactions'] = $interactions;
        } else {
            // Mode onboarding
            $favorite_genres = $user->genres()->pluck('name')->toArray();
            $payload['favorite_genres'] = $favorite_genres;
            $payload['favorite_movie_ids'] = $favorite_movie_ids;
        }

        // 4. Kirim Request
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/recommendations', $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['recommendations'])) {
                    return array_column($data['recommendations'], 'movie_id');
                }
            } else {
                Log::error('ML API Error (Recommendation): ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('ML API Exception (Recommendation): ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Menganalisa sentimen dari teks.
     * Mengembalikan array yang berisi 'sentiment' dan 'confidence'.
     */
    public function analyzeSentiment(string $text): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/sentiment', [
                'text' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Sentiment ML service gagal: ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Format collection model Movie menjadi bentuk dict yang dikenali FastAPI.
     */
    private function formatCatalog($movies): array
    {
        $catalog = [];
        foreach ($movies as $movie) {
            $genres = $movie->genres->pluck('name')->implode('|');
            $releaseYear = $movie->release_date ? (int) date('Y', strtotime($movie->release_date)) : null;

            $item = [
                'movie_id' => $movie->id,
                'title' => $movie->title,
            ];

            if (!empty($genres)) {
                $item['genres'] = $genres;
            }
            if ($releaseYear) {
                $item['release_year'] = $releaseYear;
            }
            if ($movie->duration_minutes) {
                $item['runtime'] = $movie->duration_minutes;
            }
            if ($movie->tmdb_id) {
                $item['tmdb_id'] = $movie->tmdb_id;
            }

            $catalog[] = $item;
        }

        return $catalog;
    }
}
