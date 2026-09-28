@extends('layouts.app')

@section('judul', 'Loket, ' . $film->title)

@section('konten')

    @php
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $maks = \App\Models\Booking::MAKS_KURSI_LOKET;
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">

        <a href="{{ url('/kasir') }}?tanggal={{ $jadwal->show_time->format('Y-m-d') }}"
           class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Ganti jadwal
        </a>

        <div class="mt-4 grid gap-10 lg:grid-cols-[1fr_360px] lg:gap-12">

            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl">{{ $film->title }}</h1>
                <p class="mt-1 text-sm text-nema-muted">
                    {{ $studio->label() }} &middot; {{ $namaHari[$jadwal->show_time->dayOfWeek] }},
                    {{ $jadwal->show_time->format('d/m/Y') }} &middot; {{ $jadwal->show_time->format('H:i') }}
                </p>

                @if (session('error') || $errors->any())
                    <p role="alert" class="mt-4 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                        {{ session('error') ?? $errors->first() }}
                    </p>
                @endif

                @include('partials.denah', ['studio' => $studio, 'kursiTerisi' => $kursiTerisi, 'labelPilihan' => 'Dipilih'])
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <form method="post" action="{{ url('/kasir/jadwal/' . $jadwal->id) }}" data-loket
                      class="rounded-xl bg-nema-surface p-5 sm:p-6">
                    @csrf
                    <input type="hidden" name="kursi" value="{{ old('kursi') }}" data-input-kursi>

                    <p class="text-sm text-nema-muted">Kursi dipilih</p>
                    <p data-daftar aria-live="polite" class="mt-1">belum ada</p>

                    <dl class="mt-4 space-y-2 border-t border-nema-line/40 pt-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt data-hitungan class="text-nema-muted">0 &times; Rp {{ number_format($harga, 0, ',', '.') }}</dt>
                            <dd data-total class="text-xl font-semibold">Rp 0</dd>
                        </div>
                    </dl>

                    @include('partials.batas-usia', ['usia' => $film->usia])

                    <label for="uang_diterima" class="mt-5 block text-sm">Uang diterima</label>
                    <input type="number" id="uang_diterima" name="uang_diterima" min="0" max="100000000" step="1" required
                           inputmode="numeric" value="{{ old('uang_diterima') }}" data-uang
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-bg px-4 text-lg">

                    {{-- Tombol cepat untuk pecahan uang yang paling sering dipakai. --}}
                    <div class="mt-2 grid grid-cols-4 gap-2 text-sm">
                        <button type="button" data-nominal="pas" class="min-h-11 rounded-md border border-nema-line transition-colors hover:bg-nema-surface-2">Pas</button>
                        @foreach ([50000, 100000, 200000] as $n)
                            <button type="button" data-nominal="{{ $n }}" class="min-h-11 rounded-md border border-nema-line transition-colors hover:bg-nema-surface-2">{{ $n / 1000 }}rb</button>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-nema-line/40 pt-4">
                        <span class="text-sm">Kembalian</span>
                        <span data-kembalian aria-live="polite" class="text-xl font-semibold">Rp 0</span>
                    </div>

                    <button type="submit" data-bayar disabled
                            class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover disabled:cursor-not-allowed disabled:opacity-40">
                        Terima pembayaran dan cetak tiket
                    </button>

                    <p data-pesan aria-live="polite" class="mt-3 text-center text-xs text-nema-muted">
                        Pilih kursi, paling banyak {{ $maks }}.
                    </p>
                </form>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const HARGA = {{ $harga }};
            const MAKS = {{ $maks }};

            const kursi = document.querySelectorAll('[data-kursi]:not([disabled])');
            const input = document.querySelector('[data-input-kursi]');
            const daftar = document.querySelector('[data-daftar]');
            const hitungan = document.querySelector('[data-hitungan]');
            const total = document.querySelector('[data-total]');
            const uang = document.querySelector('[data-uang]');
            const kembalian = document.querySelector('[data-kembalian]');
            const bayar = document.querySelector('[data-bayar]');
            const pesan = document.querySelector('[data-pesan]');

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
                const tagihan = dipilih.length * HARGA;
                const diterima = Number(uang.value) || 0;

                input.value = dipilih.join(',');
                daftar.textContent = dipilih.length ? dipilih.join(', ') : 'belum ada';
                hitungan.textContent = dipilih.length + ' × ' + rupiah(HARGA);
                total.textContent = rupiah(tagihan);
                kembalian.textContent = diterima >= tagihan ? rupiah(diterima - tagihan) : '-';

                // Kursi yang belum dipilih dimatikan begitu jatah satu transaksi habis.
                kursi.forEach(function (k) {
                    k.disabled = k.getAttribute('aria-pressed') !== 'true' && dipilih.length >= MAKS;
                });

                const bisa = dipilih.length > 0 && diterima >= tagihan;
                bayar.disabled = ! bisa;
                pesan.textContent = dipilih.length === 0
                    ? 'Pilih kursi, paling banyak ' + MAKS + '.'
                    : (diterima < tagihan ? 'Uang yang diterima masih kurang ' + rupiah(tagihan - diterima) + '.' : 'Siap diproses.');
            }

            kursi.forEach(function (k) {
                k.addEventListener('click', function () {
                    k.setAttribute('aria-pressed', k.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                    perbarui();
                });
            });

            document.querySelectorAll('[data-nominal]').forEach(function (tombol) {
                tombol.addEventListener('click', function () {
                    uang.value = tombol.dataset.nominal === 'pas' ? terpilih().length * HARGA : tombol.dataset.nominal;
                    perbarui();
                });
            });

            uang.addEventListener('input', perbarui);

            // Kursi pilihan sebelumnya dipulihkan kalau penjualan tadi ditolak, misalnya uangnya kurang.
            input.value.split(',').forEach(function (kode) {
                const k = document.querySelector('[data-kursi="' + kode + '"]:not([disabled])');
                if (k) k.setAttribute('aria-pressed', 'true');
            });

            // Tombol dimatikan begitu formulir dikirim, supaya transaksi tidak tercatat dua kali.
            document.querySelector('[data-loket]').addEventListener('submit', function () {
                bayar.disabled = true;
            });

            perbarui();
        })();
    </script>

@endsection
