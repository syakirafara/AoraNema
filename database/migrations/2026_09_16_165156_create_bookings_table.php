<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Satu baris per pesanan. Pembayaran dan nilai bintang ikut disimpan di sini,
        // karena satu pesanan hanya punya satu pembayaran dan satu penilaian.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // kode di tiket, sekaligus order_id di Midtrans
            $table->string('booking_code')->unique();
            // buat tau siapa yang pesan
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // nonton jam berapanya
            $table->foreignId('showtime_id')->constrained()->cascadeOnDelete();

            // harga semua kursi ditambah biaya layanan
            $table->integer('total_price');
            //nanti ada pending, paid, dan cancelled
            $table->string('status')->default('pending');
            // cara bayar yang dipilih penonton: qris, va, atau ewallet
            $table->string('payment_method')->nullable();
            // alamat halaman pembayaran Midtrans, supaya pembayaran yang tertunda bisa dilanjutkan
            $table->string('snap_url')->nullable();
            // nilai bintang 1 sampai 5, diisi penonton setelah filmnya selesai
            $table->unsignedTinyInteger('rating')->nullable();

            $table->timestamps();
        });

        // Tabel penghubung pesanan dan kursi, karena satu pesanan bisa berisi beberapa kursi.
        // Tidak ada aturan unik jadwal dan kursi di sini, karena jadwalnya disimpan di pesanan.
        // Kursi ganda dicegah BookingController::prosesBayar, yang mengunci jadwal selama
        // memeriksa dan menyimpan pesanan.
        Schema::create('booking_seat', function (Blueprint $table) {
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();

            $table->primary(['booking_id', 'seat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_seat');
        Schema::dropIfExists('bookings');
    }
};
