@extends('layouts.app')

@section('judul', 'Tiket Saya, AoraNema')

@section('konten')

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">

        <h1 class="text-2xl sm:text-3xl">Tiket Saya</h1>

        @if (session('sukses'))
            <p class="mt-6 rounded-lg border border-nema-accent bg-nema-surface px-4 py-3 text-sm">
                {{ session('sukses') }}
            </p>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (['aktif' => 'Akan datang (' . count($aktif) . ')', 'riwayat' => 'Riwayat (' . count($riwayat) . ')'] as $nilai => $label)
                <a href="{{ url('/tiket-saya') }}{{ $nilai === 'aktif' ? '' : '?tab=riwayat' }}"
                   @if ($tab === $nilai) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center rounded-md px-4 text-sm transition-colors {{ $tab === $nilai ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @php $daftar = $tab === 'aktif' ? $aktif : $riwayat; @endphp

        @if (count($daftar))
            <ul class="mt-8 space-y-4">
                @foreach ($daftar as $p)
                    <li class="rounded-xl bg-nema-surface p-4 sm:p-5">
                        <div @class(['flex gap-4 sm:gap-5', 'opacity-70' => ! $p['aktif']])>

                            <div class="w-20 shrink-0 sm:w-24">
                                @if ($p['film']['poster'])
                                    <img src="{{ $p['film']['poster'] }}"
                                         alt="Poster film {{ $p['film']['judul'] }}"
                                         class="aspect-2/3 w-full rounded-lg object-cover">
                                @else
                                    <div class="aspect-2/3 w-full rounded-lg bg-nema-surface-2"></div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">

                                @if ($p['aktif'])
                                    <p class="text-xs text-nema-accent">{{ $p['kapan'] }}{{ $p['status'] === 'pending' ? ' · Menunggu pembayaran' : '' }}</p>
                                @else
                                    <p class="text-xs text-nema-muted">
                                        {{ match ($p['status']) { 'cancelled' => 'Dibatalkan', 'pending' => 'Tidak dibayar', default => 'Sudah ditonton' } }}
                                    </p>
                                @endif

                                <h2 class="mt-1 text-lg leading-tight">{{ $p['film']['judul'] }}</h2>

                                {{-- Bagian yang datanya kosong dilewati, supaya tidak tersisa titik menggantung. --}}
                                @php $keterangan = array_filter([$p['film']['genre'], $p['film']['durasi'] ? $p['film']['durasi'] . ' menit' : null, $p['film']['usia']]); @endphp
                                @if ($keterangan)
                                    <p class="mt-1 text-sm text-nema-muted">{{ implode(' · ', $keterangan) }}</p>
                                @endif

                                <p class="mt-2 text-sm text-nema-muted">
                                    {{ $p['tanggalTeks'] }} &middot; {{ $p['jam'] }}
                                </p>

                                <p class="mt-1 text-sm text-nema-muted">
                                    {{ $p['layar'] }} &middot; Kursi {{ implode(', ', $p['kursi']) }}
                                </p>

                                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                    <div class="flex gap-2">
                                        <dt class="text-nema-muted">Kode</dt>
                                        <dd class="font-mono">{{ $p['kode'] }}</dd>
                                    </div>
                                    <div class="flex gap-2">
                                        <dt class="text-nema-muted">Total</dt>
                                        <dd>Rp {{ number_format($p['total'], 0, ',', '.') }}</dd>
                                    </div>
                                </dl>
                            </div>

                        </div>

                        @if ($p['bisaDinilai'])
                            @php $nilaiku = $p['nilai']; @endphp

                            {{-- Tiap bintang tombol kirim sendiri: satu klik langsung menyimpan,
                                 bisa dijangkau dengan Tab, dan tetap jalan tanpa JavaScript. --}}
                            <form method="post" action="{{ url('/tiket-saya/nilai') }}"
                                  class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-nema-line/40 pt-4">
                                @csrf
                                <input type="hidden" name="kode" value="{{ $p['kode'] }}">

                                <p class="text-sm">
                                    {{ $nilaiku ? 'Penilaianmu: ' . $nilaiku . ' dari 5' : 'Bagaimana filmnya?' }}
                                </p>

                                <div class="flex gap-1" role="group" aria-label="Nilai film {{ $p['film']['judul'] }}">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <button type="submit" name="nilai" value="{{ $i }}"
                                                aria-label="{{ $i }} dari 5 bintang"
                                                @if ($nilaiku === $i) aria-pressed="true" @endif
                                                class="inline-flex size-11 items-center justify-center rounded-md text-2xl leading-none transition-colors hover:bg-nema-surface-2 {{ $nilaiku && $i <= $nilaiku ? 'text-nema-accent' : 'text-nema-line' }}">
                                            {{ $nilaiku && $i <= $nilaiku ? '★' : '☆' }}
                                        </button>
                                    @endfor
                                </div>
                            </form>
                        @endif

                        {{-- Tiket yang sudah lewat atau dibatalkan tidak menampilkan kode batang lagi,
                             supaya tidak bisa dipindai ulang di pintu masuk. --}}
                        @if ($p['aktif'])
                            <div class="mt-4 border-t border-nema-line/40 pt-4 sm:flex sm:justify-end">
                                <a href="{{ url('/tiket/' . $p['kode']) }}"
                                   class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover sm:w-auto">
                                    Lihat tiket
                                </a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-8 rounded-xl bg-nema-surface px-6 py-12 text-center">

                <svg class="mx-auto size-12 text-nema-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z" />
                    <path d="M14 5v2m0 4v2m0 4v2" />
                </svg>

                @if ($tab === 'aktif')
                    <p class="mt-5 text-lg">Belum ada tiket untuk ditonton</p>
                    <p class="mt-2 text-sm text-nema-muted">Tiket yang kamu pesan akan muncul di sini sampai jam tayangnya lewat.</p>

                    <a href="{{ url('/film?status=tayang') }}"
                       class="mt-6 inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface-2">
                        Lihat film yang sedang tayang
                    </a>
                @else
                    <p class="mt-5 text-lg">Belum ada riwayat pemesanan</p>
                    <p class="mt-2 text-sm text-nema-muted">Tiket yang sudah lewat atau dibatalkan tersimpan di sini.</p>
                @endif

            </div>
        @endif

    </div>

@endsection
