<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudioController extends Controller
{
    public function index()
    {
        return view('admin.studio.index', [
            'studio' => Studio::withCount('showtimes')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.studio.form', [
            'studio' => new Studio(),
            'baris' => 8,
            'perBaris' => 10,
            'terkunci' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->aturan($request);

        $studio = Studio::create([
            'name' => $data['name'],
            'format' => $data['format'],
            'baris' => $data['baris'],
            'kursi_per_baris' => $data['per_baris'],
            'harga_biasa' => $data['harga_biasa'],
            'harga_akhir_pekan' => $data['harga_akhir_pekan'],
        ]);

        return redirect('/admin/studio')->with(
            'sukses',
            'Studio "' . $studio->name . '" dibuat dengan ' . $studio->kapasitas() . ' kursi.'
        );
    }

    public function edit(Studio $studio)
    {
        return view('admin.studio.form', [
            'studio' => $studio,
            'baris' => $studio->baris,
            'perBaris' => $studio->kursi_per_baris,
            'terkunci' => $this->terkunci($studio),
        ]);
    }

    public function update(Request $request, Studio $studio)
    {
        $terkunci = $this->terkunci($studio);
        $data = $this->aturan($request, $terkunci);

        $studio->update([
            'name' => $data['name'],
            'format' => $data['format'],
            'harga_biasa' => $data['harga_biasa'],
            'harga_akhir_pekan' => $data['harga_akhir_pekan'],
        ]);

        if (! $terkunci) {
            $studio->update([
                'baris' => $data['baris'],
                'kursi_per_baris' => $data['per_baris'],
            ]);
        }

        return redirect('/admin/studio')->with(
            'sukses',
            'Studio "' . $studio->name . '" disimpan.'
                . ($terkunci ? ' Susunan kursinya dibiarkan karena sudah ada pesanan.' : '')
        );
    }

    public function destroy(Studio $studio)
    {
        if ($studio->showtimes()->exists()) {
            return redirect('/admin/studio')->with(
                'gagal',
                'Studio "' . $studio->name . '" masih dipakai jadwal tayang, jadi tidak dihapus. '
                    . 'Hapus jadwalnya dulu.'
            );
        }

        $nama = $studio->name;
        $studio->delete();

        return redirect('/admin/studio')->with('sukses', 'Studio "' . $nama . '" dihapus.');
    }

    // Saat studio terkunci, ukuran ruangnya tidak ikut diperiksa karena isiannya
    // dimatikan di halaman sehingga tidak terkirim.
    private function aturan(Request $request, bool $terkunci = false): array
    {
        $aturan = [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::in(Studio::FORMAT)],
            'harga_biasa' => ['required', 'integer', 'min:0', 'max:1000000'],
            'harga_akhir_pekan' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];

        if (! $terkunci) {
            $aturan['baris'] = ['required', 'integer', 'min:1', 'max:26'];
            $aturan['per_baris'] = ['required', 'integer', 'min:1', 'max:30'];
        }

        return $request->validate($aturan, [], [
            'name' => 'nama studio',
            'harga_biasa' => 'harga hari biasa',
            'harga_akhir_pekan' => 'harga akhir pekan',
            'baris' => 'jumlah baris',
            'per_baris' => 'kursi per baris',
        ]);
    }

    // Studio yang kursinya sudah dipesan tidak boleh diubah susunannya, karena
    // nomor kursi di pesanan itu bisa hilang dari denah yang baru.
    private function terkunci(Studio $studio): bool
    {
        return Booking::whereHas('showtime', fn ($q) => $q->where('studio_id', $studio->id))->exists();
    }
}
