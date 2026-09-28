<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Loket bioskop: petugas kasir menjual tiket kepada penonton yang datang langsung dan membayar tunai.
// Penjualan loket disimpan di tabel bookings seperti pesanan online, atas nama akun kasir yang
// melayani, dengan cara bayar 'tunai'. Denah kursinya sama, jadi kursi yang terjual di loket langsung
// tampil terisi di website, begitu juga sebaliknya.
class KasirController extends Controller
{
    // Jadwal yang masih dijual pada tanggal yang dipilih, dikelompokkan per film, ditambah ringkasan
    // penjualan loket kasir ini hari ini.
    public function index(Request $request)
    {
        $daftarTanggal = collect(range(0, Showtime::HARI_DIJUAL - 1))->map(fn ($i) => today()->addDays($i));
        $tanggal = $daftarTanggal->first(fn ($t) => $t->format('Y-m-d') === $request->query('tanggal')) ?? today();

        // Hanya jam yang belum mulai, karena penjualan ditutup saat jam tayang tiba.
        $rentang = [$tanggal->copy()->max(now()), $tanggal->copy()->endOfDay()];

        $film = Movie::where('is_showing', true)
            ->whereHas('showtimes', fn ($q) => $q->whereBetween('show_time', $rentang))
            ->with(['showtimes' => fn ($q) => $q->whereBetween('show_time', $rentang)->orderBy('show_time')
                ->with(['studio', 'bookings' => fn ($q) => $q->where('status', '!=', Booking::BATAL)])])
            ->orderBy('title')
            ->get();

        $penjualan = Booking::with(['showtime.movie', 'showtime.studio'])
            ->where('user_id', $request->user()->id)
            ->where('payment_method', 'tunai')
            ->whereDate('created_at', today())
            ->orderByDesc('id')
            ->get();

        return view('kasir.index', [
            'tanggal' => $tanggal,
            'daftarTanggal' => $daftarTanggal,
            'film' => $film,
            'penjualan' => $penjualan,
            'pendapatan' => $penjualan->sum('total_price'),
            'tiketTerjual' => $penjualan->sum(fn ($b) => count($b->kursi)),
        ]);
    }

    public function kursi(Showtime $showtime, MidtransService $midtrans)
    {
        if ($alasan = $this->tidakDijual($showtime)) {
            return redirect('/kasir')->with('error', $alasan);
        }

        // Kursi dari pesanan online yang lewat batas bayar dilepas dulu, supaya bisa dijual di loket.
        $midtrans->lepasKedaluwarsa($showtime);

        return view('kasir.kursi', [
            'jadwal' => $showtime,
            'studio' => $showtime->studio,
            'film' => $showtime->movie,
            'harga' => $showtime->harga(),
            'kursiTerisi' => $showtime->kursiTerisi(),
        ]);
    }

    public function jual(Request $request, Showtime $showtime, MidtransService $midtrans)
    {
        if ($alasan = $this->tidakDijual($showtime)) {
            return redirect('/kasir')->with('error', $alasan);
        }

        $midtrans->lepasKedaluwarsa($showtime);

        $data = $request->validate([
            'kursi' => ['required', 'string', 'max:200'],
            'uang_diterima' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [
            'kursi.required' => 'Pilih minimal satu kursi.',
        ], [
            'uang_diterima' => 'uang diterima',
        ]);

        $kursi = array_values(array_unique(array_filter(explode(',', $data['kursi']))));

        if (count($kursi) > Booking::MAKS_KURSI_LOKET || array_diff($kursi, $showtime->studio->daftarKursi())) {
            return back()->withInput()->with('error', 'Pilihan kursi tidak valid. Paling banyak ' . Booking::MAKS_KURSI_LOKET . ' kursi sekali transaksi.');
        }

        // Di loket tidak ada biaya layanan. Biaya itu hanya untuk pembelian online.
        $total = count($kursi) * $showtime->harga();

        if ($data['uang_diterima'] < $total) {
            return back()->withInput()->with('error', 'Uang yang diterima kurang dari total Rp ' . number_format($total, 0, ',', '.') . '.');
        }

        do {
            $kode = strtoupper(Str::random(6));
        } while (Booking::where('booking_code', $kode)->exists());

        // Sama seperti pemesanan online: jadwal dikunci selama kursi diperiksa dan disimpan, jadi
        // kasir dan penonton online yang memilih kursi sama pada saat bersamaan diproses bergantian.
        try {
            DB::transaction(function () use ($showtime, $kursi, $kode, $total, $request) {
                Showtime::whereKey($showtime->id)->lockForUpdate()->first();

                if ($diambil = array_intersect($kursi, $showtime->kursiTerisi())) {
                    throw new \DomainException(implode(', ', $diambil));
                }

                Booking::create([
                    'booking_code' => $kode,
                    'user_id' => $request->user()->id,
                    'showtime_id' => $showtime->id,
                    'kursi' => collect($kursi)->sort(SORT_NATURAL)->values()->all(),
                    'total_price' => $total,
                    'status' => Booking::LUNAS,
                    'payment_method' => 'tunai',
                ]);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', 'Kursi ' . $e->getMessage() . ' baru saja terjual. Pilih kursi lain.');
        }

        // Tiketnya langsung dibuka untuk dicetak, beserta uang diterima dan kembaliannya.
        return redirect('/tiket/' . $kode)
            ->with('uang_diterima', $data['uang_diterima'])
            ->with('kembalian', $data['uang_diterima'] - $total);
    }

    // Alasan jadwal ini tidak bisa dijual lagi, atau null kalau masih bisa.
    private function tidakDijual(Showtime $showtime): ?string
    {
        return match (true) {
            ! $showtime->movie->is_showing => 'Film "' . $showtime->movie->title . '" sudah diarsipkan dan tidak dijual.',
            ! $showtime->masihDijual() => 'Penjualan jam ' . $showtime->show_time->format('H:i') . ' sudah ditutup karena filmnya sudah mulai.',
            default => null,
        };
    }
}
