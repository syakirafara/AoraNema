@extends('layouts.app')

@section('judul', 'Semua Film, AoraNema')

@section('konten')

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">

        <h1 class="text-2xl sm:text-3xl">Semua Film</h1>

        {{-- Pencarian, saringan, urutan, dan tampilan dikirim lewat alamat, bukan JavaScript,
             supaya hasilnya bisa ditautkan ke orang lain dan tetap jalan tanpa skrip. --}}
        <form method="get" action="{{ url('/film') }}" class="mt-6 flex flex-wrap gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="urut" value="{{ $urut }}">
            <input type="hidden" name="tampilan" value="{{ $tampilan }}">

            <label for="cari" class="sr-only">Cari judul film</label>
            <input type="search" id="cari" name="cari" value="{{ $cari }}"
                   placeholder="Cari judul film"
                   class="min-h-11 min-w-0 flex-1 basis-48 rounded-md border border-nema-line bg-nema-surface px-4">

            <button type="submit"
                    class="inline-flex min-h-11 shrink-0 items-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover sm:px-6">
                Cari
            </button>

            @if ($cari !== '' || $status !== 'semua')
                <a href="{{ url('/film') }}{{ $tampilan === 'baris' ? '?tampilan=baris' : '' }}"
                   class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                    Bersihkan
                </a>
            @endif
        </form>

        {{-- Di HP baris status digeser ke samping, bukan turun ke baris kedua. --}}
        <div class="no-scrollbar -mx-4 mt-6 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
            @foreach (['semua' => 'Semua', 'tayang' => 'Sedang Tayang', 'segera' => 'Akan Tayang'] as $nilai => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $nilai === 'semua' ? null : $nilai]) }}"
                   @if ($status === $nilai) aria-current="page" @endif
                   class="inline-flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm transition-colors sm:px-4 {{ $status === $nilai ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between gap-4">

            @php $namaUrut = ['terbaru' => 'Terbaru', 'az' => 'Judul A–Z', 'za' => 'Judul Z–A']; @endphp

            {{-- <details> dipakai sebagai daftar pilihan urutan: terbuka dan tertutup tanpa
                 JavaScript, dan bisa dibuka dengan Tab lalu Enter. --}}
            <details class="relative">
                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-md px-2 text-sm text-nema-muted transition-colors hover:text-nema-text [&::-webkit-details-marker]:hidden">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m7 15 5 5 5-5M7 9l5-5 5 5" />
                    </svg>
                    <span><span class="sr-only">Urutkan: </span>{{ $namaUrut[$urut] }}</span>
                </summary>

                <div class="absolute left-0 z-20 mt-2 w-52 overflow-hidden rounded-lg border border-nema-line bg-nema-surface py-1 shadow-xl shadow-black/40">
                    @foreach ($namaUrut as $nilai => $label)
                        <a href="{{ request()->fullUrlWithQuery(['urut' => $nilai === 'terbaru' ? null : $nilai]) }}"
                           @if ($urut === $nilai) aria-current="true" @endif
                           class="flex min-h-11 items-center justify-between gap-3 px-4 text-sm transition-colors hover:bg-nema-surface-2 {{ $urut === $nilai ? 'text-nema-text' : 'text-nema-muted' }}">
                            {{ $label }}
                            @if ($urut === $nilai)
                                <svg class="size-4 text-nema-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </details>

            {{-- Satu tombol yang menukar tampilan. Ikonnya menunjukkan tampilan tujuan,
                 sama seperti labelnya, jadi yang terlihat dan yang dibacakan tidak bertentangan. --}}
            @php $tujuan = $tampilan === 'kotak' ? 'baris' : 'kotak'; @endphp
            <a href="{{ request()->fullUrlWithQuery(['tampilan' => $tujuan === 'kotak' ? null : $tujuan]) }}"
               aria-label="Ganti ke tampilan {{ $tujuan }}" title="Ganti ke tampilan {{ $tujuan }}"
               class="inline-flex size-11 items-center justify-center rounded-md border border-nema-line text-nema-muted transition-colors hover:bg-nema-surface hover:text-nema-text">
                @if ($tujuan === 'kotak')
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="4" width="6.5" height="6.5" rx="1" />
                        <rect x="13.5" y="4" width="6.5" height="6.5" rx="1" />
                        <rect x="4" y="13.5" width="6.5" height="6.5" rx="1" />
                        <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1" />
                    </svg>
                @else
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M9 6h11M9 12h11M9 18h11" />
                        <path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01" stroke-width="3" />
                    </svg>
                @endif
            </a>

        </div>

        <p class="mt-4 text-sm text-nema-muted" aria-live="polite">
            {{ count($film) }} film ditemukan{{ $cari !== '' ? ' untuk pencarian "' . $cari . '"' : '' }}.
        </p>

        @if (count($film))

            @if ($tampilan === 'kotak')
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-5">
                    @foreach ($film as $f)
                        @include('partials.kartu-film', ['f' => $f])
                    @endforeach
                </div>
            @else
                <ul class="mt-4 divide-y divide-nema-line/40">
                    @foreach ($film as $f)
                        <li>
                            <a href="{{ url('/film/' . $f['slug']) }}" class="group flex items-center gap-4 py-4 sm:gap-6">
                                <div class="w-20 shrink-0 overflow-hidden rounded-lg bg-nema-surface-2 sm:w-24">
                                    @if ($f['poster'])
                                        <img src="{{ $f['poster'] }}" alt="" loading="lazy"
                                             class="aspect-2/3 w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                    @else
                                        <div class="aspect-2/3 w-full"></div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <h2 class="text-lg leading-snug transition-colors group-hover:text-nema-accent sm:text-xl">
                                        {{ $f['judul'] }}
                                    </h2>

                                    @if ($f['genre'])
                                        <p class="mt-1 truncate text-sm text-nema-muted">{{ $f['genre'] }}</p>
                                    @endif

                                    @include('partials.keterangan-film', ['f' => $f])
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

        @else
            <div class="mt-6 rounded-xl border border-nema-line bg-nema-surface p-8 text-center">
                <p>Tidak ada film yang cocok.</p>
                <p class="mt-2 text-sm text-nema-muted">
                    Coba kata kunci lain, atau ganti saringan statusnya.
                </p>
                <a href="{{ url('/film') }}"
                   class="mt-6 inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface-2">
                    Tampilkan semua film
                </a>
            </div>
        @endif

    </div>

@endsection
