@extends('layouts.app')

@section('judul', 'Kelola Film, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl">Kelola Film</h1>
                <p class="mt-1 max-w-prose text-sm text-nema-muted">
                    Film yang sudah tidak layak tayang diarsipkan, bukan dihapus. Pengunjung berhenti
                    melihatnya dan tiketnya berhenti dijual, tapi jadwal dan riwayat penjualannya tetap
                    tersimpan, jadi film bisa ditayangkan lagi kapan saja.
                </p>
            </div>

            <a href="{{ url('/admin/film/baru') }}"
                class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                Tambah Film
            </a>
        </div>

        @if (session('sukses'))
            <p class="mt-6 rounded-lg border border-nema-accent bg-nema-surface px-4 py-3 text-sm">
                {{ session('sukses') }}
            </p>
        @endif

        @if (session('gagal'))
            <p class="mt-6 rounded-lg border border-nema-line bg-nema-surface px-4 py-3 text-sm">
                {{ session('gagal') }}
            </p>
        @endif

        <div class="mt-8 flex flex-wrap gap-2">
            @foreach (['semua' => 'Semua', 'tayang' => 'Tayang (' . $jumlahTayang . ')', 'arsip' => 'Arsip (' . $jumlahArsip . ')'] as $nilai => $label)
                <a href="{{ url('/admin/film') }}{{ $nilai === 'semua' ? '' : '?status=' . $nilai }}"
                   @if ($saringan === $nilai) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center rounded-md px-4 text-sm transition-colors {{ $saringan === $nilai ? 'border border-nema-accent bg-nema-maroon text-white' : 'border border-nema-line text-nema-muted hover:bg-nema-surface' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="relative mt-6 overflow-x-auto">
            <table class="w-full min-w-4xl text-left text-sm">
                <thead class="border-b border-nema-line/40 text-nema-muted">
                    <tr>
                        <th class="py-3 pr-4 font-normal">Judul</th>
                        <th class="py-3 pr-4 font-normal">Genre</th>
                        <th class="py-3 pr-4 font-normal">Tiket terjual</th>
                        <th class="py-3 pr-4 font-normal">Jadwal mendatang</th>
                        <th class="py-3 pr-4 font-normal">Status</th>
                        <th class="py-3 font-normal"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-nema-line/40">
                    @forelse ($film as $f)
                        <tr @class(['opacity-60' => ! $f->is_showing])>
                            <td class="py-4 pr-4">
                                {{ $f->title }}
                                @if ($f->release_date)
                                    <span class="block text-xs text-nema-muted">
                                        Rilis {{ \Illuminate\Support\Carbon::parse($f->release_date)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </td>

                            <td class="py-4 pr-4 text-nema-muted">
                                {{ $f->genres->pluck('name')->join(', ') ?: '—' }}
                            </td>

                            <td class="py-4 pr-4">{{ $f->tiket_terjual }}</td>

                            <td class="py-4 pr-4 text-nema-muted">{{ $f->jadwal_mendatang }}</td>

                            <td class="py-4 pr-4">
                                {{ $f->is_showing ? 'Tayang' : 'Arsip' }}
                            </td>

                            <td class="py-4">
                                <div class="flex justify-end gap-2">
                                    <form method="post" action="{{ url('/admin/film/' . $f->id . '/arsip') }}">
                                        @csrf

                                        <button type="submit"
                                                class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                            {{ $f->is_showing ? 'Arsipkan' : 'Tayangkan' }}
                                        </button>
                                    </form>

                                    <a href="{{ url('/admin/film/' . $f->id . '/ubah') }}"
                                       class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                        Ubah
                                    </a>

                                    <form method="post" action="{{ url('/admin/film/' . $f->id) }}"
                                          data-konfirmasi="Hapus film &quot;{{ $f->title }}&quot;? Tindakan ini tidak bisa dibatalkan. Untuk menarik film dari peredaran, pakai Arsipkan."
                                          onsubmit="return confirm(this.dataset.konfirmasi)">
                                        @csrf
                                        @method('delete')

                                        <button type="submit"
                                                class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-nema-muted">
                                @if ($saringan === 'arsip')
                                    Belum ada film yang diarsipkan.
                                @elseif ($saringan === 'tayang')
                                    Tidak ada film yang sedang tayang.
                                @else
                                    Belum ada film di database.
                                    <a href="{{ url('/admin/film/baru') }}" class="text-nema-accent underline">Tambah film pertama</a>,
                                    atau jalankan seeder TMDB kalau kunci API-nya sudah dipasang.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-6 max-w-prose text-xs text-nema-muted">
            Tiket terjual dihitung dari kursi di pesanan yang sudah lunas. Jadwal mendatang adalah
            jadwal yang belum lewat, termasuk jadwal film yang diarsipkan.
        </p>

        <div class="mt-8">
            {{ $film->links('partials.halaman') }}
        </div>

    </div>

@endsection
