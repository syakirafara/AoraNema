# AoraNema

Aplikasi pemesanan tiket bioskop dengan Laravel. Rekomendasi film dan analisis sentimen masukan dikerjakan oleh layanan machine learning terpisah di folder [`ml`](ml).

## Fitur

**Penonton**
- Mendaftar akun dan memilih genre favorit
- Melihat film yang sedang tayang dan akan tayang, jadwal tujuh hari ke depan, harga, dan sisa kursi
- Memilih kursi di denah, lalu membayar lewat Midtrans (sandbox)
- Melihat tiket berkode batang di halaman Tiket Saya
- Memberi nilai film setelah filmnya selesai, dan mengirim masukan

**Admin**
- Mengelola film, studio, dan jadwal tayang. Jadwal dilihat per hari dan per studio, seperti papan jadwal bioskop
- Melihat daftar pesanan, mencarinya dari kode tiket atau nama pemesan, dan melihat penjualan hari ini
- Melihat ringkasan masukan

**Layanan ML**
- Rekomendasi film di beranda, dari genre favorit atau nilai film yang pernah diberikan
- Nada masukan (positif, netral, negatif) dengan model IndoBERT

## Yang perlu dipasang

- PHP 8.4 atau lebih baru, dan Composer
- Node.js 22 dan npm
- MySQL, misalnya dari Laragon
- Python 3 dan pip, untuk layanan ML
- Kunci API TMDB, untuk mengambil data film saat seeding

## Menjalankan aplikasi

1. Unduh proyek dan pasang dependensinya.

   ```bash
   git clone https://github.com/syakirafara/AoraNema.git
   cd AoraNema
   composer install
   npm install
   ```

2. Salin `.env.example` menjadi `.env`, lalu buat kunci aplikasi. Di PowerShell, ganti `cp` dengan `copy`.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Buat database kosong bernama `aoranema` di phpMyAdmin, lalu isi bagian ini di `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=aoranema
   DB_USERNAME=root
   DB_PASSWORD=

   TMDB_API_KEY=kunci-tmdb-kamu
   ```

4. Buat tabel dan isi data contoh. Langkah ini butuh internet, karena data film diambil dari TMDB.

   ```bash
   php artisan migrate --seed
   ```

5. Jalankan dua perintah ini di dua terminal, lalu buka http://127.0.0.1:8000.

   ```bash
   php artisan serve
   ```

   ```bash
   npm run dev
   ```

### Akun contoh

| Peran | Email | Kata sandi |
|---|---|---|
| Admin | admin@aoranema.com | password123 |
| Penonton | user@aoranema.com | password123 |

Akun penonton sudah punya genre favorit, jadi rekomendasi di beranda langsung muncul selama layanan ML menyala. Kata sandi contoh ini hanya untuk belajar; ganti sebelum aplikasi dipasang di internet.

### Data contoh

Seeder utama membuat 10 film yang sedang tayang dan beberapa film yang akan tayang dari TMDB lengkap dengan batas usianya, dua akun contoh, delapan studio, dan jadwal tayang untuk hari ini sampai enam hari ke depan. Jadwalnya disusun mengikuti aturan bioskop di bawah, jadi tidak ada yang bertabrakan.

Jadwal hanya dibuat untuk tujuh hari. Kalau proyek dibuka lagi setelah itu, susun ulang jadwalnya:

```bash
php artisan db:seed --class=JadwalSeeder
```

Jadwal mendatang yang belum dipesan akan dihapus dan disusun ulang. Jadwal yang sudah dipesan tidak disentuh.

Seeder berikut dijalankan sendiri kalau perlu:

| Perintah | Isinya |
|---|---|
| `php artisan db:seed --class=LengkapiFilmSeeder` | Mengisi batas usia film yang masih kosong, dari rating TMDB. Batas usia yang sudah diisi admin tidak ditimpa |
| `php artisan db:seed --class=MasukanContohSeeder` | Contoh masukan untuk halaman admin. Layanan ML harus menyala |

## Aturan bioskop

Aturan ini dipakai di halaman admin, halaman penonton, dan seeder. Angkanya ditulis sekali sebagai konstanta di `app/Models/Showtime.php` dan `app/Models/Booking.php`.

