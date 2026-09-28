@php
    /**
     * @var \App\Models\Movie $film
     * @var \Carbon\Carbon $tanggal
     * @var \App\Models\Showtime $jadwal
     * @var \App\Models\Studio $studio
     * @var string $layar
     * @var string $jam
     * @var int $harga
     * @var int $jumlah
     * @var int $jumlahDiminta
     * @var int $sisa
     * @var array $kursiTerisi
     */
@endphp

@extends('layouts.app')

@section('judul', 'Pilih kursi, ' . $film->title)

@section('konten')

    @php
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        [$th, $bl, $hr] = explode('-', $tanggal->format('Y-m-d'));
        $tanggalTeks = $namaHari[$tanggal->dayOfWeek] . ', ' . (int) $hr . ' ' . $namaBulan[(int) $bl];

        $filmSlug = $film->slug();

        $adaPoster = !empty($film->poster_url);
        $biayaLayanan = \App\Models\Booking::BIAYA_LAYANAN;
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">

        <a href="{{ url('/film/' . $filmSlug) }}?tanggal={{ $tanggal->format('Y-m-d') }}"
           class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Ganti jadwal
        </a>

        <div class="mt-4 grid gap-10 lg:grid-cols-[1fr_340px] lg:gap-12">

            <div class="min-w-0">

                <h1 class="text-2xl sm:text-3xl">Pilih kursi</h1>

                @if(session('error'))
                    <p role="alert" class="mt-4 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                        {{ session('error') }}
                    </p>
                @endif

                @if ($jumlah < $jumlahDiminta)
                    <p role="status" class="mt-4 rounded-lg border border-nema-line bg-nema-surface p-4 text-sm">
                        Kursi kosong di jadwal ini tinggal {{ $sisa }}, jadi jumlah tiketmu disesuaikan menjadi {{ $jumlah }}.
                    </p>
                @endif

                @include('partials.denah', ['studio' => $studio, 'kursiTerisi' => $kursiTerisi])

            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-xl bg-nema-surface p-5 sm:p-6">

                    <div class="flex gap-4">
                        <div class="w-16 shrink-0 sm:w-20">
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
                        <p class="text-sm text-nema-muted">Kursi dipilih</p>
                        <p data-daftar aria-live="polite" class="mt-1">belum ada</p>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt data-hitungan class="text-nema-muted">0 &times; Rp {{ number_format($harga, 0, ',', '.') }}</dt>
                            <dd data-subtotal>Rp 0</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-nema-muted">Biaya layanan</dt>
                            <dd data-layanan>Rp 0</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-nema-line/40 pt-4">
                        <span>Total</span>
                        <span data-total aria-live="polite" class="text-xl font-semibold">Rp 0</span>
                    </div>

                    <a data-lanjut aria-disabled="true"
                       class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover aria-disabled:pointer-events-none aria-disabled:opacity-40">
                        Lanjut
                    </a>

                    <p data-sisa aria-live="polite" class="mt-3 text-center text-xs text-nema-muted">
                        Pilih {{ $jumlah }} kursi untuk melanjutkan.
                    </p>

                </div>
            </div>

        </div>
    </div>

        <script>
        (function () {
            const MAKS = {{ $jumlah }};
            const HARGA = {{ $harga }};
            const BIAYA_LAYANAN = {{ $biayaLayanan }};

            const kursi = document.querySelectorAll('[data-kursi]:not([disabled])');
            const daftar = document.querySelector('[data-daftar]');
            const hitungan = document.querySelector('[data-hitungan]');
            const sisa = document.querySelector('[data-sisa]');
            const total = document.querySelector('[data-total]');
            const subtotal = document.querySelector('[data-subtotal]');
            const layanan = document.querySelector('[data-layanan]');
            const lanjut = document.querySelector('[data-lanjut]');

            const dasar = @json(url('/bayar/' . $filmSlug));
            const bawaan = { jadwal: @json($jadwal->id) };

            function rupiah(angka) {
                return 'Rp ' + angka.toLocaleString('id-ID');
            }

            function terpilih() {
                return Array.from(kursi)
                    .filter(function (k) { return k.getAttribute('aria-pressed') === 'true'; })
                    .map(function (k) { return k.dataset.kursi; });
            }

            function perbarui() {
                const dipilih = terpilih();
                const kurang = MAKS - dipilih.length;

                daftar.textContent = dipilih.length ? dipilih.join(', ') : 'belum ada';
                hitungan.textContent = dipilih.length + ' \u00d7 ' + rupiah(HARGA);
                subtotal.textContent = rupiah(dipilih.length * HARGA);
                layanan.textContent = rupiah(dipilih.length * BIAYA_LAYANAN);
                total.textContent = rupiah(dipilih.length * (HARGA + BIAYA_LAYANAN));

                sisa.textContent = kurang > 0
                    ? 'Pilih ' + kurang + ' kursi lagi untuk melanjutkan.'
                    : 'Kursi sudah lengkap.';

                lanjut.setAttribute('aria-disabled', kurang > 0 ? 'true' : 'false');

                if (kurang === 0) {
                    const isi = Object.assign({}, bawaan, { kursi: dipilih.join(',') });
                    lanjut.href = dasar + '?' + new URLSearchParams(isi).toString();
                } else {
                    lanjut.removeAttribute('href');
                }

                // Kursi yang belum dipilih dimatikan begitu jatahnya habis,
                // supaya orang tidak menekan kursi yang memang tidak bisa diambil.
                kursi.forEach(function (k) {
                    const ini = k.getAttribute('aria-pressed') === 'true';
                    k.disabled = !ini && kurang === 0;
                });
            }

            kursi.forEach(function (k) {
                k.addEventListener('click', function () {
                    const ini = k.getAttribute('aria-pressed') === 'true';
                    k.setAttribute('aria-pressed', ini ? 'false' : 'true');
                    perbarui();
                });
            });

            perbarui();
        })();
    </script>

@endsection
