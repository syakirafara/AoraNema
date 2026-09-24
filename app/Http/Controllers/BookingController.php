<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class BookingController extends Controller
{
    // Cara bayar yang dipilih di halaman bayar AoraNema diteruskan ke Midtrans, supaya penonton
    // tidak diminta memilih lagi di halaman Midtrans.
    private const CARA_BAYAR_MIDTRANS = [
        'qris' => ['other_qris', 'gopay'],
        'va' => ['bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel', 'cimb_va', 'other_va'],
        'ewallet' => ['gopay', 'shopeepay'],
    ];

    // Menyiapkan pustaka Midtrans. Mengembalikan false kalau kunci server belum dipasang di .env.
    private function midtransSiap(): bool
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        return Config::$serverKey !== '';
    }

    // Menyamakan status pesanan dengan status transaksinya di Midtrans. Pesanan hanya menjadi
    // lunas kalau Midtrans menyatakan uangnya sudah diterima. Pesanan yang batal atau kedaluwarsa
    // melepas kursinya, supaya bisa dipesan orang lain. Hanya pesanan yang masih menunggu
    // pembayaran yang diubah, jadi pesanan yang sudah batal tidak bisa hidup lagi.
    private function terapkanStatus(string $bookingCode, string $statusMidtrans, ?string $statusPenipuan = null): void
    {
        $lunas = $statusMidtrans === 'settlement' || ($statusMidtrans === 'capture' && $statusPenipuan !== 'challenge');
        $batal = in_array($statusMidtrans, ['expire', 'cancel', 'deny', 'failure']);

        $pesanan = Booking::where('booking_code', $bookingCode)->where('status', Booking::MENUNGGU);

        if ($lunas) {
            $pesanan->update(['status' => Booking::LUNAS]);
        } elseif ($batal) {
            $pesanan->update(['status' => Booking::BATAL]);
        }
    }

    // Menanyakan status pesanan yang masih menunggu pembayaran ke Midtrans. Dipanggil saat penonton
    // kembali dari halaman Midtrans dan saat membuka Tiket Saya, karena pemberitahuan dari Midtrans
    // (webhook) tidak bisa sampai ke laptop yang tidak bisa diakses dari internet.
    private function cekStatus(string $bookingCode): void
    {
        $pesanan = Booking::where('booking_code', $bookingCode)->first();

        if (! $pesanan || $pesanan->status !== Booking::MENUNGGU || ! $this->midtransSiap()) {
            return;
        }

        try {
            // Kode pesanan juga dipakai sebagai order_id di Midtrans.
            $status = \Midtrans\Transaction::status($bookingCode);
            $this->terapkanStatus($bookingCode, $status->transaction_status, $status->fraud_status ?? null);
        } catch (\Exception $e) {
            // Midtrans menjawab 404 kalau penonton belum memilih cara bayar di halaman Midtrans.
            // Kalau batas bayarnya sudah lewat, pesanan itu dianggap kedaluwarsa. Galat lain,
            // misalnya internet putus, tidak mengubah apa pun supaya pesanan tidak batal karena salah baca.
            if (str_contains($e->getMessage(), '404') && $pesanan->created_at->lt(now()->subMinutes(Booking::BATAS_BAYAR_MENIT))) {
                $this->terapkanStatus($bookingCode, 'expire');
            }
        }
    }

    // Melepas kursi dari pesanan di jadwal ini yang sudah melewati batas bayar tanpa dibayar.
    private function lepasKedaluwarsa(Showtime $showtime): void
    {
        $kedaluwarsa = Booking::where('showtime_id', $showtime->id)
            ->where('status', Booking::MENUNGGU)
            ->where('created_at', '<', now()->subMinutes(Booking::BATAS_BAYAR_MENIT));

        // Tanpa Midtrans tidak ada yang bisa ditanyakan, jadi pesanan yang lewat batas langsung dibatalkan.
        if (! $this->midtransSiap()) {
            $kedaluwarsa->update(['status' => Booking::BATAL]);

            return;
        }

        $kedaluwarsa->pluck('booking_code')->each(fn ($kode) => $this->cekStatus($kode));
    }

    // Jadwal yang dipilih di halaman detail film dibawa lewat ?jadwal={id}. Studio, kursi, jam,
    // dan harga semuanya diambil dari jadwal itu, jadi tidak ada yang ditebak dari nama layar.
    private function ambilJadwal(Request $request, string $slug): Showtime
    {
        $movie = Movie::findOrFail(Str::afterLast($slug, '-'));
        $showtime = Showtime::with(['movie', 'studio'])->find((int) $request->input('jadwal'));

        // Jadwal harus milik film di alamatnya, dan filmnya tidak diarsipkan.
        abort_if($showtime === null || $showtime->movie_id !== $movie->id || ! $movie->is_showing, 404);

        // Penjualan ditutup begitu jam tayang tiba. Penonton dikembalikan ke halaman film dengan
        // penjelasan, supaya bisa langsung memilih jam lain.
        if (! $showtime->masihDijual()) {
            throw new HttpResponseException($this->kembaliKeFilm(
                $showtime,
                'Penjualan tiket jam ' . $showtime->show_time->format('H:i') . ' sudah ditutup karena filmnya sudah mulai. Silakan pilih jam lain.'
            ));
        }

        return $showtime;
    }

    private function kembaliKeFilm(Showtime $jadwal, string $pesan)
    {
        return redirect('/film/' . $jadwal->movie->slug() . '?tanggal=' . $jadwal->show_time->format('Y-m-d'))
            ->with('error', $pesan);
    }

    // Kursi yang tampil terisi bagi penonton ini. Pesanannya sendiri yang belum dibayar tidak ikut
    // dihitung, karena pesanan itu dibatalkan dan diganti begitu ia menekan Bayar lagi.
    private function kursiTerisi(Showtime $jadwal, int $userId): array
    {
        $this->lepasKedaluwarsa($jadwal);

        $milikSendiri = Booking::where('showtime_id', $jadwal->id)
            ->where('user_id', $userId)
            ->where('status', Booking::MENUNGGU)
            ->pluck('kursi')
            ->flatten()
            ->all();

        return array_values(array_diff($jadwal->kursiTerisi(), $milikSendiri));
    }

    // Pesanan lunas penonton ini di jadwal yang sama yang memuat salah satu kursi itu, atau null.
    // Terjadi kalau ia menekan Kembali dari halaman Midtrans, atau menekan Bayar dua kali.
    private function pesananSendiri(Showtime $jadwal, int $userId, array $kursi): ?Booking
    {
        return Booking::where('showtime_id', $jadwal->id)
            ->where('user_id', $userId)
            ->where('status', Booking::LUNAS)
            ->get()
            ->first(fn ($b) => array_intersect($b->kursi, $kursi));
    }

    // Kursi dari alamat dicocokkan dengan susunan kursi studio. Kursi yang tidak ada di studio,
    // atau lebih dari batas per pesanan, membuat permintaan ditolak.
    private function ambilKursi(string $kursiInput, Showtime $showtime): array
    {
        $kursi = array_values(array_unique(array_filter(explode(',', $kursiInput))));

        abort_if(count($kursi) < 1 || count($kursi) > Booking::MAKS_KURSI || array_diff($kursi, $showtime->studio->daftarKursi()), 404);

        return $kursi;
    }

    // Kembali ke denah dengan pesan, supaya penonton bisa langsung memilih kursi lain.
    // Kursi yang sudah dipesan orang lain tampil terisi di denah itu. Kalau kursinya ternyata
    // milik pesanan lunas penonton ini sendiri, ia dibawa ke tiketnya.
    private function kursiSudahDiambil(Showtime $jadwal, int $userId, array $diambil, int $jumlah)
    {
        if ($tiket = $this->pesananSendiri($jadwal, $userId, $diambil)) {
            return redirect('/tiket/' . $tiket->booking_code)
                ->with('warning', 'Kursi ' . implode(', ', $tiket->kursi) . ' sudah kamu pesan. Ini tiketnya.');
        }

        return redirect('/kursi/' . $jadwal->movie->slug() . '?jadwal=' . $jadwal->id . '&jumlah=' . $jumlah)
            ->with('error', 'Kursi ' . implode(', ', $diambil) . ' sudah dipesan orang lain. Silakan pilih kursi lain.');
    }

    public function pilihKursi(Request $request, string $slug)
    {
        $jadwal = $this->ambilJadwal($request, $slug);
        $terisi = $this->kursiTerisi($jadwal, $request->user()->id);
        $sisa = $jadwal->studio->kapasitas() - count($terisi);

        if ($sisa < 1) {
            return $this->kembaliKeFilm($jadwal, 'Kursi untuk jam ' . $jadwal->show_time->format('H:i') . ' sudah habis. Silakan pilih jam lain.');
        }

        $jumlah = max(1, min(Booking::MAKS_KURSI, (int) $request->query('jumlah', 1)));

        return view('kursi', [
            'film' => $jadwal->movie,
            'jadwal' => $jadwal,
            'studio' => $jadwal->studio,
            'layar' => $jadwal->studio->label(),
            'jam' => $jadwal->show_time->format('H:i'),
            'tanggal' => $jadwal->show_time->copy()->startOfDay(),
            'harga' => $jadwal->harga(),
            // Kalau kursi kosongnya tinggal sedikit, jumlah tiket disesuaikan dengan sisanya.
            'jumlah' => min($jumlah, $sisa),
            'jumlahDiminta' => $jumlah,
            'sisa' => $sisa,
            'kursiTerisi' => $terisi,
        ]);
    }

    public function halamanBayar(Request $request, string $slug)
    {
        $jadwal = $this->ambilJadwal($request, $slug);
        $kursi = $this->ambilKursi(is_string($request->query('kursi')) ? $request->query('kursi') : '', $jadwal);
        $userId = $request->user()->id;

        // Kursi bisa saja sudah diambil orang lain sejak denahnya dibuka.
        if ($diambil = array_values(array_intersect($kursi, $this->kursiTerisi($jadwal, $userId)))) {
            return $this->kursiSudahDiambil($jadwal, $userId, $diambil, count($kursi));
        }

        return view('bayar', [
            'film' => $jadwal->movie,
            'jadwal' => $jadwal,
            'layar' => $jadwal->studio->label(),
            'jam' => $jadwal->show_time->format('H:i'),
            'tanggal' => $jadwal->show_time->copy()->startOfDay(),
            'harga' => $jadwal->harga(),
            'kursi' => $kursi,
        ]);
    }

    public function prosesBayar(Request $request, string $slug)
    {
        $showtime = $this->ambilJadwal($request, $slug);
        $this->lepasKedaluwarsa($showtime);
        $metode = $request->input('metode');

        abort_unless(in_array($metode, ['qris', 'va', 'ewallet']), 404);

        $kursiArr = $this->ambilKursi(is_string($request->input('kursi')) ? $request->input('kursi') : '', $showtime);

        // Harga dihitung ulang di server dari tarif studio, bukan diambil dari halaman.
        $grossAmount = count($kursiArr) * ($showtime->harga() + Booking::BIAYA_LAYANAN);

        // Buat booking_code unik, yang juga dipakai sebagai order_id di Midtrans. Kode diulang kalau kebetulan sudah dipakai.
        do {
            $bookingCode = strtoupper(Str::random(6));
        } while (Booking::where('booking_code', $bookingCode)->exists());

        $userId = $request->user()->id;

        // Pemeriksaan kursi dan penyimpanan pesanan dijalankan dalam satu transaksi yang mengunci
        // jadwal ini. Dua penonton yang memilih kursi sama pada saat bersamaan, atau satu penonton
        // yang menekan Bayar dua kali, diproses bergantian. Kalau satu kursi ternyata sudah diambil,
        // seluruh pesanan dibatalkan, jadi tidak ada kursi yang tertahan setengah.
        try {
            $pesananLama = DB::transaction(function () use ($showtime, $kursiArr, $userId, $bookingCode, $grossAmount, $metode) {
                Showtime::whereKey($showtime->id)->lockForUpdate()->first();

                // Pesanan penonton ini di jadwal yang sama yang belum dibayar dibatalkan, supaya ia bisa
                // mencoba lagi tanpa terhalang kursinya sendiri. Pesanannya tidak dihapus, jadi tetap
                // tercatat kalau ternyata pernah dibayar.
                $lama = Booking::where('showtime_id', $showtime->id)
                    ->where('user_id', $userId)
                    ->where('status', Booking::MENUNGGU)
                    ->pluck('booking_code');

                Booking::whereIn('booking_code', $lama)->where('status', Booking::MENUNGGU)->update(['status' => Booking::BATAL]);

                if ($diambil = array_values(array_intersect($kursiArr, $showtime->kursiTerisi()))) {
                    throw new \DomainException(implode(',', $diambil));
                }

                // Cara bayar dicatat sebelum Midtrans dipanggil, supaya tetap tersimpan walaupun Midtrans gagal.
                Booking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => $userId,
                    'showtime_id' => $showtime->id,
                    'kursi' => collect($kursiArr)->sort(SORT_NATURAL)->values()->all(),
                    'total_price' => $grossAmount, // Harga termasuk biaya layanan
                    'status' => Booking::MENUNGGU,
                    'payment_method' => $metode,
                ]);

                return $lama;
            });
        } catch (\DomainException $e) {
            return $this->kursiSudahDiambil($showtime, $userId, explode(',', $e->getMessage()), count($kursiArr));
        }

        // Tanpa kunci Midtrans (misalnya di laptop pengembang), pesanan dianggap lunas supaya alurnya
        // tetap bisa dicoba sampai tiket. Begitu kunci dipasang, jalur ini tidak dipakai lagi.
        if (! $this->midtransSiap()) {
            $this->terapkanStatus($bookingCode, 'settlement');

            return redirect('/tiket/' . $bookingCode)->with('warning', 'Pembayaran belum tersambung ke Midtrans, jadi pesanan ini dianggap lunas tanpa ditagih. Tiket ini hanya untuk uji coba.');
        }

        // Transaksi Midtrans milik pesanan lama ikut dibatalkan, supaya tidak bisa dibayar lagi.
        // Midtrans menjawab 404 kalau cara bayarnya belum dipilih; itu tidak apa-apa.
        foreach ($pesananLama as $kodeLama) {
            try {
                \Midtrans\Transaction::cancel($kodeLama);
            } catch (\Exception $e) {
            }
        }

        $params = [
            'transaction_details' => [
                'order_id' => $bookingCode,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'callbacks' => [
                'finish' => url('/tiket/' . $bookingCode),
            ],
            'expiry' => ['unit' => 'minutes', 'duration' => Booking::BATAS_BAYAR_MENIT],
            'enabled_payments' => self::CARA_BAYAR_MIDTRANS[$metode],
        ];

        try {
            $snapUrl = Snap::createTransaction($params)->redirect_url;
        } catch (\Exception $e) {
            // Halaman Midtrans gagal dibuat. Pesanannya dibatalkan supaya kursinya tidak tertahan.
            report($e);
            $this->terapkanStatus($bookingCode, 'failure');

            return back()->with('error', 'Halaman pembayaran gagal dibuka. Coba lagi sebentar lagi.');
        }

        // Proyek ini untuk belajar dan tidak ada yang membayar sungguhan, jadi pesanan langsung
        // dianggap lunas begitu halaman Midtrans dibuka. Halaman Midtrans tetap ditampilkan untuk
        // memperlihatkan alurnya. Untuk pembayaran sungguhan, hapus baris terapkanStatus ini:
        // pesanan akan menunggu sampai Midtrans menyatakan lunas lewat cekStatus() dan notifikasiMidtrans().
        $this->terapkanStatus($bookingCode, 'settlement');

        return redirect()->away($snapUrl);
    }

    // Pemberitahuan pembayaran dari Midtrans. Alamat ini didaftarkan di dashboard Midtrans
    // (Settings, Payment Notification URL) begitu website bisa diakses dari internet.
    public function notifikasiMidtrans(Request $request)
    {
        // Tanpa kunci server, tanda tangan tidak bisa diperiksa, jadi pemberitahuan ditolak.
        abort_unless($this->midtransSiap(), 404);

        $isi = $request->all();
        $kode = (string) ($isi['order_id'] ?? '');

        // Tanda tangan dicek supaya tidak ada yang bisa memalsukan pemberitahuan "sudah dibayar".
        $tandaTangan = hash('sha512', $kode . ($isi['status_code'] ?? '') . ($isi['gross_amount'] ?? '') . Config::$serverKey);
        abort_unless(hash_equals($tandaTangan, (string) ($isi['signature_key'] ?? '')), 403);

        $pesanan = Booking::where('booking_code', $kode)->first();

        if ($pesanan?->status === Booking::MENUNGGU) {
            // Status transaksi tidak ikut ditandatangani, jadi statusnya ditanyakan langsung ke Midtrans.
            $this->cekStatus($kode);
        } elseif ($pesanan?->status !== Booking::LUNAS && in_array($isi['transaction_status'] ?? '', ['settlement', 'capture'])) {
            // Uang masuk untuk pesanan yang sudah batal atau tidak ada. Dicatat di log supaya
            // pengelola bisa mengembalikan dananya.
            Log::warning('Pembayaran masuk untuk pesanan yang tidak sedang menunggu pembayaran', [
                'order_id' => $kode,
                'status_pesanan' => $pesanan?->status,
                'gross_amount' => $isi['gross_amount'] ?? null,
            ]);
        }

        return response()->json(['diterima' => true]);
    }

    public function tiketSaya(Request $request)
    {
        // Pesanan yang masih menunggu pembayaran ditanyakan dulu ke Midtrans, supaya statusnya terbaru.
        Booking::where('user_id', $request->user()->id)
            ->where('status', Booking::MENUNGGU)
            ->pluck('booking_code')
            ->each(fn ($kode) => $this->cekStatus($kode));

        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $pesanan = Booking::where('user_id', $request->user()->id)
            ->with(['showtime.movie.genres', 'showtime.movie.jadwalMendatang.studio', 'showtime.studio'])
            ->orderBy('id')
            ->get()
            ->map(function ($b) use ($namaHari, $namaBulan) {
                $waktu = $b->showtime->show_time;
                $dibatalkan = $b->status === Booking::BATAL;

                // Tiket baru kedaluwarsa setelah filmnya selesai.
                $lewat = $b->showtime->selesai()->isPast();

                return [
                    'kode' => $b->booking_code,
                    'movieId' => $b->showtime->movie_id,
                    'film' => $b->showtime->movie->kartu(),
                    'tanggal' => $waktu,
                    'tanggalTeks' => $namaHari[$waktu->dayOfWeek] . ', ' . $waktu->day . ' ' . $namaBulan[$waktu->month] . ' ' . $waktu->year,
                    'jam' => $waktu->format('H:i'),
                    'layar' => $b->showtime->studio->label(),
                    'kursi' => $b->kursi,
                    'total' => $b->total_price,
                    'status' => $b->status,
                    'kapan' => match (true) {
                        $waktu->isPast() => 'Sedang diputar',
                        $waktu->isToday() => 'Hari ini',
                        $waktu->isTomorrow() => 'Besok',
                        default => (int) today()->diffInDays($waktu->copy()->startOfDay()) . ' hari lagi',
                    },
                    'aktif' => ! $dibatalkan && ! $lewat,
                    // Film hanya bisa dinilai setelah benar-benar ditonton: sudah dibayar dan filmnya selesai.
                    'bisaDinilai' => $b->status === Booking::LUNAS && $lewat,
                    // Nilai disimpan per pesanan, jadi film yang sama yang ditonton dua kali
                    // punya dua nilai terpisah.
                    'nilai' => $b->rating,
                ];
            });

        return view('tiket-saya', [
            'tab' => $request->query('tab') === 'riwayat' ? 'riwayat' : 'aktif',
            'aktif' => $pesanan->where('aktif', true)->sortBy('tanggal')->values()->all(),
            'riwayat' => $pesanan->where('aktif', false)->sortByDesc('tanggal')->values()->all(),
        ]);
    }

    public function nilaiFilm(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20'],
            'nilai' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        // Pesanan harus milik akun ini, sudah dibayar, dan filmnya sudah selesai diputar.
        // Tanpa ini siapa pun bisa menilai film apa saja.
        $pesanan = Booking::with('showtime.movie')
            ->where('user_id', $request->user()->id)
            ->where('booking_code', strtoupper($data['kode']))
            ->where('status', Booking::LUNAS)
            ->first();

        abort_unless($pesanan && $pesanan->showtime->selesai()->isPast(), 403);

        // Satu penilaian per pesanan. Menilai ulang pesanan yang sama mengganti nilai lamanya,
        // sedangkan pesanan lain untuk film yang sama punya penilaiannya sendiri.
        $pesanan->update(['rating' => $data['nilai']]);

        return redirect('/tiket-saya?tab=riwayat')
            ->with('sukses', 'Penilaianmu untuk "' . $pesanan->showtime->movie->title . '" tersimpan: ' . $data['nilai'] . ' dari 5.');
    }

    public function halamanTiket(Request $request, string $booking_code)
    {
        // Kode tiket di alamat boleh ditulis huruf kecil, tapi kode batang Code 39 hanya menerima huruf besar.
        $kode = strtoupper($booking_code);

        $pesanan = Booking::where('booking_code', $kode)->first();

        // Tiket hanya bisa dibuka pemesannya dan admin. Tanpa ini, siapa pun yang login
        // dan tahu kodenya bisa melihat tiket orang lain.
        abort_unless($pesanan && ($pesanan->user_id === $request->user()->id || $request->user()->isAdmin()), 404);

        // Penonton biasanya sampai di sini dari halaman Midtrans, jadi statusnya ditanyakan dulu.
        $this->cekStatus($kode);
        $pesanan = $pesanan->fresh(['user', 'showtime.movie', 'showtime.studio']);

        $film = $pesanan->showtime->movie;
        $tanggalCarbon = $pesanan->showtime->show_time;

        $daftarMetode = ['qris' => 'QRIS', 'va' => 'Transfer Bank', 'ewallet' => 'Dompet Digital'];

        // Kode batang hanya ditampilkan untuk tiket yang masih bisa dipakai masuk: sudah dibayar dan
        // filmnya belum selesai. Tiket lain tetap bisa dibuka, tapi tanpa kode yang bisa dipindai.
        $keadaan = match (true) {
            $pesanan->status === Booking::BATAL => 'batal',
            $pesanan->status !== Booking::LUNAS => 'belum-bayar',
            $pesanan->showtime->selesai()->isPast() => 'selesai',
            default => 'aktif',
        };

        // Generate Barcode menggunakan picqer/php-barcode-generator
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();

        return view('tiket', [
            'pesanan' => $pesanan,
            'film' => $film,
            'tanggalCarbon' => $tanggalCarbon,
            'jam' => $tanggalCarbon->format('H:i'),
            'layar' => $pesanan->showtime->studio->label(),
            'kursi' => $pesanan->kursi,
            'namaMetode' => $daftarMetode[$pesanan->payment_method ?? ''] ?? 'Midtrans',
            'total' => $pesanan->total_price,
            'kode' => $kode,
            'batang' => $generator->getBarcode($kode, $generator::TYPE_CODE_39, 2, 64, 'black'),
            'keadaan' => $keadaan,
        ]);
    }
}
