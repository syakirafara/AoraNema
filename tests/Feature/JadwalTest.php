<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\CinemaSeeder;
use Database\Seeders\JadwalSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

// Aturan jadwal tayang: satu studio tidak boleh memutar dua film yang waktunya bertabrakan,
// jadwal harus di dalam jam operasional, tidak sebelum tanggal rilis, dan tidak lebih jauh
// dari hari penjualan tiket.
class JadwalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Semua tes berjalan pada Senin pagi, supaya hasilnya tidak bergantung pada jam saat tes dijalankan.
        $this->travelTo('2026-10-05 08:00:00');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function film(array $isi = []): Movie
    {
        return Movie::create($isi + ['title' => 'Film Uji', 'duration_minutes' => 120, 'release_date' => '2026-09-01']);
    }

    private function studio(string $nama = 'Studio 1'): Studio
    {
        return Studio::create(['name' => $nama, 'baris' => 5, 'kursi_per_baris' => 10]);
    }

    private function tambahJadwal(Movie $film, Studio $studio, string $jam, string $tanggal = '2026-10-05')
    {
        return $this->post('/admin/jadwal', [
            'movie_id' => $film->id,
            'studio_id' => $studio->id,
            'tanggal_mulai' => $tanggal,
            'jam' => [$jam],
        ]);
    }

    public function test_studio_tidak_bisa_memutar_dua_film_yang_waktunya_bertabrakan(): void
    {
        $studio = $this->studio();
        $filmA = $this->film(['title' => 'Film A', 'duration_minutes' => 120]);
        $filmB = $this->film(['title' => 'Film B', 'duration_minutes' => 90]);

        // Film A memakai studio dari 13:00 sampai 15:25: 10 menit iklan, 120 menit film, 15 menit jeda.
        $this->tambahJadwal($filmA, $studio, '13:00');
        $this->assertSame(1, Showtime::count());

        // Film B di jam 15:00 masih bertabrakan, begitu juga Film B di jam 11:30 yang baru selesai 13:25.
        $this->tambahJadwal($filmB, $studio, '15:00')->assertSessionHas('gagal');
        $this->tambahJadwal($filmB, $studio, '11:30')->assertSessionHas('gagal');
        $this->assertSame(1, Showtime::count());

        // Tepat saat studio siap lagi boleh.
        $this->tambahJadwal($filmB, $studio, '15:25');
        $this->assertSame(2, Showtime::count());
    }

    public function test_film_yang_sama_juga_tidak_boleh_bertumpuk_di_satu_studio(): void
    {
        $studio = $this->studio();
        $film = $this->film();

        // Dua jam dalam satu kiriman: jam kedua bertabrakan dengan jam pertama, jadi dilewati.
        $this->post('/admin/jadwal', [
            'movie_id' => $film->id,
            'studio_id' => $studio->id,
            'tanggal_mulai' => '2026-10-05',
            'jam' => ['13:00', '14:00'],
        ])->assertSessionHas('gagal');

        $this->assertSame(['13:00'], Showtime::pluck('show_time')->map->format('H:i')->all());
    }

    public function test_film_yang_sama_boleh_diputar_bersamaan_di_studio_lain(): void
    {
        $film = $this->film();

        $this->tambahJadwal($film, $this->studio('Studio 1'), '13:00');
        $this->tambahJadwal($film, $this->studio('Studio 2'), '13:00');

        $this->assertSame(2, Showtime::count());
    }

    public function test_jam_tayang_harus_di_dalam_jam_operasional(): void
    {
        $studio = $this->studio();
        $film = $this->film();

        $this->tambahJadwal($film, $studio, '09:00')->assertSessionHasErrors('jam.0');
        $this->tambahJadwal($film, $studio, '22:30')->assertSessionHasErrors('jam.0');
        $this->assertSame(0, Showtime::count());

        $this->tambahJadwal($film, $studio, Showtime::JAM_TERAKHIR);
        $this->assertSame(1, Showtime::count());
    }

    public function test_jadwal_tidak_boleh_sebelum_tanggal_rilis_film(): void
    {
        $studio = $this->studio();
        $film = $this->film(['release_date' => '2026-10-08']);

        $this->tambahJadwal($film, $studio, '13:00', '2026-10-07')->assertSessionHas('gagal');
        $this->tambahJadwal($film, $studio, '13:00', '2026-10-08');

        $this->assertSame(['2026-10-08'], Showtime::pluck('show_time')->map->format('Y-m-d')->all());
    }

    public function test_jadwal_hanya_sampai_hari_terakhir_penjualan(): void
    {
        $studio = $this->studio();
        $film = $this->film();

        // Tiket dijual 7 hari: Senin 5 Oktober sampai Minggu 11 Oktober.
        $this->tambahJadwal($film, $studio, '13:00', '2026-10-12')->assertSessionHasErrors('tanggal_mulai');
        $this->tambahJadwal($film, $studio, '13:00', '2026-10-11');

        $this->assertSame(1, Showtime::count());
    }

    public function test_film_tanpa_durasi_tidak_bisa_dijadwalkan(): void
    {
        $film = $this->film(['duration_minutes' => null]);

        $this->tambahJadwal($film, $this->studio(), '13:00')->assertSessionHas('gagal');

        $this->assertSame(0, Showtime::count());
    }

    public function test_durasi_yang_membuat_jadwal_bertabrakan_tidak_disimpan(): void
    {
        $studio = $this->studio();
        $filmA = $this->film(['title' => 'Film A', 'duration_minutes' => 100]);
        $filmB = $this->film(['title' => 'Film B']);

        // Film A 13:00 dengan durasi 100 menit selesai memakai studio 15:05, lalu Film B mulai 15:05.
        $this->tambahJadwal($filmA, $studio, '13:00');
        $this->tambahJadwal($filmB, $studio, '15:05');
        $this->assertSame(2, Showtime::count());

        // Durasi Film A dibetulkan jadi 150 menit, yang akan menabrak Film B. Perubahannya ditolak.
        $this->put('/admin/film/' . $filmA->id, [
            'title' => 'Film A',
            'duration_minutes' => 150,
            'release_date' => '2026-09-01',
            'is_showing' => '1',
        ])->assertSessionHas('gagal');

        $this->assertSame(100, $filmA->fresh()->duration_minutes);

        // Tetap ditolak walaupun Film A sedang diputar, karena tayangan itu masih memakai studionya.
        $this->travelTo('2026-10-05 13:30:00');
        $this->put('/admin/film/' . $filmA->id, [
            'title' => 'Film A',
            'duration_minutes' => 150,
            'release_date' => '2026-09-01',
            'is_showing' => '1',
        ])->assertSessionHas('gagal');

        $this->assertSame(100, $filmA->fresh()->duration_minutes);
    }

    public function test_film_yang_diarsipkan_bisa_ditayangkan_lagi_bersama_jadwalnya(): void
    {
        $film = $this->film(['title' => 'Film Arsip']);
        $jadwal = Showtime::create(['movie_id' => $film->id, 'studio_id' => $this->studio()->id, 'show_time' => '2026-10-05 13:00']);
        $alamatKursi = '/kursi/' . $film->slug() . '?jadwal=' . $jadwal->id;

        // Diarsipkan: film hilang dari beranda dan tiketnya tidak dijual, tapi jadwalnya tetap ada.
        $this->post('/admin/film/' . $film->id . '/arsip');
        $this->assertFalse($film->fresh()->is_showing);
        $this->assertSame(1, Showtime::count());
        $this->get('/')->assertDontSee('Film Arsip');
        $this->actingAs(User::factory()->create())->get($alamatKursi)->assertNotFound();

        // Ditayangkan lagi: film dan jadwalnya langsung muncul kembali.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/film/' . $film->id . '/arsip');
        $this->assertTrue($film->fresh()->is_showing);
        $this->get('/')->assertSee('Film Arsip');
        $this->actingAs(User::factory()->create())->get($alamatKursi)->assertOk();
    }

    public function test_jadwal_yang_sudah_dipesan_tidak_bisa_diubah_atau_dihapus(): void
    {
        $film = $this->film();
        $jadwal = Showtime::create(['movie_id' => $film->id, 'studio_id' => $this->studio()->id, 'show_time' => '2026-10-05 13:00']);

        Booking::create([
            'booking_code' => 'ABC123', 'user_id' => User::factory()->create()->id, 'showtime_id' => $jadwal->id,
            'kursi' => ['A1'], 'total_price' => 48000, 'status' => Booking::LUNAS,
        ]);

        $this->delete('/admin/jadwal/' . $jadwal->id)->assertSessionHas('gagal');
        $this->put('/admin/jadwal/' . $jadwal->id, [
            'movie_id' => $film->id, 'studio_id' => $jadwal->studio_id, 'show_time' => '2026-10-05T16:00',
        ])->assertSessionHas('gagal');

        $this->assertSame('13:00', $jadwal->fresh()->show_time->format('H:i'));
    }

    public function test_seeder_jadwal_tidak_pernah_membuat_jadwal_bertabrakan(): void
    {
        $this->seed(CinemaSeeder::class);

        foreach ([95, 120, 135, 150, 170, 105, 128, 142, 88, 160] as $i => $durasi) {
            $this->film(['title' => 'Film ' . ($i + 1), 'duration_minutes' => $durasi]);
        }

        $this->seed(JadwalSeeder::class);

        $this->assertGreaterThan(0, Showtime::count());

        foreach (Showtime::with('movie')->orderBy('show_time')->get()->groupBy('studio_id') as $jadwalStudio) {
            $sebelumnya = null;

            foreach ($jadwalStudio as $j) {
                $this->assertTrue(Showtime::dalamJamBuka($j->show_time), 'Di luar jam operasional: ' . $j->show_time);
                $this->assertTrue($j->show_time->lte(Showtime::tanggalTerakhir()->endOfDay()));

                if ($sebelumnya) {
                    $this->assertTrue(
                        $j->show_time->gte($sebelumnya->studioSiap()),
                        "Studio {$j->studio_id} bentrok: {$sebelumnya->show_time} dan {$j->show_time}"
                    );
                }

                $sebelumnya = $j;
            }
        }
    }

    public function test_seeding_tetap_membuat_akun_dan_studio_walaupun_tmdb_tidak_bisa_dihubungi(): void
    {
        config(['services.tmdb.key' => 'kunci-uji']);
        Http::fake(['*' => Http::failedConnection()]);
        Sleep::fake();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, User::whereIn('email', ['admin@aoranema.com', 'kasir@aoranema.com', 'user@aoranema.com'])->count());
        $this->assertSame(8, Studio::count());
        $this->assertSame(0, Movie::count());
    }
}
