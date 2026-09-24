<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Illuminate\Database\Seeder;

// Jadwal uji untuk enam hari ke depan: setiap film yang sedang tayang punya format sendiri
// (ada yang 2D saja, ada yang 2D, 3D, dan IMAX), dan tiap format dapat 3 tayangan per hari,
// tanpa ada studio yang bertabrakan.
// Jalankan sendiri dengan: php artisan db:seed --class=JadwalContohSeeder
// Tidak dipanggil DatabaseSeeder, karena jadwal asli seharusnya dibuat admin.
class JadwalContohSeeder extends Seeder
{
    // Jumlah studio per format. Satu studio muat sekitar enam tayangan sehari, jadi 19 film
    // dengan 3 tayangan per format butuh belasan studio, seperti bioskop besar sungguhan.
    private const STUDIO_PER_FORMAT = ['Regular 2D' => 8, 'Regular 3D' => 4, 'IMAX' => 4];

    // Jumlah tayangan per format per hari untuk setiap film.
    private const TAYANG_PER_FORMAT = 3;

    // Tidak semua film tayang di semua format. Pola ini dibagikan bergiliran ke tiap film.
    private const POLA_FORMAT = [
        ['Regular 2D'],
        ['Regular 2D', 'Regular 3D'],
        ['Regular 2D', 'IMAX'],
        ['Regular 3D'],
        ['Regular 2D'],
        ['Regular 2D', 'Regular 3D', 'IMAX'],
        ['Regular 2D'],
        ['IMAX'],
    ];

    public function run(): void
    {
        $film = Movie::where('is_showing', true)->orderBy('id')->get();

        if ($film->isEmpty()) {
            $this->command->error('Belum ada film yang sedang tayang.');

            return;
        }

        $studioBaru = $this->lengkapiStudio();

        // Jadwal mendatang yang belum dipesan siapa pun dibuat ulang. Yang sudah dipesan dibiarkan,
        // supaya tiket penonton tetap sah; jadwal baru akan menghindarinya.
        $dihapus = Showtime::where('show_time', '>=', now())->doesntHave('bookings')->delete();

        $studio = Studio::orderBy('id')->get()->groupBy('format');
        $dibuat = 0;
        $tidakMuat = 0;

        for ($hari = 0; $hari < 6; $hari++) {
            $tanggal = now()->startOfDay()->addDays($hari);

            // Waktu kosong berikutnya di tiap studio. Studio dimulai bergantian 10:00, 10:20, 10:40
            // supaya jam tayang tidak serempak semua. Untuk hari ini, jam yang sudah lewat dilompati
            // supaya sisa hari tetap terisi, tidak kosong karena jatahnya habis di pagi hari.
            $sekarang = now()->addMinutes(10 - now()->minute % 10)->startOfMinute();
            $kosong = [];
            foreach (Studio::orderBy('id')->get() as $i => $s) {
                $kosong[$s->id] = $tanggal->copy()->setTime(10, ($i % 3) * 20)->max($sekarang);
            }

            // Tiap putaran, setiap format dari setiap film dapat satu tayangan, bergiliran. Dengan begitu
            // tiga tayangan satu format tersebar dari siang sampai malam, tidak menumpuk di jam yang sama.
            for ($putaran = 0; $putaran < self::TAYANG_PER_FORMAT; $putaran++) {
                foreach ($film as $urut => $f) {
                    // Film yang belum rilis baru diberi jadwal mulai hari rilisnya.
                    if ($f->release_date && $f->release_date > $tanggal->format('Y-m-d')) {
                        continue;
                    }

                    foreach (self::POLA_FORMAT[$urut % count(self::POLA_FORMAT)] as $format) {
                        $dipakai = $studio->get($format, collect())
                            ->sortBy(fn ($s) => [$kosong[$s->id]->timestamp, $s->id])
                            ->first();

                        if (! $dipakai) {
                            continue;
                        }

                        $mulai = $kosong[$dipakai->id]->copy();
                        $menit = ($f->duration_minutes ?: 120) + Showtime::JEDA_MENIT;
                        $kosong[$dipakai->id] = $mulai->copy()->addMinutes((int) ceil($menit / 10) * 10);

                        // Tayangan terakhir paling lambat mulai 21:45.
                        if ($mulai->gt($tanggal->copy()->setTime(21, 45))) {
                            $tidakMuat++;
                            continue;
                        }

                        if ($mulai->isPast() || Showtime::bentrokDengan($dipakai->id, $f->id, $mulai)) {
                            continue;
                        }

                        Showtime::create([
                            'movie_id' => $f->id,
                            'studio_id' => $dipakai->id,
                            'show_time' => $mulai,
                            'price' => $dipakai->hargaUntuk($mulai),
                        ]);
                        $dibuat++;
                    }
                }
            }
        }

        $this->command->info("SELESAI: {$studioBaru} studio ditambahkan, {$dihapus} jadwal lama dibuat ulang, {$dibuat} jadwal dibuat.");

        if ($tidakMuat) {
            $this->command->warn("{$tidakMuat} tayangan tidak muat karena studionya sudah penuh sampai malam. Tambah studio kalau perlu.");
        }
    }

    // Menambah studio sampai jumlah per format terpenuhi, masing-masing 8 baris kali 10 kursi.
    private function lengkapiStudio(): int
    {
        $ditambah = 0;

        foreach (self::STUDIO_PER_FORMAT as $format => $jumlah) {
            for ($ada = Studio::where('format', $format)->count(); $ada < $jumlah; $ada++) {
                Studio::create([
                    'name' => 'Studio ' . (Studio::count() + 1),
                    'format' => $format,
                    'capacity' => 80,
                    'baris' => 8,
                    'kursi_per_baris' => 10,
                    'harga_biasa' => $format === 'IMAX' ? 75000 : ($format === 'Regular 3D' ? 55000 : 45000),
                    'harga_akhir_pekan' => $format === 'IMAX' ? 90000 : ($format === 'Regular 3D' ? 65000 : 55000),
                ]);

                $ditambah++;
            }
        }

        return $ditambah;
    }
}
