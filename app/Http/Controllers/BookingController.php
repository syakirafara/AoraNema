<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class BookingController extends Controller
{
    // Batas waktu bayar. Selama itu kursi ditahan untuk pemesan; lewat dari itu kursinya dilepas.
    private const BATAS_BAYAR_MENIT = 15;

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
        Config::$serverKey = config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = filter_var(config('services.midtrans.is_production') ?? env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        return ! empty(Config::$serverKey);
    }

    // Menyamakan status pesanan dengan status transaksinya di Midtrans. Pesanan hanya menjadi
    // lunas kalau Midtrans menyatakan uangnya sudah diterima. Pesanan yang batal atau kedaluwarsa
    // melepas kursinya, supaya bisa dipesan orang lain.
    private function terapkanStatus(string $bookingCode, string $statusMidtrans, ?string $statusPenipuan = null): void
    {
        $lunas = $statusMidtrans === 'settlement' || ($statusMidtrans === 'capture' && $statusPenipuan !== 'challenge');
        $batal = in_array($statusMidtrans, ['expire', 'cancel', 'deny', 'failure']);

        $pesanan = Booking::where('booking_code', $bookingCode)->where('status', 'pending');

        if ($lunas) {
            $pesanan->update(['status' => 'paid']);
        } elseif ($batal) {
            $pesanan->update(['status' => 'cancelled']);
        }
    }

    // Menanyakan status pesanan yang masih menunggu pembayaran ke Midtrans. Dipanggil saat penonton
    // kembali dari halaman Midtrans dan saat membuka Tiket Saya, karena pemberitahuan dari Midtrans
    // (webhook) tidak bisa sampai ke laptop yang tidak bisa diakses dari internet.
    private function cekStatus(string $bookingCode): void
    {
        $pesanan = Booking::where('booking_code', $bookingCode)->first();

        if (! $pesanan || $pesanan->status !== 'pending' || ! $this->midtransSiap()) {
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
            if (str_contains($e->getMessage(), '404') && $pesanan->created_at->lt(now()->subMinutes(self::BATAS_BAYAR_MENIT))) {
                $this->terapkanStatus($bookingCode, 'expire');
            }
        }
    }

    // Melepas kursi dari pesanan di jadwal ini yang sudah melewati batas bayar tanpa dibayar.
    private function lepasKedaluwarsa(Showtime $showtime): void
    {
        Booking::where('showtime_id', $showtime->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::BATAS_BAYAR_MENIT))
            ->pluck('booking_code')
            ->each(fn ($kode) => $this->cekStatus($kode));
    }

    // Jadwal yang dipilih di halaman detail film dibawa lewat ?jadwal={id}. Studio, kursi, jam,
    // dan harga semuanya diambil dari jadwal itu, jadi tidak ada yang ditebak dari nama layar.
    private function ambilJadwal(Request $request, string $slug): Showtime
    {
        $parts = explode('-', $slug);
        $id = end($parts);
        $movie = Movie::findOrFail($id);

        $showtime = Showtime::with(['movie', 'studio'])->find((int) $request->input('jadwal'));

        // Jadwal harus milik film di alamatnya, belum lewat, dan filmnya tidak diarsipkan.
        abort_if($showtime === null || $showtime->movie_id !== $movie->id || $showtime->show_time->isPast() || ! $movie->is_showing, 404);

        return $showtime;
    }

    // Nomor kursi yang sedang diambil pada jadwal ini: milik pesanan lunas dan pesanan yang masih dalam batas
    // waktu bayar. Kursi dari pesanan yang batal atau kedaluwarsa sudah dilepas dan bisa dipilih lagi.
    private function kursiDiambil(Showtime $showtime): array
    {
        return Booking::where('showtime_id', $showtime->id)
            ->where('status', '!=', 'cancelled')
            ->pluck('kursi')
            ->flatten()
            ->all();
    }

    // Nomor kursi yang sedang diambil, untuk ditandai terisi di denah.
    private function kursiTerisi(Showtime $showtime): array
    {
        $this->lepasKedaluwarsa($showtime);

        return $this->kursiDiambil($showtime);
    }

    // Kursi dari alamat dicocokkan dengan susunan kursi studio. Kursi yang tidak ada di studio,
    // atau lebih dari enam, membuat permintaan ditolak.
    private function ambilKursi(string $kursiInput, Showtime $showtime): array
    {
        $kursi = array_values(array_unique(array_filter(explode(',', $kursiInput))));

        abort_if(count($kursi) < 1 || count($kursi) > 6 || array_diff($kursi, $showtime->studio->daftarKursi()), 404);

        return $kursi;
    }

    public function pilihKursi(Request $request, string $slug)
    {
        $jadwal = $this->ambilJadwal($request, $slug);

        return view('kursi', [
            'film' => $jadwal->movie,
            'jadwal' => $jadwal,
            'studio' => $jadwal->studio,
            'layar' => $jadwal->studio->label(),
            'jam' => $jadwal->show_time->format('H:i'),
            'tanggal' => $jadwal->show_time->copy()->startOfDay(),
            'harga' => $jadwal->price,
            'jumlah' => max(1, min(6, (int) $request->query('jumlah', 1))),
            'kursiTerisi' => $this->kursiTerisi($jadwal),
        ]);
    }

    public function halamanBayar(Request $request, string $slug)
    {
        $jadwal = $this->ambilJadwal($request, $slug);

        return view('bayar', [
            'film' => $jadwal->movie,
            'jadwal' => $jadwal,
            'layar' => $jadwal->studio->label(),
            'jam' => $jadwal->show_time->format('H:i'),
            'tanggal' => $jadwal->show_time->copy()->startOfDay(),
            'harga' => $jadwal->price,
            'kursi' => $this->ambilKursi(is_string($request->query('kursi')) ? $request->query('kursi') : '', $jadwal),
        ]);
    }

    public function prosesBayar(Request $request, string $slug)
    {
        $showtime = $this->ambilJadwal($request, $slug);
        $this->lepasKedaluwarsa($showtime);
        $film = $showtime->movie;
        $metode = $request->input('metode');

        abort_unless(in_array($metode, ['qris', 'va', 'ewallet']), 404);

        $kursiArr = $this->ambilKursi(is_string($request->input('kursi')) ? $request->input('kursi') : '', $showtime);

        $biayaLayanan = 3000;
        $totalHargaPerKursi = $showtime->price + $biayaLayanan;
        $grossAmount = count($kursiArr) * $totalHargaPerKursi;

        // Buat booking_code unik, yang juga dipakai sebagai order_id di Midtrans. Kode diulang kalau kebetulan sudah dipakai.
        do {
            $bookingCode = strtoupper(Str::random(6));
        } while (Booking::where('booking_code', $bookingCode)->exists());

        $userId = \Illuminate\Support\Facades\Auth::id();

        // Pemeriksaan kursi dan penyimpanan pesanan dijalankan dalam satu transaksi yang mengunci
        // jadwal ini. Dua penonton yang memilih kursi sama pada saat bersamaan, atau satu penonton
        // yang menekan Bayar dua kali, diproses bergantian. Kalau satu kursi ternyata sudah diambil,
        // seluruh pesanan dibatalkan, jadi tidak ada kursi yang tertahan setengah.
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($showtime, $kursiArr, $userId, $bookingCode, $grossAmount, $metode) {
                Showtime::whereKey($showtime->id)->lockForUpdate()->first();

                // Hapus pesanan 'pending' sebelumnya milik pengguna ini untuk jadwal yang sama
                // (agar mereka bisa mencoba ulang simulasi tanpa terhalang error "kursi sudah dipesan")
                Booking::where('showtime_id', $showtime->id)
                    ->where('user_id', $userId)
                    ->where('status', 'pending')
                    ->delete();

                if ($diambil = array_intersect($kursiArr, $this->kursiDiambil($showtime))) {
                    throw new \DomainException(reset($diambil));
                }

                // Cara bayar dicatat sebelum Midtrans dipanggil, supaya tetap tersimpan walaupun Midtrans gagal.
                Booking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => $userId,
                    'showtime_id' => $showtime->id,
                    'kursi' => collect($kursiArr)->sort(SORT_NATURAL)->values()->all(),
                    'total_price' => $grossAmount, // Harga termasuk layanan
                    'status' => 'pending', // default
                    'payment_method' => $metode,
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', "Kursi {$e->getMessage()} baru saja dipesan orang lain. Pilih kursi lain.");
        }

        // Panggil Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id' => $bookingCode,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => \Illuminate\Support\Facades\Auth::user()->name,
                'email' => \Illuminate\Support\Facades\Auth::user()->email,
            ],
            'callbacks' => [
                'finish' => url('/tiket/' . $bookingCode)
            ]
        ];

        // Tanpa kunci Midtrans (misalnya di laptop pengembang), pesanan dianggap lunas supaya alurnya
        // tetap bisa dicoba sampai tiket. Begitu kunci dipasang, jalur ini tidak dipakai lagi.
        if (! $this->midtransSiap()) {
            Booking::where('booking_code', $bookingCode)->update(['status' => 'paid']);

            return redirect('/tiket/' . $bookingCode)->with('warning', 'Pembayaran belum tersambung ke Midtrans, jadi pesanan ini dianggap lunas tanpa ditagih. Tiket ini hanya untuk uji coba.');
        }

        $params['expiry'] = ['unit' => 'minutes', 'duration' => self::BATAS_BAYAR_MENIT];
        $params['enabled_payments'] = self::CARA_BAYAR_MIDTRANS[$metode];

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
        // memperlihatkan alurnya. Untuk pembayaran sungguhan, hapus 'status' => 'paid' di bawah:
        // pesanan akan menunggu sampai Midtrans menyatakan lunas lewat cekStatus() dan notifikasiMidtrans().
        Booking::where('booking_code', $bookingCode)->update(['snap_url' => $snapUrl, 'status' => 'paid']);

        return redirect()->away($snapUrl);
    }

    // Pemberitahuan pembayaran dari Midtrans. Alamat ini didaftarkan di dashboard Midtrans
    // (Settings, Payment Notification URL) begitu website bisa diakses dari internet.
    public function notifikasiMidtrans(Request $request)
    {
        $this->midtransSiap();
        $isi = $request->all();

        // Tanda tangan dicek supaya tidak ada yang bisa memalsukan pemberitahuan "sudah dibayar".
        $tandaTangan = hash('sha512', ($isi['order_id'] ?? '') . ($isi['status_code'] ?? '') . ($isi['gross_amount'] ?? '') . Config::$serverKey);
        abort_unless(hash_equals($tandaTangan, (string) ($isi['signature_key'] ?? '')), 403);

        // order_id di Midtrans sama dengan kode pesanan.
        $this->terapkanStatus((string) ($isi['order_id'] ?? ''), (string) ($isi['transaction_status'] ?? ''), $isi['fraud_status'] ?? null);

        return response()->json(['diterima' => true]);
    }

    public function tiketSaya(Request $request)
    {
        // Pesanan yang masih menunggu pembayaran ditanyakan dulu ke Midtrans, supaya statusnya terbaru.
        Booking::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->pluck('booking_code')
            ->each(fn ($kode) => $this->cekStatus($kode));

        $hariIni = now()->startOfDay();
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $pesanan = Booking::where('user_id', $request->user()->id)
            ->with(['showtime.movie.genres', 'showtime.movie.jadwalMendatang.studio', 'showtime.studio'])
            ->orderBy('id')
            ->get()
            ->map(function ($b) use ($hariIni, $namaHari, $namaBulan) {
                $waktu = $b->showtime->show_time;
                $durasi = $b->showtime->movie->duration_minutes ?? 120;
                $waktuSelesai = $waktu->copy()->addMinutes($durasi);

                $selisih = (int) $hariIni->diffInDays($waktu->copy()->startOfDay(), false);
                $dibatalkan = $b->status === 'cancelled';
                
                // Tiket baru kedaluwarsa setelah film selesai (waktu tayang + durasi)
                $lewat = $waktuSelesai->isPast();

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
                        $selisih < 0 => 'Sedang diputar',
                        $selisih === 0 => 'Hari ini',
                        $selisih === 1 => 'Besok',
                        default => $selisih . ' hari lagi',
                    },
                    'aktif' => ! $dibatalkan && ! $lewat,
                    // Film hanya bisa dinilai setelah benar-benar ditonton: sudah dibayar dan jamnya lewat.
                    'bisaDinilai' => $b->status === 'paid' && $lewat,
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

        // Pesanan harus milik akun ini, sudah dibayar, dan filmnya sudah selesai diputar
        // (jam tayang ditambah durasi film). Tanpa ini siapa pun bisa menilai film apa saja.
        $pesanan = Booking::with('showtime.movie')
            ->where('user_id', $request->user()->id)
            ->where('booking_code', strtoupper($data['kode']))
            ->where('status', 'paid')
            ->first();

        abort_unless($pesanan, 403);

        $film = $pesanan->showtime->movie;
        $selesai = $pesanan->showtime->show_time->copy()->addMinutes($film->duration_minutes ?: 120);

        abort_unless($selesai->isPast(), 403);

        // Satu penilaian per pesanan. Menilai ulang pesanan yang sama mengganti nilai lamanya,
        // sedangkan pesanan lain untuk film yang sama punya penilaiannya sendiri.
        $pesanan->update(['rating' => $data['nilai']]);

        $judul = $film->title;

        return redirect('/tiket-saya?tab=riwayat')
            ->with('sukses', 'Penilaianmu untuk "' . $judul . '" tersimpan: ' . $data['nilai'] . ' dari 5.');
    }

    public function halamanTiket(Request $request, string $booking_code)
    {
        // Kode tiket di alamat boleh ditulis huruf kecil, tapi kode batang Code 39 hanya menerima huruf besar.
        $booking_code = strtoupper($booking_code);

        // Penonton biasanya sampai di sini dari halaman Midtrans, jadi statusnya ditanyakan dulu.
        $this->cekStatus($booking_code);

        // 1. Ambil data transaksi dari database
        $pesanan = Booking::where('booking_code', $booking_code)->with(['showtime.movie', 'showtime.studio'])->first();
        
        if (! $pesanan) {
            abort(404, 'Tiket tidak ditemukan');
        }

        // Tiket hanya bisa dibuka pemesannya dan admin. Tanpa ini, siapa pun yang login
        // dan tahu kodenya bisa melihat tiket orang lain.
        abort_unless($pesanan->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        $film = $pesanan->showtime->movie;
        $tanggalCarbon = $pesanan->showtime->show_time;
        $jam = $tanggalCarbon->format('H:i');
        $layar = $pesanan->showtime->studio->label();
        
        $kursi = $pesanan->kursi;
        
        $akhirPekan = in_array($tanggalCarbon->dayOfWeek, [0, 5, 6]);

        $daftarMetode = ['qris' => 'QRIS', 'va' => 'Transfer Bank', 'ewallet' => 'Dompet Digital'];
        $namaMetode = $daftarMetode[$pesanan->payment_method ?? ''] ?? 'Midtrans';
        $total = $pesanan->total_price;
        $kode = $booking_code;

        // Kode batang hanya ditampilkan untuk tiket yang masih bisa dipakai masuk: sudah dibayar dan
        // filmnya belum selesai. Tiket lain tetap bisa dibuka, tapi tanpa kode yang bisa dipindai.
        $selesai = $tanggalCarbon->copy()->addMinutes($film->duration_minutes ?: 120)->isPast();

        // Pesanan yang belum dibayar bisa dilanjutkan di halaman Midtrans selama batas bayarnya belum lewat.
        $lanjutBayar = $pesanan->status === 'pending' && $pesanan->created_at->gt(now()->subMinutes(self::BATAS_BAYAR_MENIT))
            ? $pesanan->snap_url
            : null;
        $keadaan = match (true) {
            $pesanan->status === 'cancelled' => 'batal',
            $pesanan->status !== 'paid' => 'belum-bayar',
            $selesai => 'selesai',
            default => 'aktif',
        };

        // Generate Barcode menggunakan picqer/php-barcode-generator
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
        $batang = $generator->getBarcode($kode, $generator::TYPE_CODE_39, 2, 64, 'black');

        // Lempar ke view tiket.blade.php
        return view('tiket', compact('film', 'tanggalCarbon', 'layar', 'jam', 'kursi', 'akhirPekan', 'namaMetode', 'total', 'kode', 'batang', 'keadaan', 'lanjutBayar'));
    }
}