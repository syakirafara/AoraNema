@php
    /**
     * @var \App\Models\Movie $film
     * @var \Carbon\Carbon $tanggalCarbon
     */
@endphp

@extends('layouts.app')

@section('judul', 'Tiket, ' . $film->title)

@section('konten')

    @php
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        [$th, $bl, $hr] = explode('-', $tanggalCarbon->format('Y-m-d'));
        $tanggalTeks = $namaHari[$tanggalCarbon->dayOfWeek] . ', ' . (int) $hr . ' ' . $namaBulan[(int) $bl] . ' ' . $th;
    @endphp

    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6">

        @if(session('warning'))
            <p role="status" class="mb-6 rounded-lg border border-nema-line bg-nema-surface p-4 text-sm text-nema-muted">
                {{ session('warning') }}
            </p>
        @endif

        @php
            [$judulTiket, $ketTiket] = match ($keadaan) {
                'batal' => ['Pesanan dibatalkan', 'Tiket ini sudah tidak berlaku.'],
                'belum-bayar' => ['Menunggu pembayaran', 'Kode masuk muncul di sini setelah pembayaran diterima.'],
                'selesai' => ['Film sudah selesai', 'Tiket ini sudah lewat dan tidak bisa dipakai masuk lagi.'],
                default => ['Tiketmu siap', 'Tunjukkan kode ini ke petugas di pintu masuk.'],
            };
        @endphp

        <h1 class="text-center text-2xl sm:text-3xl">{{ $judulTiket }}</h1>

        <p class="mt-2 text-center text-sm text-nema-muted">{{ $ketTiket }}</p>

        <div data-tiket class="mt-8 overflow-hidden rounded-2xl bg-nema-surface">

            <div class="p-6 sm:p-8">
                <p class="text-center text-xs text-nema-muted">Kode pesanan</p>

                {{-- Alasnya putih dan batangnya hitam karena pemindai butuh kontras setinggi
                     mungkin. Kode batang di atas latar gelap sering gagal terbaca. Tiket yang
                     tidak bisa dipakai masuk tidak diberi kode batang, supaya tidak bisa dipindai. --}}
                @if ($keadaan === 'aktif')
                    <div class="mt-4 rounded-lg bg-white p-3 flex justify-center">
                        {!! $batang !!}
                    </div>
                @endif

                <p class="mt-4 text-center font-mono text-2xl font-semibold tracking-widest sm:text-3xl">{{ $kode }}</p>
            </div>

            {{-- Garis putus-putus meniru sobekan tiket, penanda batas antara kode dan rinciannya --}}
            <div data-sobek class="border-t border-dashed border-nema-line"></div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-5 p-6 text-sm sm:p-8">
                <div class="col-span-2">
                    <dt class="text-nema-muted">Film</dt>
                    <dd class="mt-1 text-base">{{ $film->title }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Tanggal</dt>
                    <dd class="mt-1">{{ $tanggalTeks }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Jam</dt>
                    <dd class="mt-1">{{ $jam }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Studio</dt>
                    <dd class="mt-1">{{ $layar }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Kursi</dt>
                    <dd class="mt-1">{{ implode(', ', $kursi) }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Cara bayar</dt>
                    <dd class="mt-1">{{ $namaMetode }}</dd>
                </div>
                <div>
                    <dt class="text-nema-muted">Total dibayar</dt>
                    <dd class="mt-1">Rp {{ number_format($total, 0, ',', '.') }}</dd>
                </div>
            </dl>

        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <button type="button" data-cetak
                    class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                Cetak atau simpan PDF
            </button>

            <a href="{{ url('/') }}"
               class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                Kembali ke beranda
            </a>
        </div>

    </div>

    <style>
        @media print {
            @page {
                margin: 18mm;
            }

            /* Semua disembunyikan dulu, lalu kartu tiketnya saja yang ditampilkan lagi.
               Cara ini lebih tahan banting daripada menyembunyikan satu per satu elemen. */
            body * {
                visibility: hidden;
            }

            [data-tiket],
            [data-tiket] * {
                visibility: visible;
            }

            [data-tiket] {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                margin: 0;
                border: 1px solid #000;
                border-radius: 0;

                /* Dicetak terang, bukan gelap. Latar hitam boros tinta dan bikin
                   kode pesanannya buram di kebanyakan printer. */
                background: #fff;
                color: #000;
            }

            [data-tiket] * {
                color: #000 !important;
            }

            [data-tiket] dt {
                color: #555 !important;
            }

            [data-sobek] {
                border-color: #000 !important;
            }
        }
    </style>

    <script>
        document.querySelector('[data-cetak]').addEventListener('click', function () {
            window.print();
        });
    </script>

@endsection
