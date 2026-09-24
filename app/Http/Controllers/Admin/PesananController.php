<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;

class PesananController extends Controller
{
    public function index()
    {
        return view('admin.pesanan.index', [
            'pesanan' => Booking::with(['user', 'showtime.movie', 'showtime.studio'])
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }
}
