@php
    /**
     * @var \App\Models\Movie $film
     * @var \Carbon\Carbon $tanggal
     * @var \App\Models\Showtime $jadwal
     * @var string $layar
     * @var string $jam
     * @var int $harga
     * @var array $kursi
     */
@endphp

@extends('layouts.app')

@section('judul', 'Pembayaran, ' . $film->title)

@section('konten')

    @php
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        [$th, $bl, $hr] = explode('-', $tanggal->format('Y-m-d'));
        $tanggalTeks = $namaHari[$tanggal->dayOfWeek] . ', ' . (int) $hr . ' ' . $namaBulan[(int) $bl];

        // Biaya layanan per tiket. Angkanya sama dengan yang ditagihkan di BookingController::prosesBayar.
        $biayaLayanan = \App\Models\Booking::BIAYA_LAYANAN;

        $jumlah = count($kursi);
        $subtotal = $jumlah * $harga;
        $layanan = $jumlah * $biayaLayanan;
        $total = $subtotal + $layanan;

        $adaPoster = !empty($film->poster_url);

        // Alamat film, misalnya resident-evil-1
        $slugUrl = $film->slug();

        $metode = [
            'qris' => ['nama' => 'QRIS', 'ket' => 'Pindai dengan aplikasi bank atau dompet digital apa pun'],
            'va' => ['nama' => 'Transfer Bank', 'ket' => 'Nomor rekening khusus, berlaku sampai batas waktu pembayaran'],
            'ewallet' => ['nama' => 'Dompet Digital', 'ket' => 'Bayar dari saldo aplikasi dompet digitalmu'],
        ];
    @endphp



    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">

        <a href="{{ url('/kursi/' . $slugUrl) }}?jadwal={{ $jadwal->id }}&jumlah={{ $jumlah }}"
           class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Ganti kursi
        </a>

        <h1 class="mt-4 text-2xl sm:text-3xl">Pembayaran</h1>

        @if(session('error'))
            <p role="alert" class="mt-4 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                {{ session('error') }}
            </p>
        @endif

        {{-- data-sekali: tombol Bayar dimatikan begitu formulir dikirim, supaya pesanan tidak dibuat dua kali. --}}
        <form action="{{ url('/proses-bayar/' . $slugUrl) }}" method="POST" data-sekali
              class="mt-8 grid gap-10 lg:grid-cols-[1fr_340px] lg:gap-12">
            @csrf

            <input type="hidden" name="jadwal" value="{{ $jadwal->id }}">
            <input type="hidden" name="kursi" value="{{ implode(',', $kursi) }}">

            <fieldset class="min-w-0">
                <legend class="text-lg">Pilih cara bayar</legend>

                <div class="mt-4 space-y-3">
                    @foreach ($metode as $kode => $m)
                        <label class="flex cursor-pointer items-start gap-4 rounded-xl border border-nema-line p-4 transition-colors hover:bg-nema-surface has-checked:border-nema-accent has-checked:bg-nema-surface">
                            <input type="radio" name="metode" value="{{ $kode }}" required
                                   class="mt-1 size-5 shrink-0 accent-nema-maroon">

                            <span class="min-w-0">
                                <span class="block">{{ $m['nama'] }}</span>
                                <span class="mt-1 block text-sm text-nema-muted">{{ $m['ket'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <p class="mt-4 text-sm text-nema-muted">
                    Pembayaran diproses oleh Midtrans. Selesaikan dalam {{ \App\Models\Booking::BATAS_BAYAR_MENIT }} menit;
                    lewat dari itu pesanan batal dan kursinya dilepas untuk penonton lain.
                    Karena proyek ini untuk belajar, pesanan langsung dianggap lunas begitu halaman Midtrans dibuka.
                </p>

                <div class="mt-6 rounded-xl border border-nema-line p-4 text-sm text-nema-muted">
                    <p class="text-nema-text">Ketentuan tiket</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>Tiket yang sudah dibayar tidak bisa dibatalkan, dikembalikan, atau ditukar jadwal.</li>
                        <li>Tunjukkan kode tiket di pintu studio. Satu kursi untuk satu penonton.</li>
                        <li>Datang sebelum jam tayang. Film mulai sekitar {{ \App\Models\Showtime::IKLAN_MENIT }} menit setelah jam tayang, setelah iklan dan cuplikan film.</li>
                    </ul>
                </div>
            </fieldset>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-xl bg-nema-surface p-5 sm:p-6">

                    <div class="flex gap-4">
                        <div class="w-16 shrink-0">
                            @if ($adaPoster)
                                <img src="{{ $film->alamatPoster() }}"
                                     alt="Poster film {{ $film->title }}"
                                     class="aspect-2/3 w-full rounded-lg object-cover">
                            @else
                                <div class="aspect-2/3 w-full rounded-lg bg-nema-surface-2"></div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <h2 class="text-lg leading-tight">{{ $film->title }}</h2>
                            <p class="mt-1 text-sm text-nema-muted">{{ $layar }}</p>
                            <p class="mt-1 text-sm text-nema-muted">{{ $tanggalTeks }} &middot; {{ $jam }}</p>
                        </div>
                    </div>

                    @include('partials.batas-usia', ['usia' => $film->usia])

                    <div class="mt-5 border-t border-nema-line/40 pt-5">
                        <p class="text-sm text-nema-muted">Kursi</p>
                        <p class="mt-1">{{ implode(', ', $kursi) }}</p>
                    </div>

                    <dl class="mt-5 space-y-2 border-t border-nema-line/40 pt-5 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-nema-muted">{{ $jumlah }} &times; Rp {{ number_format($harga, 0, ',', '.') }}</dt>
                            <dd>Rp {{ number_format($subtotal, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-nema-muted">Biaya layanan</dt>
                            <dd>Rp {{ number_format($layanan, 0, ',', '.') }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-nema-line/40 pt-4">
                        <span>Total</span>
                        <span class="text-xl font-semibold">Rp {{ number_format($total, 0, ',', '.') }}</span>
                    </div>

                    <button type="submit"
                            class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover disabled:cursor-wait disabled:opacity-60">
                        Bayar sekarang
                    </button>

                </div>
            </div>

        </form>
    </div>

    <script>
        (function () {
            const tombol = document.querySelector('[data-sekali] button[type="submit"]');

            document.querySelector('[data-sekali]').addEventListener('submit', function () {
                tombol.disabled = true;
                tombol.textContent = 'Memproses...';
            });

            // Tombol dinyalakan lagi kalau halaman ini dibuka ulang lewat tombol Kembali di browser.
            window.addEventListener('pageshow', function () {
                tombol.disabled = false;
                tombol.textContent = 'Bayar sekarang';
            });
        })();
    </script>

@endsection
