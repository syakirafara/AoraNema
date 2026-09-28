<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Showtime;
use Midtrans\Config;
use Midtrans\Transaction;

// Semua urusan status pembayaran Midtrans. Dipakai pemesanan online (BookingController) dan loket
// (KasirController), supaya kursi dari pesanan yang kedaluwarsa dilepas dengan cara yang sama.
class MidtransService
{
    // Menyiapkan pustaka Midtrans. Mengembalikan false kalau kunci server belum dipasang di .env.
    public function siap(): bool
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        return Config::$serverKey !== '';
    }

    // Menyamakan status pesanan dengan status transaksinya di Midtrans. Pesanan hanya menjadi
    // lunas kalau Midtrans menyatakan uangnya sudah diterima. Pesanan yang batal atau kedaluwarsa
    // melepas kursinya, supaya bisa dipesan orang lain. Hanya pesanan yang masih menunggu
    // pembayaran yang diubah, jadi pesanan yang sudah batal tidak bisa hidup lagi.
    public function terapkanStatus(string $bookingCode, string $statusMidtrans, ?string $statusPenipuan = null): void
    {
        $lunas = $statusMidtrans === 'settlement' || ($statusMidtrans === 'capture' && $statusPenipuan !== 'challenge');
        $batal = in_array($statusMidtrans, ['expire', 'cancel', 'deny', 'failure']);

        $pesanan = Booking::where('booking_code', $bookingCode)->where('status', Booking::MENUNGGU);

        if ($lunas) {
            $pesanan->update(['status' => Booking::LUNAS]);
        } elseif ($batal) {
            $pesanan->update(['status' => Booking::BATAL]);
        }
    }

    // Menanyakan status pesanan yang masih menunggu pembayaran ke Midtrans. Dipanggil saat penonton
    // kembali dari halaman Midtrans dan saat membuka Tiket Saya, karena pemberitahuan dari Midtrans
    // (webhook) tidak bisa sampai ke laptop yang tidak bisa diakses dari internet.
    public function cekStatus(string $bookingCode): void
    {
        $pesanan = Booking::where('booking_code', $bookingCode)->first();

        if (! $pesanan || $pesanan->status !== Booking::MENUNGGU || ! $this->siap()) {
            return;
        }

        try {
            // Kode pesanan juga dipakai sebagai order_id di Midtrans.
            $status = Transaction::status($bookingCode);
            $this->terapkanStatus($bookingCode, $status->transaction_status, $status->fraud_status ?? null);
        } catch (\Exception $e) {
            // Midtrans menjawab 404 kalau penonton belum memilih cara bayar di halaman Midtrans.
            // Kalau batas bayarnya sudah lewat, pesanan itu dianggap kedaluwarsa. Galat lain,
            // misalnya internet putus, tidak mengubah apa pun supaya pesanan tidak batal karena salah baca.
            if (str_contains($e->getMessage(), '404') && $pesanan->created_at->lt(now()->subMinutes(Booking::BATAS_BAYAR_MENIT))) {
                $this->terapkanStatus($bookingCode, 'expire');
            }
        }
    }

    // Melepas kursi dari pesanan yang sudah melewati batas bayar tanpa dibayar, di satu jadwal atau,
    // kalau jadwalnya tidak disebut, di semua jadwal. Dipanggil sebelum halaman menghitung kursi terisi,
    // supaya pesanan yang ditinggal tidak membuat jadwal tampak penuh atau terkunci.
    public function lepasKedaluwarsa(?Showtime $showtime = null): void
    {
        $kedaluwarsa = Booking::where('status', Booking::MENUNGGU)
            ->when($showtime, fn ($q) => $q->where('showtime_id', $showtime->id))
            ->where('created_at', '<', now()->subMinutes(Booking::BATAS_BAYAR_MENIT));

        // Tanpa Midtrans tidak ada yang bisa ditanyakan, jadi pesanan yang lewat batas langsung dibatalkan.
        if (! $this->siap()) {
            $kedaluwarsa->update(['status' => Booking::BATAL]);

            return;
        }

        $kedaluwarsa->pluck('booking_code')->each(fn ($kode) => $this->cekStatus($kode));
    }
}