**Jadwal tayang**
- Satu studio hanya memutar satu film dalam satu waktu, baik film yang sama maupun film yang berbeda. Film yang sama boleh diputar di beberapa studio sekaligus.
- Satu tayangan memakai studio mulai jam tayang, lalu 10 menit iklan dan cuplikan film, durasi film, dan 15 menit jeda bersih-bersih. Tayangan berikutnya di studio itu baru boleh mulai setelahnya.
- Jam tayang antara 10:00 dan 22:00.
- Tiket dijual untuk hari ini dan enam hari ke depan. Admin hanya bisa menyusun jadwal sejauh itu.
- Film baru bisa dijadwalkan mulai tanggal rilisnya, dan wajib punya durasi.
- Mengubah durasi atau tanggal rilis film ditolak kalau membuat jadwalnya bertabrakan atau tayang sebelum rilis.
- Jadwal yang sudah dipesan tidak bisa diubah atau dihapus. Format dan susunan kursi studionya juga dikunci sampai jadwal itu lewat.
- Film yang diarsipkan disembunyikan dari penonton dan tiketnya berhenti dijual. Jadwalnya tetap disimpan dan tetap memakai studio, jadi film bisa ditayangkan lagi bersama jadwalnya. Tiket yang sudah terjual tetap berlaku.

**Pemesanan**
- Satu kursi hanya untuk satu pesanan. Kalau dua penonton membayar kursi yang sama bersamaan, yang lebih dulu diproses yang mendapatkannya, dan yang lain dikembalikan ke denah.
- Paling banyak 6 kursi per pesanan. Harga tiket mengikuti tarif studio: hari biasa (Senin sampai Jumat), atau akhir pekan (Sabtu dan Minggu). Ada biaya layanan Rp3.000 per tiket.
- Penjualan ditutup saat jam tayang tiba. Jadwal yang kursinya habis ditandai Penuh.
- Pesanan yang tidak dibayar dalam 15 menit dibatalkan dan kursinya dilepas. Dalam mode belajar aturan ini tidak terlihat, karena pesanan langsung dianggap lunas (lihat bagian Pembayaran Midtrans).
- Tiket yang sudah dibayar tidak bisa dibatalkan atau ditukar. Film hanya bisa dinilai setelah selesai ditonton.
- Film tampil sebagai "sedang tayang" hanya kalau sudah rilis dan punya jadwal yang bisa dipesan.

## Menjalankan layanan ML

Dari folder proyek, di PowerShell:

```powershell
cd ml
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe -m uvicorn api.main:app --port 8001
```

Saat pertama dijalankan, model sentimen diunduh dari Hugging Face, jadi butuh internet dan agak lama. Laravel memanggil layanan ini di `http://127.0.0.1:8001`. Alamatnya bisa diubah lewat `ML_API_URL` di `.env`. Penjelasan lengkapnya ada di [ml/README.md](ml/README.md).

Tanpa layanan ML, aplikasi tetap berjalan. Bedanya, rekomendasi di beranda tidak muncul, dan masukan tetap tersimpan tapi nadanya tercatat `unknown`.

## Pembayaran Midtrans

Isi `MIDTRANS_SERVER_KEY` di `.env` dengan kunci server sandbox dari dashboard Midtrans. Karena proyek ini untuk belajar, pesanan langsung dianggap lunas begitu halaman Midtrans dibuka. Kalau kuncinya dikosongkan, halaman Midtrans dilewati dan pesanan juga langsung lunas.

Untuk pembayaran sungguhan, lihat komentar di `BookingController::prosesBayar`.

## Struktur database

Selain tabel bawaan Laravel, aplikasi ini memakai tujuh tabel:

| Tabel | Isinya |
|---|---|
| `movies` | Data film |
| `genres` | Daftar genre |
| `genre_movie` | Penghubung film dan genre |
| `studios` | Format layar, susunan kursi (baris × kursi per baris), serta tarif hari biasa dan akhir pekan |
| `showtimes` | Jadwal tayang. Harganya diambil dari tarif studio sesuai harinya |
| `bookings` | Pesanan: kursi, total harga, status, cara bayar, dan nilai film |
| `feedbacks` | Masukan penonton beserta nadanya |

Genre favorit penonton disimpan di kolom `favorite_genres` pada tabel `users`.

## Menjalankan tes

```bash
php artisan test
```

Tes memakai SQLite di memori, jadi database MySQL tidak tersentuh. Aturan jadwal ada di `tests/Feature/JadwalTest.php`, dan aturan pemesanan di `tests/Feature/PemesananTest.php`.
