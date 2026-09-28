<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\AoranemaMlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FeedbackController extends Controller
{
    /**
     * Menampilkan form feedback untuk user.
     * Tampilan aslinya akan dikerjakan oleh tim FE.
     */
    public function create()
    {
        return view('feedback.create');
    }

    /**
     * Menyimpan feedback dari user, setelah diproses oleh ML Sentiment.
     */
    public function store(Request $request, AoranemaMlService $ml)
    {
        $validated = $request->validate([
            // Formulir menampung paling banyak lima masukan sekali kirim. Batas ini juga dicek di
            // server, karena tiap masukan dianalisis satu per satu oleh layanan ML.
            'feedbacks' => ['required', 'array', 'min:1', 'max:5'],
            'feedbacks.*.category' => [
                'required',
                'string',
                'in:booking,payment,application,customer_service,cinema_service,other',
            ],
            'feedbacks.*.comment' => [
                'required',
                'string',
                'min:1',
                'max:2000',
            ],
        ], [], [
            'feedbacks' => 'masukan',
            'feedbacks.*.category' => 'kategori masukan',
            'feedbacks.*.comment' => 'isi masukan',
        ]);

        try {
            $analyzed = [];

            $adaYangBelumDianalisis = false;

            foreach ($validated['feedbacks'] as $item) {
                try {
                    // Kalau satu analisis sudah gagal, sisanya tidak dicoba lagi, supaya penonton
                    // tidak menunggu layanan yang mati berulang kali.
                    if ($adaYangBelumDianalisis) {
                        throw new RuntimeException('Layanan sentimen sedang tidak bisa dihubungi.');
                    }

                    // Memanggil FastAPI endpoint /sentiment
                    $prediction = $ml->analyzeSentiment($item['comment']);
                    $nada = $prediction['sentiment'];
                    $keyakinan = $prediction['confidence'];
                } catch (Throwable $e) {
                    // Layanan sentimen sedang tidak bisa dihubungi. Masukannya tetap disimpan dengan
                    // nada 'unknown', bukan ditebak, supaya isi masukan penonton tidak hilang.
                    report($e);
                    $nada = 'unknown';
                    $keyakinan = null;
                    $adaYangBelumDianalisis = true;
                }

                $analyzed[] = [
                    'category' => $item['category'],
                    'comment' => $item['comment'],
                    'sentiment' => $nada,
                    'confidence' => $keyakinan,
                ];
            }

            $saved = DB::transaction(function () use ($analyzed, $request) {
                $results = [];

                foreach ($analyzed as $item) {
                    $results[] = Feedback::create([
                        'user_id' => $request->user()->id,
                        'category' => $item['category'],
                        'comment' => $item['comment'],
                        'sentiment' => $item['sentiment'],
                        'confidence' => $item['confidence'],
                    ]);
                }

                return $results;
            });

            $pesan = count($saved) > 1
                ? count($saved) . ' masukan terkirim. Terima kasih sudah menuliskannya.'
                : 'Masukanmu terkirim. Terima kasih sudah menuliskannya.';

            if ($adaYangBelumDianalisis) {
                $pesan .= ' Nada masukannya belum bisa dianalisis sekarang, tapi isinya sudah tersimpan.';
            }

            // Form di website mengharapkan halaman, bukan JSON. Jawaban JSON tetap disediakan
            // untuk pemanggil lain, misalnya kalau nanti ada aplikasi ponsel.
            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan, 'data' => $saved], 201);
            }

            return redirect('/feedback')->with('sukses', $pesan);

        } catch (Throwable $e) {
            report($e);

            $pesan = 'Masukanmu belum bisa diproses sekarang. Coba lagi beberapa saat lagi.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan], 503);
            }

            return back()->withInput()->with('gagal', $pesan);
        }
    }

    /**
     * Menampilkan dashboard statistik sentimen untuk Admin.
     */
    public function indexAdmin(Request $request)
    {
        // Saringan dari alamat: ?kategori=payment&nada=negative. Nilai di luar daftar diabaikan.
        $kategori = in_array($request->query('kategori'), ['booking', 'payment', 'application', 'customer_service', 'cinema_service', 'other'])
            ? $request->query('kategori')
            : null;

        $nada = in_array($request->query('nada'), ['positive', 'neutral', 'negative', 'unknown'])
            ? $request->query('nada')
            : null;

        $summary = Feedback::selectRaw('sentiment, COUNT(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment')
            ->toArray();

        $byCategory = Feedback::selectRaw('category, sentiment, COUNT(*) as total')
            ->groupBy('category', 'sentiment')
            ->get();

        // Ringkasan dan grafik memakai seluruh masukan, sedangkan daftarnya mengikuti saringan,
        // supaya angka besarnya tidak ikut berubah waktu admin sedang menyaring.
        $latestFeedbacks = Feedback::with('user')
            ->when($kategori, fn ($q) => $q->where('category', $kategori))
            ->when($nada, fn ($q) => $q->where('sentiment', $nada))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.feedback.index', compact('summary', 'byCategory', 'latestFeedbacks', 'kategori', 'nada'));
    }
}
