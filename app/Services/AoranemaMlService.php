<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AoranemaMlService
{
    // Batas waktu (detik) untuk tersambung ke layanan ML. Kalau layanannya mati,
    // kita langsung tahu dalam 2 detik, tidak menunggu sampai batas waktu penuh.
    private const CONNECT_TIMEOUT = 2;

    private string $baseUrl;
    private int $timeout;
    private int $sentimentTimeout;

    public function __construct()
    {
        // Tetap menggunakan config('services.ml.url') agar tidak perlu mengubah env yang sudah ada.
        $this->baseUrl = rtrim(
            config('services.ml.url', 'http://127.0.0.1:8001'),
            '/'
        );

        // Rekomendasi dipanggil setiap kali beranda dibuka, jadi batas waktunya singkat (5 detik).
        $this->timeout = (int) config('services.ml.timeout', 5);

        // Sentimen hanya dipanggil saat form masukan dikirim, jadi boleh lebih lama.
        $this->sentimentTimeout = (int) config('services.ml.sentiment_timeout', 30);
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

        $favorite_genres = $user->favorite_genres ?? [];

        // 2. Tentukan Mode
        $mode = count($interactions) > 0 ? 'history' : 'onboarding';

        // Belum pernah memberi nilai dan belum memilih genre favorit: API ML pasti
        // menolak (422), jadi tidak perlu dipanggil. Beranda cukup tanpa rekomendasi.
        if ($mode === 'onboarding' && empty($favorite_genres)) {
            return [];
        }

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
            $payload['favorite_genres'] = $favorite_genres;
            $payload['favorite_movie_ids'] = $favorite_movie_ids;
        }

        // 4. Cek cache dulu. Kuncinya memuat id user dan md5 dari payload, jadi kalau
        //    ada rating baru atau daftar film berubah, kuncinya ikut berubah.
        $cacheKey = 'rekomendasi:user:' . $user->id . ':' . md5(json_encode($payload));

        $hasilCache = Cache::get($cacheKey);
        if ($hasilCache !== null) {
            return $hasilCache;
        }

        // Layanan ML baru saja gagal dihubungi. Selama semenit tidak dicoba lagi untuk siapa pun,
        // supaya beranda semua penonton tidak ikut menunggu layanan yang mati.
        if (Cache::has('ml:mati')) {
            return [];
        }

        // 5. Kirim Request
        $movieIds = $this->requestRecommendations($payload, $user->id);

        if ($movieIds === null) {
            // Gagal: simpan hasil kosong selama 1 menit, dan tandai layanannya sedang mati.
            Cache::put($cacheKey, [], now()->addMinute());
            Cache::put('ml:mati', true, now()->addMinute());

            return [];
        }

        // Berhasil: simpan selama 10 menit.
        Cache::put($cacheKey, $movieIds, now()->addMinutes(10));

        return $movieIds;
    }

    /**
     * Menganalisa sentimen dari teks.
     * Mengembalikan array yang berisi 'sentiment' dan 'confidence'.
     */
    public function analyzeSentiment(string $text): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout($this->sentimentTimeout)
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
     * Kirim payload ke endpoint /recommendations.
     * Mengembalikan daftar movie_id, atau null kalau request gagal.
     */
    private function requestRecommendations(array $payload, int $userId): ?array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/recommendations', $payload);
        } catch (\Exception $e) {
            Log::error('ML API Exception (Recommendation): ' . $e->getMessage());

            return null;
        }

        if (!$response->successful()) {
            Log::error('ML API Error (Recommendation): ' . $response->body());

            return null;
        }

        $data = $response->json();

        // Peringatan dari ML, misalnya genre favorit yang tidak dikenal model.
        if (!empty($data['warnings'])) {
            Log::warning('ML API Warning (Recommendation)', [
                'user_id' => $userId,
                'warnings' => $data['warnings'],
            ]);
        }

        if (!isset($data['recommendations'])) {
            Log::error('ML API Error (Recommendation): respons tidak berisi recommendations.');

            return null;
        }

        return array_column($data['recommendations'], 'movie_id');
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
