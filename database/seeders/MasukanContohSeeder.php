<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\User;
use App\Services\AoranemaMlService;
use Illuminate\Database\Seeder;
use Throwable;

// Masukan contoh untuk mengisi halaman Masukan di panel admin, supaya grafiknya bisa dilihat
// dengan data yang cukup banyak. Kalimatnya ditulis sendiri sebagai contoh, tapi nadanya
// (positif, netral, negatif) benar-benar dihitung model IndoBERT, bukan ditetapkan di sini.
//
// Jalankan dengan layanan ML menyala: php artisan db:seed --class=MasukanContohSeeder
class MasukanContohSeeder extends Seeder
{
    private const MASUKAN = [
        // ----- Pemesanan tiket
        ['booking', 'Pilih kursinya gampang banget, denahnya jelas dan langsung kelihatan mana yang kosong.'],
        ['booking', 'Baru sekali pakai langsung ngerti alurnya, nggak perlu tanya siapa-siapa.'],
        ['booking', 'Pas mau pesan buat rombongan, maksimal enam kursi jadi harus dipisah dua transaksi.'],
        ['booking', 'Jadwal filmnya lengkap, tapi jam tayang yang sudah lewat masih muncul di daftar.'],
        ['booking', 'Proses pesannya cepat, dari pilih film sampai bayar cuma dua menit.'],
        ['booking', 'Kursi yang saya incar keburu diambil orang pas mau bayar, agak kecewa.'],
        ['booking', 'Biasa saja, sama seperti aplikasi bioskop lain yang pernah saya pakai.'],

        // ----- Pembayaran
        ['payment', 'QRIS-nya lancar, sekali scan langsung terbayar dan tiket keluar.'],
        ['payment', 'Pembayaran gagal terus padahal saldo cukup, saya coba tiga kali tetap sama.'],
        ['payment', 'Halaman pembayarannya lama sekali terbuka, sempat saya kira error.'],
        ['payment', 'Pilihan bayarnya cukup banyak, ada QRIS dan transfer bank.'],
        ['payment', 'Sudah bayar tapi tiketnya belum muncul, saya harus refresh dulu.'],
        ['payment', 'Nominalnya jelas, biaya layanan ditulis terpisah jadi tidak ada kejutan.'],
        ['payment', 'Transfer bank nomor virtual account-nya susah disalin di HP.'],

        // ----- Website
        ['application', 'Tampilannya enak dilihat, warnanya kalem dan tulisannya besar.'],
        ['application', 'Websitenya ringan dibuka di HP, tidak berat seperti aplikasi bioskop lain.'],
        ['application', 'Beberapa gambar poster lama muncul kalau sinyal saya jelek.'],
        ['application', 'Saya bingung mencari tiket yang sudah dibeli, menunya kurang terlihat.'],
        ['application', 'Fitur cari filmnya membantu, langsung ketemu judul yang saya mau.'],
        ['application', 'Di HP saya tulisannya sempat kepotong waktu buka halaman kursi.'],
        ['application', 'Cukup membantu, tidak ada yang istimewa tapi juga tidak menyusahkan.'],

        // ----- Layanan pelanggan
        ['customer_service', 'Dibalas cepat waktu saya tanya soal tiket yang belum masuk, terima kasih.'],
        ['customer_service', 'Sudah kirim keluhan dua hari lalu tapi belum ada jawaban sama sekali.'],
        ['customer_service', 'Petugasnya ramah dan jelas menerangkan cara menukar tiket.'],
        ['customer_service', 'Susah dihubungi kalau di luar jam kerja, padahal nonton saya malam.'],
        ['customer_service', 'Jawabannya standar, terasa seperti dibalas otomatis.'],

        // ----- Layanan di bioskop
        ['cinema_service', 'Ruangannya bersih dan dingin, suaranya juga mantap.'],
        ['cinema_service', 'Antre masuk lama sekali karena petugas pemindai tiketnya cuma satu.'],
        ['cinema_service', 'Kursinya nyaman, bisa duduk dua jam tanpa pegal.'],
        ['cinema_service', 'Toiletnya kurang bersih, semoga diperhatikan.'],
        ['cinema_service', 'Filmnya mulai telat sepuluh menit dari jadwal yang tertulis di tiket.'],
        ['cinema_service', 'Biasa saja, standar bioskop pada umumnya.'],
        ['cinema_service', 'Petugasnya sigap bantu cari kursi waktu lampu sudah gelap.'],

        // ----- Lainnya
        ['other', 'Semoga nanti ada promo buat pelajar, harga tiketnya lumayan berat.'],
        ['other', 'Sarannya tambahkan pilihan bahasa Inggris untuk penonton asing.'],
        ['other', 'Sejauh ini puas, saya sudah pesan tiga kali dan tidak ada kendala.'],
        ['other', 'Kalau bisa tiketnya dikirim juga lewat email, biar tidak hilang.'],
    ];

    public function run(AoranemaMlService $ml): void
    {
        $penonton = User::where('role', 'user')->pluck('id')->all();

        if (! $penonton) {
            $this->command->error('Belum ada akun penonton. Jalankan UserSeeder dulu.');

            return;
        }

        $dibuat = 0;
        $gagalAnalisis = 0;

        foreach (self::MASUKAN as $urut => [$kategori, $komentar]) {
            try {
                $hasil = $ml->analyzeSentiment($komentar);
                $nada = $hasil['sentiment'];
                $keyakinan = $hasil['confidence'];
            } catch (Throwable $e) {
                $nada = 'unknown';
                $keyakinan = null;
                $gagalAnalisis++;
            }

            Feedback::create([
                'user_id' => $penonton[$urut % count($penonton)],
                'category' => $kategori,
                'comment' => $komentar,
                'sentiment' => $nada,
                'confidence' => $keyakinan,
                // Waktu dibuat disebar mundur beberapa jam, supaya daftar "terbaru" tidak
                // menampilkan puluhan masukan dengan jam yang sama persis.
                'created_at' => now()->subMinutes($urut * 37),
                'updated_at' => now()->subMinutes($urut * 37),
            ]);

            $dibuat++;
        }

        $this->command->info("SELESAI: {$dibuat} masukan contoh dibuat.");

        if ($gagalAnalisis) {
            $this->command->warn("{$gagalAnalisis} masukan belum dianalisis nadanya karena layanan ML tidak bisa dihubungi.");
        }
    }
}
