<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

// Jadwal tayang untuk hari-hari yang dijual (hari ini dan enam hari ke depan), disusun seperti
// bioskop sungguhan:
// - satu studio hanya memutar satu film dalam satu waktu,
// - setiap tayangan memakai studio selama iklan, film, dan jeda bersih-bersih,
// - tayangan pertama mulai 10:00 dan tayangan terakhir paling lambat 22:00,
// - film yang belum rilis baru dijadwalkan mulai tanggal rilisnya.
// Dipanggil DatabaseSeeder. Bisa dijalankan lagi kapan saja untuk menyusun ulang jadwal:
// php artisan db:seed --class=JadwalSeeder
// Jadwal mendatang yang belum dipesan akan dihapus dan disusun ulang; yang sudah dipesan dibiarkan.
class JadwalSeeder extends Seeder
{
    // Paling banyak empat tayangan per format per film dalam sehari, selama studionya masih muat.
    private const TAYANG_PER_FORMAT = 4;

    // Tidak semua film tayang di semua format. Pola ini dibagikan bergiliran ke tiap film.
    private const POLA_FORMAT = [
        ['Regular 2D', 'IMAX'],
        ['Regular 2D'],
        ['Regular 2D', 'Regular 3D'],
        ['Regular 2D'],
        ['Regular 2D'],
        ['IMAX'],
        ['Regular 2D'],
        ['Regular 3D'],
    ];

    public function run(): void
    {
        // Film tanpa durasi tidak dijadwalkan, karena tidak bisa dihitung kapan studionya kosong lagi.
        $film = Movie::where('is_showing', true)->whereNotNull('duration_minutes')->orderBy('id')->get();
        $studio = Studio::orderBy('id')->get();

        if ($film->isEmpty() || $studio->isEmpty()) {
            $this->command->error('Butuh minimal satu film yang sedang tayang dan satu studio.');

            return;
        }

        // Jadwal mendatang yang belum dipesan siapa pun dibuat ulang. Yang sudah dipesan dibiarkan,
        // supaya tiket penonton tetap sah; jadwal baru akan menghindarinya.
        $dihapus = Showtime::where('show_time', '>=', now())
            ->whereDoesntHave('bookings', fn ($q) => $q->where('status', '!=', Booking::BATAL))
            ->delete();

        $perFormat = $studio->groupBy('format');
        $dibuat = 0;
        $tidakMuat = 0;

        for ($hari = 0; $hari < Showtime::HARI_DIJUAL; $hari++) {
            $tanggal = today()->addDays($hari);
            $jamTerakhir = $this->jam($tanggal, Showtime::JAM_TERAKHIR);

            // Kalau seeder dijalankan larut malam, hari ini sudah tidak bisa diberi tayangan lagi.
            if ($jamTerakhir->isPast()) {
                continue;
            }

            // Waktu kosong berikutnya di tiap studio. Studio dibuka bergantian tiap 15 menit supaya
            // jam tayangnya tidak serempak. Untuk hari ini, jam yang sudah lewat dilompati.
            $kosong = [];
            foreach ($studio as $i => $s) {
                $kosong[$s->id] = $this->bulatkan(
                    $this->jam($tanggal, Showtime::JAM_BUKA)->addMinutes(($i % 4) * 15)->max(now()->addMinutes(5))
                );
            }

            // Tiap putaran, setiap format dari setiap film dapat satu tayangan, bergiliran. Dengan begitu
            // semua film kebagian tayangan dulu sebelum film yang sama dapat tayangan kedua.
            for ($putaran = 0; $putaran < self::TAYANG_PER_FORMAT; $putaran++) {
                foreach ($film as $urut => $f) {
                    if ($f->release_date && $f->release_date > $tanggal->format('Y-m-d')) {
                        continue;
                    }

                    foreach (self::POLA_FORMAT[$urut % count(self::POLA_FORMAT)] as $format) {
                        // Studio berformat itu yang paling cepat kosong.
                        $dipakai = $perFormat->get($format, collect())
                            ->sortBy(fn ($s) => [$kosong[$s->id]->timestamp, $s->id])
                            ->first();

                        if (! $dipakai) {
                            continue;
                        }

                        $mulai = $kosong[$dipakai->id]->copy();

                        // Jadwal lama yang sudah dipesan dilompati: mulai lagi setelah studionya siap.
                        while ($mulai->lte($jamTerakhir) && ($bentrok = Showtime::bentrokDengan($dipakai->id, $f, $mulai))) {
                            $mulai = $this->bulatkan($bentrok->studioSiap());
                        }

                        if ($mulai->gt($jamTerakhir)) {
                            $tidakMuat++;

                            continue;
                        }

                        $jadwal = Showtime::create([
                            'movie_id' => $f->id,
                            'studio_id' => $dipakai->id,
                            'show_time' => $mulai,
                        ]);
                        $jadwal->setRelation('movie', $f);

                        $kosong[$dipakai->id] = $this->bulatkan($jadwal->studioSiap());
                        $dibuat++;
                    }
                }
            }
        }

        $this->command->info("SELESAI: {$dihapus} jadwal lama disusun ulang, {$dibuat} jadwal dibuat untuk " . Showtime::HARI_DIJUAL . ' hari.');

        if ($tidakMuat) {
            $this->command->info("{$tidakMuat} tayangan tambahan tidak dibuat karena semua studio sudah terisi sampai malam. Ini wajar kalau filmnya banyak.");
        }
    }

    // Tanggal itu pada jam tertentu, misalnya 10:00.
    private function jam(Carbon $tanggal, string $jam): Carbon
    {
        return $tanggal->copy()->setTimeFromTimeString($jam);
    }

    // Jam tayang dibulatkan ke atas ke kelipatan lima menit, misalnya 12:37 menjadi 12:40.
    private function bulatkan(Carbon $waktu): Carbon
    {
        $waktu = $waktu->copy()->startOfMinute();
        $lebih = $waktu->minute % 5;

        return $lebih ? $waktu->addMinutes(5 - $lebih) : $waktu;
    }
}
