<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Penjualan tiket di loket oleh kasir: bayar tunai, memakai denah kursi yang sama dengan pemesanan online.
class KasirTest extends TestCase
{
    use RefreshDatabase;

    private Showtime $jadwal;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin pagi, jadi jadwal Senin 13:00 memakai tarif hari biasa.
        $this->travelTo('2026-10-05 08:00:00');

        $film = Movie::create(['title' => 'Film Uji', 'duration_minutes' => 120, 'release_date' => '2026-09-01']);
        $studio = Studio::create(['name' => 'Studio 1', 'baris' => 2, 'kursi_per_baris' => 3, 'harga_biasa' => 40000, 'harga_akhir_pekan' => 50000]);

        $this->jadwal = Showtime::create(['movie_id' => $film->id, 'studio_id' => $studio->id, 'show_time' => '2026-10-05 13:00']);
        $this->kasir = User::factory()->create(['role' => 'cashier']);
    }

    private function jual(string $kursi, int $uang, ?User $kasir = null)
    {
        return $this->actingAs($kasir ?? $this->kasir)->post('/kasir/jadwal/' . $this->jadwal->id, [
            'kursi' => $kursi,
            'uang_diterima' => $uang,
        ]);
    }

    public function test_kasir_menjual_tiket_tunai_tanpa_biaya_layanan(): void
    {
        $respons = $this->jual('A1,A2', 100000);

        $pesanan = Booking::first();
        $respons->assertRedirect('/tiket/' . $pesanan->booking_code)->assertSessionHas('kembalian', 20000);

        $this->assertSame(80000, $pesanan->total_price);
        $this->assertSame(Booking::LUNAS, $pesanan->status);
        $this->assertSame('tunai', $pesanan->payment_method);
        $this->assertSame($this->kasir->id, $pesanan->user_id);
    }

    public function test_uang_kurang_ditolak(): void
    {
        $this->jual('A1,A2', 50000)->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
    }

    public function test_kursi_yang_sudah_dibeli_online_tidak_bisa_dijual_di_loket(): void
    {
        $this->actingAs(User::factory()->create())->post('/proses-bayar/film-uji-' . $this->jadwal->movie_id, [
            'jadwal' => $this->jadwal->id, 'kursi' => 'A1', 'metode' => 'qris',
        ]);

        $this->jual('A1,A2', 100000)->assertSessionHas('error');
        $this->assertSame(1, Booking::count());

        // Sebaliknya, kursi yang terjual di loket tampil terisi bagi penonton online.
        $this->jual('B1', 50000);
        $this->actingAs(User::factory()->create())->post('/proses-bayar/film-uji-' . $this->jadwal->movie_id, [
            'jadwal' => $this->jadwal->id, 'kursi' => 'B1', 'metode' => 'qris',
        ])->assertSessionHas('error');
    }

    public function test_penjualan_ditutup_saat_jam_tayang_tiba(): void
    {
        $this->travelTo('2026-10-05 13:00:00');

        $this->actingAs($this->kasir)->get('/kasir/jadwal/' . $this->jadwal->id)
            ->assertRedirect('/kasir')
            ->assertSessionHas('error');
    }

    public function test_loket_hanya_untuk_kasir(): void
    {
        $this->actingAs($this->kasir)->get('/kasir')->assertOk()->assertSee('Film Uji');
        $this->actingAs($this->kasir)->get('/kasir/jadwal/' . $this->jadwal->id)->assertOk()->assertSee('Uang diterima');
        $this->actingAs(User::factory()->create())->get('/kasir')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/kasir')->assertForbidden();

        // Kasir menjual lewat loket, bukan lewat pemesanan online.
        $this->actingAs($this->kasir)->get('/kursi/film-uji-' . $this->jadwal->movie_id . '?jadwal=' . $this->jadwal->id)->assertForbidden();
    }

    public function test_tiket_loket_bisa_dicetak_ulang_kasir_lain_tapi_tidak_penonton(): void
    {
        $this->jual('A1', 40000);
        $kode = Booking::first()->booking_code;

        $this->actingAs(User::factory()->create(['role' => 'cashier']))->get('/tiket/' . $kode)->assertOk()->assertSee('Tunai di loket');
        $this->actingAs(User::factory()->create())->get('/tiket/' . $kode)->assertNotFound();
    }

    public function test_kasir_diarahkan_ke_loket_setelah_masuk(): void
    {
        $kasir = User::factory()->create(['role' => 'cashier', 'email' => 'kasir@contoh.test', 'password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $kasir->email, 'password' => 'rahasia123'])->assertRedirect('/kasir');
    }

    public function test_transaksi_tanpa_kursi_ditolak(): void
    {
        $this->jual(',', 0)->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
    }
}
