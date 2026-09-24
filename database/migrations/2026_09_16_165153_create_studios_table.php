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
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // format layar yang tampil ke penonton: Regular 2D, Regular 3D, atau IMAX.
            // dipisah dari nama, supaya bioskop bisa punya banyak studio dengan format yang sama
            $table->string('format')->default('Regular 2D');
            // susunan kursi: jumlah baris (A, B, C, ...) dan jumlah kursi di tiap baris. Nomor kursi
            // seperti A1 atau C10, juga kapasitas studio, dihitung dari dua angka ini
            $table->integer('baris');
            $table->integer('kursi_per_baris');
            // harga per kursi ditentukan studio dan harinya, bukan jam tayangnya.
            // akhir pekan dihitung Sabtu dan Minggu
            $table->integer('harga_biasa')->default(45000);
            $table->integer('harga_akhir_pekan')->default(55000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studios');
    }
};
