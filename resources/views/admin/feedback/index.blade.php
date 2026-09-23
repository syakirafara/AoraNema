@extends('layouts.app')

@section('judul', 'Masukan Penonton, AoraNema')

@section('konten')

    @include('admin.nav')

    @php
        $namaSentimen = ['positive' => 'Positif', 'neutral' => 'Netral', 'negative' => 'Negatif', 'unknown' => 'Belum dianalisis'];
        $namaKategori = [
            'booking' => 'Pemesanan tiket',
            'payment' => 'Pembayaran',
            'application' => 'Website AoraNema',
            'customer_service' => 'Layanan pelanggan',
            'cinema_service' => 'Layanan di bioskop',
            'other' => 'Lainnya',
        ];

        // Warna nada hanya pendukung. Tiap angka tetap ditulis, supaya halaman ini tetap terbaca
        // oleh yang sulit membedakan warna.
        $warna = ['positive' => 'bg-usia-semua', 'neutral' => 'bg-nema-line', 'negative' => 'bg-usia-dewasa'];

        $total = array_sum($summary);
        $negatif = $summary['negative'] ?? 0;
        $persen = fn ($jumlah, $dari) => $dari ? round($jumlah / $dari * 100) : 0;

        // Bagian layanan diurutkan dari porsi keluhan terbesar, karena itu yang lebih dulu perlu
        // ditindaklanjuti. Enam keluhan dari sepuluh masukan lebih mendesak daripada enam dari empat puluh.
        $perBagian = $byCategory->groupBy('category')
            ->sortByDesc(fn ($b) => ($b->firstWhere('sentiment', 'negative')->total ?? 0) / max(1, $b->sum('total')));

        $paling = $perBagian->keys()->first();
        $jalur = url('/admin/feedback');
    @endphp

    @if ($total === 0)
        <div class="mx-auto max-w-5xl px-4 py-20 text-center sm:px-6">
            <h1 class="text-2xl sm:text-3xl">Belum ada masukan yang masuk</h1>
            <p class="mx-auto mt-3 max-w-prose text-nema-muted">
                Masukan dari penonton muncul di sini setelah mereka mengirimnya lewat halaman Kirim Masukan,
                lengkap dengan nada yang dibaca model analisis sentimen.
            </p>
        </div>
    @else
        {{-- Satu angka dibesarkan, yaitu porsi keluhan, karena itu yang menentukan tindakan pengelola. --}}
        <header class="border-b border-nema-line/40 bg-nema-surface">
            <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
                <p class="text-sm text-nema-muted">Masukan penonton</p>

                <div class="mt-4 flex flex-wrap items-end gap-x-12 gap-y-8">
                    <div>
                        <p class="text-6xl font-semibold leading-none text-usia-dewasa sm:text-7xl">
                            {{ $persen($negatif, $total) }}%
                        </p>
                        <p class="mt-3 max-w-xs text-nema-muted">
                            masukan bernada negatif, {{ $negatif }} dari {{ $total }} yang masuk.
                            @if ($paling)
                                Paling banyak di <span class="text-nema-text">{{ mb_strtolower($namaKategori[$paling] ?? $paling) }}</span>.
                            @endif
                        </p>
                    </div>

                    <dl class="flex gap-10 text-sm">
                        @foreach (['neutral', 'positive'] as $kunci)
                            <div>
                                <dt class="text-nema-muted">{{ $namaSentimen[$kunci] }}</dt>
                                <dd class="mt-1 text-2xl font-semibold">
                                    {{ $persen($summary[$kunci] ?? 0, $total) }}%
                                    <span class="text-sm font-normal text-nema-muted">{{ $summary[$kunci] ?? 0 }} masukan</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">

            <div class="relative overflow-x-auto">
                <table class="w-full min-w-2xl text-left text-sm">
                    <caption class="sr-only">Masukan per bagian layanan, diurutkan dari porsi keluhan terbesar</caption>
                    <thead class="text-nema-muted">
                        <tr>
                            <th class="pb-3 pr-4 font-normal">Bagian layanan</th>
                            <th class="pb-3 pr-4 font-normal">Sebaran nada</th>
                            <th class="pb-3 pr-4 font-normal">Masukan</th>
                            <th class="pb-3 text-right font-normal">Keluhan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-nema-line/30 border-t border-nema-line/30">
                        @foreach ($perBagian as $bagian => $baris)
                            @php
                                $n = $baris->sum('total');
                                $neg = $baris->firstWhere('sentiment', 'negative')->total ?? 0;
                                $aktif = $kategori === $bagian;
                            @endphp

                            <tr>
                                <td class="py-3 pr-4">
                                    {{-- Klik nama bagian untuk menyaring daftar di bawah; klik lagi untuk melepas. --}}
                                    <a href="{{ $jalur }}?{{ http_build_query(array_filter(['kategori' => $aktif ? null : $bagian, 'nada' => $nada])) }}"
                                       @if ($aktif) aria-current="true" @endif
                                       class="inline-flex min-h-11 items-center transition-colors hover:text-nema-accent {{ $aktif ? 'text-nema-accent' : '' }}">
                                        {{ $namaKategori[$bagian] ?? $bagian }}
                                    </a>
                                </td>

                                <td class="w-1/2 py-3 pr-4">
                                    <span class="flex h-1.5 overflow-hidden rounded-full bg-nema-surface-2" aria-hidden="true">
                                        @foreach ($warna as $kunci => $w)
                                            @php $x = $baris->firstWhere('sentiment', $kunci)->total ?? 0; @endphp
                                            @if ($x)<span class="{{ $w }}" style="width: {{ $x / $n * 100 }}%"></span>@endif
                                        @endforeach
                                    </span>
                                </td>

                                <td class="py-3 pr-4 text-nema-muted">{{ $n }}</td>

                                <td class="py-3 text-right {{ $neg ? 'text-usia-dewasa' : 'text-nema-muted' }}">
                                    {{ $persen($neg, $n) }}%
                                    <span class="text-nema-muted">({{ $neg }})</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-14 flex flex-wrap items-baseline justify-between gap-4">
                <h2 class="text-xl sm:text-2xl">
                    {{ $kategori ? $namaKategori[$kategori] : 'Semua masukan' }}
                    <span class="text-nema-muted">({{ $latestFeedbacks->total() }})</span>
                </h2>

                {{-- Saringan nada. Saringan bagian layanan dipilih lewat tabel di atas. --}}
                <div class="no-scrollbar relative -mx-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    @php
                        $pilihanNada = collect(['' => 'Semua nada', 'negative' => 'Negatif', 'neutral' => 'Netral', 'positive' => 'Positif'])
                            ->filter(fn ($label, $kunci) => $kunci === '' || ($summary[$kunci] ?? 0));
                    @endphp

                    @foreach ($pilihanNada as $kunci => $label)
                        @php $aktif = $nada === ($kunci ?: null); @endphp

                        <a href="{{ $jalur }}?{{ http_build_query(array_filter(['kategori' => $kategori, 'nada' => $kunci])) }}"
                           @if ($aktif) aria-current="page" @endif
                           class="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm transition-colors {{ $aktif ? 'bg-nema-maroon text-white' : 'text-nema-muted hover:bg-nema-surface' }}">
                            {{ $label }}
                            @if ($kunci)
                                <span class="ml-1.5 {{ $aktif ? 'text-white/75' : '' }}">{{ $persen($summary[$kunci], $total) }}%</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($kategori || $nada)
                <p class="mt-3 text-sm text-nema-muted">
                    Saringan sedang aktif.
                    <a href="{{ $jalur }}" class="text-nema-accent hover:underline">Tampilkan semua masukan</a>
                </p>
            @endif

            @if ($latestFeedbacks->isEmpty())
                <p class="mt-8 rounded-xl bg-nema-surface px-6 py-10 text-center text-nema-muted">
                    Tidak ada masukan yang cocok dengan saringan ini.
                </p>
            @else
                <ul class="mt-6 space-y-8">
                    @foreach ($latestFeedbacks as $m)
                        {{-- Garis di kiri menandai nada, jadi satu keluhan tetap kelihatan waktu halaman digulir cepat. --}}
                        <li class="border-l-2 pl-5 {{ $m->sentiment === 'negative' ? 'border-usia-dewasa' : ($m->sentiment === 'positive' ? 'border-usia-semua' : 'border-nema-line') }}">
                            <p class="text-xs text-nema-muted">
                                {{ $namaKategori[$m->category] ?? $m->category }}
                                &middot; {{ $namaSentimen[$m->sentiment] ?? $m->sentiment }}
                                @if ($m->confidence)
                                    <span title="Tingkat keyakinan model">({{ round($m->confidence * 100) }}% yakin)</span>
                                @endif
                            </p>

                            <p class="mt-2 text-lg leading-relaxed">{{ $m->comment }}</p>

                            <p class="mt-2 text-xs text-nema-muted">
                                {{ $m->user?->name ?? 'Akun sudah dihapus' }}
                                &middot; {{ $m->created_at->translatedFormat('d F Y, H:i') }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10">{{ $latestFeedbacks->links('partials.halaman') }}</div>
            @endif

        </div>
    @endif

@endsection
