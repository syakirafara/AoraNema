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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->unique()->nullable();
            $table->string('title');
            // kalimat pendek di bawah judul, diisi admin. Kosong berarti tidak ditampilkan
            $table->string('tagline')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('poster_url')->nullable();
            $table->integer('duration_minutes')->nullable();
            // batas usia penonton dari LSF: SU, 13+, 17+, atau 21+. TMDB tidak menyediakannya
            $table->string('usia', 3)->nullable();
            $table->date('release_date')->nullable();
            $table->boolean('is_showing')->default(true);
            // dicentang admin supaya film muncul di bagian Dipilih Pengelola di beranda
            $table->boolean('pilihan')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
