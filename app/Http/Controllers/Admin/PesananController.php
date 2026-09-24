<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class PesananController extends Controller
{
    public function index(Request $request)
    {
        $cari = is_string($request->query('cari')) ? trim($request->query('cari')) : '';
        $status = in_array($request->query('status'), array_keys(Booking::NAMA_STATUS), true) ? $request->query('status') : 'semua';

        // Ringkasan penjualan hari ini, dari pesanan lunas yang dibuat hari ini.
        $lunasHariIni = Booking::where('status', Booking::LUNAS)->whereDate('created_at', today())->get();

        return view('admin.pesanan.index', [
            // Petugas biasanya mencari pesanan dari kode di tiket penonton, atau dari nama dan email pemesan.
            'pesanan' => Booking::with(['user', 'showtime.movie', 'showtime.studio'])
                ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
                ->when($cari !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('booking_code', strtoupper($cari))
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%' . $cari . '%')->orWhere('email', 'like', '%' . $cari . '%'))))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'status' => $status,
            'pendapatanHariIni' => $lunasHariIni->sum('total_price'),
            'tiketHariIni' => $lunasHariIni->sum(fn ($b) => count($b->kursi)),
            'pesananHariIni' => $lunasHariIni->count(),
        ]);
    }
}
