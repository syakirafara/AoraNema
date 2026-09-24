# AoraNema

Aplikasi pemesanan tiket bioskop dengan Laravel. Rekomendasi film dan analisis sentimen masukan dikerjakan oleh layanan machine learning terpisah di folder [`ml`](ml).

## Fitur

**Penonton**
- Mendaftar akun dan memilih genre favorit
- Melihat film, jadwal tayang, dan harga
- Memilih kursi di denah, lalu membayar lewat Midtrans (sandbox)
- Melihat tiket berkode batang di halaman Tiket Saya
- Memberi nilai film setelah filmnya selesai, dan mengirim masukan

**Admin**
- Mengelola film, studio, dan jadwal tayang
- Melihat daftar pesanan dan ringkasan masukan

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

Akun penonton sudah punya genre favorit, jadi rekomendasi di beranda langsung muncul selama layanan ML menyala.

### Data contoh tambahan

Seeder utama membuat film yang sedang tayang, dua studio, dan jadwal untuk hari ini. Seeder berikut dijalankan sendiri kalau perlu:

| Perintah | Isinya |
|---|---|
| `php artisan db:seed --class=JadwalContohSeeder` | Jadwal untuk enam hari ke depan |
| `php artisan db:seed --class=FilmAkanTayangSeeder` | Film yang akan tayang, dari TMDB |
| `php artisan db:seed --class=MasukanContohSeeder` | Contoh masukan untuk halaman admin. Layanan ML harus menyala |

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
