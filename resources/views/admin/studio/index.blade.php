@extends('layouts.app')

@section('judul', 'Kelola Studio, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl">Kelola Studio</h1>
                <p class="mt-1 max-w-prose text-sm text-nema-muted">
                    Nama studio sekaligus menjadi format layarnya, misalnya Regular 2D atau IMAX.
                    Itu yang nanti tampil di halaman jadwal.
                </p>
            </div>

            <a href="{{ url('/admin/studio/baru') }}"
               class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                Tambah Studio
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

        <div class="relative mt-8 overflow-x-auto">
            <table class="w-full min-w-2xl text-left text-sm">
                <thead class="border-b border-nema-line/40 text-nema-muted">
                    <tr>
                        <th class="py-3 pr-4 font-normal">Nama</th>
                        <th class="py-3 pr-4 font-normal">Format</th>
                        <th class="py-3 pr-4 font-normal">Kursi</th>
                        <th class="py-3 pr-4 font-normal">Harga biasa / akhir pekan</th>
                        <th class="py-3 pr-4 font-normal">Jadwal terpasang</th>
                        <th class="py-3 font-normal"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-nema-line/40">
                    @forelse ($studio as $s)
                        <tr>
                            <td class="py-4 pr-4">{{ $s->name }}</td>
                            <td class="py-4 pr-4 text-nema-muted">{{ $s->format }}</td>

                            <td class="py-4 pr-4 text-nema-muted">
                                {{ $s->kapasitas() }} kursi
                            </td>

                            <td class="py-4 pr-4 text-nema-muted">
                                Rp {{ number_format($s->harga_biasa, 0, ',', '.') }} /
                                Rp {{ number_format($s->harga_akhir_pekan, 0, ',', '.') }}
                            </td>

                            <td class="py-4 pr-4 text-nema-muted">{{ $s->showtimes_count }}</td>

                            <td class="py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ url('/admin/studio/' . $s->id . '/ubah') }}"
                                       class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                        Ubah
                                    </a>

                                    <form method="post" action="{{ url('/admin/studio/' . $s->id) }}"
                                          data-konfirmasi="Hapus studio &quot;{{ $s->name }}&quot;? Kursi di dalamnya ikut terhapus."
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
                                Belum ada studio.
                                <a href="{{ url('/admin/studio/baru') }}" class="text-nema-accent underline">Tambah studio pertama</a>.
                                Jadwal tayang tidak bisa dibuat sebelum ada studio.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
