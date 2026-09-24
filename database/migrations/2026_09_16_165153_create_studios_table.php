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
            $table->integer('capacity');
            // susunan kursi: jumlah baris (A, B, C, ...) dan jumlah kursi di tiap baris. Nomor kursi
            // seperti A1 atau C10 dihitung dari dua angka ini, jadi tidak perlu tabel kursi sendiri
            $table->integer('baris');
            $table->integer('kursi_per_baris');
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
