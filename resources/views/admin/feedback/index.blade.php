@extends('layouts.app')

@section('judul', 'Masukan Penonton, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

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
            $total = array_sum($summary);

            // Warna hanya pendukung: tiap angka selalu ditulis juga sebagai teks, supaya
            // tetap terbaca oleh yang sulit membedakan warna.
            $warnaSentimen = [
                'positive' => 'bg-usia-semua',
                'neutral' => 'bg-nema-line',
                'negative' => 'bg-usia-dewasa',
            ];
        @endphp

        <h1 class="text-2xl sm:text-3xl">Masukan Penonton</h1>

        <p class="mt-2 max-w-prose text-sm text-nema-muted">
            {{ $total }} masukan tersimpan. Nada tiap masukan (positif, netral, negatif) ditentukan model
            analisis sentimen, bukan dibaca satu per satu oleh pengelola. Ringkasan dan grafik di bawah
            memakai seluruh masukan; daftarnya bisa disaring per bagian layanan dan per nada.
        </p>

        @if ($total === 0)
            <div class="mt-8 rounded-xl bg-nema-surface px-6 py-12 text-center">
                <p class="text-lg">Belum ada masukan yang masuk</p>
                <p class="mt-2 text-sm text-nema-muted">
                    Masukan dari penonton muncul di sini setelah mereka mengirimnya lewat halaman Kirim Masukan.
                </p>
            </div>
        @else
            {{-- Ringkasan nada masukan. Angkanya dihitung dari seluruh masukan yang tersimpan. --}}
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach (array_slice($namaSentimen, 0, 3, true) as $kunci => $label)
                    @php $jumlah = $summary[$kunci] ?? 0; @endphp
                    <div class="rounded-xl bg-nema-surface p-5">
                        <p class="text-sm text-nema-muted">{{ $label }}</p>
                        <p class="mt-1 text-3xl font-semibold">{{ $jumlah }}</p>
                        <p class="mt-1 text-sm text-nema-muted">
                            {{ $total ? round($jumlah / $total * 100) : 0 }}% dari semua masukan
                        </p>
                    </div>
                    @endforeach

                @if (($summary['unknown'] ?? 0) > 0)
                    <div class="rounded-xl border border-nema-line bg-nema-surface p-5 sm:col-span-3">
                        <p class="text-sm">
                            {{ $summary['unknown'] }} masukan belum dianalisis nadanya, karena layanan
                            analisis sentimen tidak bisa dihubungi saat masukan itu dikirim.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Satu batang untuk menjawab: seberapa besar bagian masukan yang bernada negatif? --}}
            <div class="mt-8 flex h-3 overflow-hidden rounded-full bg-nema-surface-2" role="img"
                 aria-label="Perbandingan nada masukan: {{ implode(', ', array_map(fn ($k, $l) => ($summary[$k] ?? 0) . ' ' . $l, array_keys($namaSentimen), $namaSentimen)) }}">
                @foreach ($warnaSentimen as $kunci => $warna)
                    @php $jumlah = $summary[$kunci] ?? 0; @endphp
                    @if ($jumlah)
                        <div class="{{ $warna }}" style="width: {{ $jumlah / $total * 100 }}%"></div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-nema-muted">
                @foreach ($warnaSentimen as $kunci => $warna)
                    <span class="flex items-center gap-2">
                        <span class="size-3 shrink-0 rounded-sm {{ $warna }}" aria-hidden="true"></span>
                        {{ $namaSentimen[$kunci] }} {{ $summary[$kunci] ?? 0 }}
                    </span>
                @endforeach
            </div>

            @php
                // Bagian layanan diurutkan dari yang paling banyak masukan negatifnya, karena itu
                // yang pertama perlu ditindaklanjuti pengelola.
                $perKategori = $byCategory->groupBy('category')
                    ->sortByDesc(fn ($baris) => $baris->firstWhere('sentiment', 'negative')->total ?? 0);
                $terbanyak = $perKategori->max(fn ($baris) => $baris->sum('total')) ?: 1;
            @endphp

            <h2 class="mt-12 text-xl sm:text-2xl">Bagian mana yang paling banyak dikeluhkan?</h2>

            <ul class="mt-4 space-y-4">
                {{-- Nama peubah di sini sengaja bukan $kategori, karena $kategori dipakai
                     untuk saringan yang sedang aktif di daftar bawah. --}}
                @foreach ($perKategori as $bagian => $baris)
                    @php $jumlahKategori = $baris->sum('total'); @endphp

                    <li>
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                            <span>{{ $namaKategori[$bagian] ?? $bagian }}</span>
                            @php
                                // Nada yang jumlahnya nol tidak ditulis, supaya tidak ada koma menggantung.
                                $rincian = collect($namaSentimen)
                                    ->map(fn ($label, $kunci) => ($baris->firstWhere('sentiment', $kunci)->total ?? 0)
                                        ? ($baris->firstWhere('sentiment', $kunci)->total . ' ' . mb_strtolower($label))
                                        : null)
                                    ->filter()
                                    ->implode(', ');
                            @endphp

                            <span class="text-sm text-nema-muted">{{ $rincian }}</span>
                        </div>

                        {{-- Panjang batang mengikuti jumlah masukan, jadi bagian dengan masukan
                             paling banyak terlihat paling panjang. --}}
                        <div class="mt-2 flex h-2.5 overflow-hidden rounded-full bg-nema-surface"
                             style="width: {{ max(12, $jumlahKategori / $terbanyak * 100) }}%">
                            @foreach ($warnaSentimen as $kunci => $warna)
                                @php $n = $baris->firstWhere('sentiment', $kunci)->total ?? 0; @endphp
                                @if ($n)
                                    <div class="{{ $warna }}" style="width: {{ $n / $jumlahKategori * 100 }}%"></div>
                                @endif
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>

            @php
                // Jumlah per kategori dan per nada, dipakai di tombol saringan supaya admin tahu
                // ada berapa masukan sebelum mengkliknya.
                $jumlahKategori = $byCategory->groupBy('category')->map(fn ($b) => $b->sum('total'));
            @endphp

            <h2 class="mt-12 text-xl sm:text-2xl">Daftar masukan</h2>

            {{-- Saringan dikirim lewat alamat, jadi hasilnya bisa ditautkan dan tetap jalan tanpa skrip. --}}
            <div class="no-scrollbar relative -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                <a href="{{ request()->fullUrlWithQuery(['kategori' => null, 'page' => null]) }}"
                   @if (! $kategori) aria-current="page" @endif
                   class="inline-flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm transition-colors {{ ! $kategori ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                    Semua bagian ({{ $total }})
                </a>

                @foreach ($namaKategori as $nilai => $label)
                    @continue (! isset($jumlahKategori[$nilai]))

                    <a href="{{ request()->fullUrlWithQuery(['kategori' => $nilai, 'page' => null]) }}"
                       @if ($kategori === $nilai) aria-current="page" @endif
                       class="inline-flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm transition-colors {{ $kategori === $nilai ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                        {{ $label }} ({{ $jumlahKategori[$nilai] }})
                    </a>
                @endforeach
            </div>

            <div class="no-scrollbar relative -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                <a href="{{ request()->fullUrlWithQuery(['nada' => null, 'page' => null]) }}"
                   @if (! $nada) aria-current="page" @endif
                   class="inline-flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm transition-colors {{ ! $nada ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                    Semua nada
                </a>

                @foreach ($namaSentimen as $nilai => $label)
                    @continue (! ($summary[$nilai] ?? 0))

                    <a href="{{ request()->fullUrlWithQuery(['nada' => $nilai, 'page' => null]) }}"
                       @if ($nada === $nilai) aria-current="page" @endif
                       class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-md px-3 text-sm transition-colors {{ $nada === $nilai ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                        @isset ($warnaSentimen[$nilai])
                            <span class="size-2.5 shrink-0 rounded-sm {{ $warnaSentimen[$nilai] }}" aria-hidden="true"></span>
                        @endisset
                        {{ $label }} ({{ $summary[$nilai] }})
                    </a>
                @endforeach
            </div>

            <p class="mt-4 text-sm text-nema-muted" aria-live="polite">
                {{ $latestFeedbacks->total() }} masukan
                {{ $kategori ? 'di bagian ' . mb_strtolower($namaKategori[$kategori]) : '' }}
                {{ $nada ? 'bernada ' . mb_strtolower($namaSentimen[$nada]) : '' }}
            </p>

            @if ($latestFeedbacks->isEmpty())
                <div class="mt-4 rounded-xl bg-nema-surface px-6 py-10 text-center">
                    <p>Tidak ada masukan yang cocok dengan saringan ini.</p>
                    <a href="{{ url('/admin/feedback') }}"
                       class="mt-4 inline-flex min-h-11 items-center rounded-md border border-nema-line px-5 text-sm transition-colors hover:bg-nema-surface-2">
                        Tampilkan semua masukan
                    </a>
                </div>
            @endif

            <ul class="mt-4 space-y-4">
                @foreach ($latestFeedbacks as $masukan)
                    <li class="rounded-xl bg-nema-surface p-5">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="font-medium">{{ $masukan->user?->name ?? 'Akun sudah dihapus' }}</span>
                            <span class="text-sm text-nema-muted">
                                {{ $namaKategori[$masukan->category] ?? $masukan->category }}
                                &middot; {{ $masukan->created_at->translatedFormat('d/m/Y H:i') }}
                            </span>

                            {{-- Nada masukan ditulis sebagai teks, bukan hanya warna, supaya tetap terbaca. --}}
                            <span class="ml-auto inline-flex min-h-7 items-center rounded-md bg-nema-surface-2 px-2.5 text-xs">
                                {{ $namaSentimen[$masukan->sentiment] ?? $masukan->sentiment }}
                                @if ($masukan->confidence)
                                    <span class="ml-1 text-nema-muted">{{ round($masukan->confidence * 100) }}% yakin</span>
                                @endif
                            </span>
                        </div>

                        <p class="mt-3 whitespace-pre-line">{{ $masukan->comment }}</p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">
                {{ $latestFeedbacks->links('partials.halaman') }}
            </div>
        @endif

    </div>

@endsection
