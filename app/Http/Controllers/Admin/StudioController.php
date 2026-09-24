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
        $data = $this->aturan($request, $terkunci, $studio);

        $studio->update([
            'name' => $data['name'],
            'harga_biasa' => $data['harga_biasa'],
            'harga_akhir_pekan' => $data['harga_akhir_pekan'],
        ]);

        if (! $terkunci) {
            $studio->update([
                'format' => $data['format'],
                'baris' => $data['baris'],
                'kursi_per_baris' => $data['per_baris'],
            ]);
        }

        return redirect('/admin/studio')->with(
            'sukses',
            'Studio "' . $studio->name . '" disimpan.'
                . ($terkunci ? ' Format dan susunan kursinya dibiarkan karena ada jadwal mendatang yang sudah dipesan.' : '')
        );
    }

    public function destroy(Studio $studio)
    {
        // Menghapus studio ikut menghapus jadwal dan pesanannya (cascadeOnDelete), termasuk riwayat
        // tiket penonton. Jadi hanya studio yang belum pernah punya jadwal yang boleh dihapus.
        if ($studio->showtimes()->exists()) {
            return redirect('/admin/studio')->with(
                'gagal',
                'Studio "' . $studio->name . '" sudah punya jadwal tayang, jadi tidak dihapus supaya riwayat '
                    . 'tiketnya tidak ikut hilang.'
            );
        }

        $nama = $studio->name;
        $studio->delete();

        return redirect('/admin/studio')->with('sukses', 'Studio "' . $nama . '" dihapus.');
    }

    // Saat studio terkunci, format dan ukuran ruangnya tidak ikut diperiksa karena isiannya
    // dimatikan di halaman sehingga tidak terkirim.
    private function aturan(Request $request, bool $terkunci = false, ?Studio $studio = null): array
    {
        $aturan = [
            // Nama studio tampil di tiket, jadi tidak boleh kembar.
            'name' => ['required', 'string', 'max:255', Rule::unique('studios', 'name')->ignore($studio)],
            'harga_biasa' => ['required', 'integer', 'min:0', 'max:1000000'],
            'harga_akhir_pekan' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];

        if (! $terkunci) {
            $aturan['format'] = ['required', Rule::in(Studio::FORMAT)];
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

    // Studio yang punya jadwal mendatang dengan pesanan aktif tidak boleh diubah format dan susunan
    // kursinya, karena penonton sudah membeli tiket untuk format dan nomor kursi itu. Pesanan yang
    // sudah lewat tidak mengunci, karena nomor kursinya tersimpan sebagai teks di pesanan.
    private function terkunci(Studio $studio): bool
    {
        return Booking::where('status', '!=', Booking::BATAL)
            ->whereHas('showtime', fn ($q) => $q->where('studio_id', $studio->id)->where('show_time', '>=', now()))
            ->exists();
    }
}
