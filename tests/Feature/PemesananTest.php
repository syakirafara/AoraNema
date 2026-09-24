<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Aturan pemesanan tiket: satu kursi hanya untuk satu pesanan, harga dihitung di server,
// penjualan ditutup saat jam tayang, dan tiket hanya bisa dibuka pemesannya.
// Kunci Midtrans dikosongkan di phpunit.xml, jadi pesanan langsung lunas tanpa memanggil Midtrans.
class PemesananTest extends TestCase
{
    use RefreshDatabase;

    private Showtime $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin pagi. Jadwal uji diputar Senin 13:00, jadi memakai tarif hari biasa.
        $this->travelTo('2026-10-05 08:00:00');

        $film = Movie::create(['title' => 'Film Uji', 'duration_minutes' => 120, 'release_date' => '2026-09-01']);
        $studio = Studio::create(['name' => 'Studio 1', 'baris' => 2, 'kursi_per_baris' => 3, 'harga_biasa' => 40000, 'harga_akhir_pekan' => 50000]);

        $this->jadwal = Showtime::create(['movie_id' => $film->id, 'studio_id' => $studio->id, 'show_time' => '2026-10-05 13:00']);
    }

    private function penonton(): User
    {
        return User::factory()->create();
    }

    private function bayar(User $user, string $kursi)
    {
        return $this->actingAs($user)->post('/proses-bayar/film-uji-' . $this->jadwal->movie_id, [
            'jadwal' => $this->jadwal->id,
            'kursi' => $kursi,
            'metode' => 'qris',
        ]);
    }

    public function test_satu_kursi_tidak_bisa_dipesan_dua_orang(): void
    {
        $this->bayar($this->penonton(), 'A1,A2')->assertRedirectContains('/tiket/');

        // Penonton kedua memilih A2, yang sudah diambil. Ia dikembalikan ke denah dengan pesan.
        $this->bayar($this->penonton(), 'A2,A3')
            ->assertRedirectContains('/kursi/')
            ->assertSessionHas('error', 'Kursi A2 sudah dipesan orang lain. Silakan pilih kursi lain.');

        $this->assertSame(1, Booking::count());
    }

    public function test_menekan_bayar_dua_kali_membuka_tiket_sendiri(): void
    {
        $penonton = $this->penonton();

        $this->bayar($penonton, 'A1');
        $kode = Booking::first()->booking_code;

        // Kiriman kedua untuk kursi yang sama tidak membuat pesanan baru, tapi membuka tiket yang sudah ada.
        $this->bayar($penonton, 'A1')->assertRedirect('/tiket/' . $kode);

        $this->assertSame(1, Booking::count());
    }

    public function test_total_harga_dihitung_dari_tarif_studio_dan_biaya_layanan(): void
    {
        $this->bayar($this->penonton(), 'A1,A2,A3');

        $pesanan = Booking::first();
        $this->assertSame(3 * (40000 + Booking::BIAYA_LAYANAN), $pesanan->total_price);
        $this->assertSame(Booking::LUNAS, $pesanan->status);
        $this->assertSame(['A1', 'A2', 'A3'], $pesanan->kursi);
    }

    public function test_tarif_akhir_pekan_hanya_sabtu_dan_minggu(): void
    {
        $this->jadwal->update(['show_time' => '2026-10-09 13:00']); // Jumat
        $this->assertSame(40000, $this->jadwal->fresh()->harga());

        $this->jadwal->update(['show_time' => '2026-10-10 13:00']); // Sabtu
        $this->assertSame(50000, $this->jadwal->fresh()->harga());

        $this->jadwal->update(['show_time' => '2026-10-11 13:00']); // Minggu
        $this->assertSame(50000, $this->jadwal->fresh()->harga());
    }

    public function test_kursi_yang_tidak_ada_di_studio_ditolak(): void
    {
        // Studio uji hanya punya baris A dan B, masing-masing 3 kursi.
        $this->bayar($this->penonton(), 'C1')->assertNotFound();
        $this->bayar($this->penonton(), 'A1,A2,A3,B1,B2,B3,A1')->assertRedirectContains('/tiket/');
        $this->assertSame(1, Booking::count());
    }

    public function test_penjualan_ditutup_saat_jam_tayang_tiba(): void
    {
        $this->travelTo('2026-10-05 13:00:00');

        $this->actingAs($this->penonton())
            ->get('/kursi/film-uji-' . $this->jadwal->movie_id . '?jadwal=' . $this->jadwal->id)
            ->assertRedirectContains('/film/')
            ->assertSessionHas('error');
    }

    public function test_jadwal_yang_kursinya_habis_tidak_bisa_dipesan(): void
    {
        $this->bayar($this->penonton(), 'A1,A2,A3,B1,B2,B3');

        $this->actingAs($this->penonton())
            ->get('/kursi/film-uji-' . $this->jadwal->movie_id . '?jadwal=' . $this->jadwal->id)
            ->assertRedirectContains('/film/')
            ->assertSessionHas('error');
    }

    public function test_kursi_pesanan_batal_bisa_dipesan_lagi(): void
    {
        Booking::create([
            'booking_code' => 'LAMA01', 'user_id' => $this->penonton()->id, 'showtime_id' => $this->jadwal->id,
            'kursi' => ['A1'], 'total_price' => 43000, 'status' => Booking::BATAL,
        ]);

        $this->bayar($this->penonton(), 'A1')->assertRedirectContains('/tiket/');
    }

    public function test_tiket_hanya_bisa_dibuka_pemesannya(): void
    {
        $this->bayar($this->penonton(), 'A1');
        $kode = Booking::first()->booking_code;

        $this->actingAs($this->penonton())->get('/tiket/' . $kode)->assertNotFound();
        $this->actingAs(Booking::first()->user)->get('/tiket/' . $kode)->assertOk()->assertSee($kode);
    }

    public function test_admin_tidak_bisa_memesan_tiket(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->bayar($admin, 'A1');

        $this->assertSame(0, Booking::count());
    }

    public function test_film_tanpa_jadwal_tidak_ditampilkan_sebagai_sedang_tayang(): void
    {
        Movie::create(['title' => 'Film Tanpa Jadwal', 'duration_minutes' => 100, 'release_date' => '2026-09-01']);

        $this->get('/')->assertOk()->assertSee('Film Uji')->assertDontSee('Film Tanpa Jadwal');
        $this->get('/film?status=tayang')->assertOk()->assertSee('Film Uji')->assertDontSee('Film Tanpa Jadwal');
    }
}
