<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom 'role' dengan nilai bawaan 'user'
            $table->string('role')->default('user')->after('password');
            // Nama genre yang dipilih saat mendaftar, misalnya ["Action", "Drama"].
            // Dipakai untuk rekomendasi pertama sebelum user punya riwayat nilai
            $table->json('favorite_genres')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'favorite_genres']);
        });
    }
};