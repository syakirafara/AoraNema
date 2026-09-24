@extends('layouts.app')

@section('judul', 'Kelola Jadwal Tayang, AoraNema')

@section('konten')

    @include('admin.nav')

    @php
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $tanggalTeks = fn ($t) => $namaHari[$t->dayOfWeek] . ', ' . $t->day . ' ' . $namaBulan[$t->month] . ' ' . $t->year;

        // Hari-hari yang dijual ke penonton, untuk tombol tanggal di atas daftar.
        $hariDijual = collect(range(0, \App\Models\Showtime::HARI_DIJUAL - 1))->map(fn ($i) => today()->addDays($i));
    @endphp

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl">Kelola Jadwal Tayang</h1>
                <p class="mt-1 text-sm text-nema-muted">
                    {{ $tanggalTeks($tanggal) }} &middot; {{ $jumlah }} tayangan di {{ $studio->count() }} studio.
                </p>
            </div>

            @if ($bisaTambah)
                <a href="{{ url('/admin/jadwal/baru') }}?tanggal={{ $tanggal->format('Y-m-d') }}"
                   class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                    Tambah Jadwal
                </a>
            @endif
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

        @unless ($bisaTambah)
            <p class="mt-6 rounded-lg border border-nema-line bg-nema-surface p-4 text-sm text-nema-muted">
                Jadwal butuh minimal satu film yang sedang tayang dan satu studio.
                @if (! $adaFilm)
                    <a href="{{ url('/admin/film/baru') }}" class="text-nema-accent underline">Tambah film</a>.
                @endif
                @if (! $adaStudio)
                    <a href="{{ url('/admin/studio/baru') }}" class="text-nema-accent underline">Tambah studio</a>.
                @endif
            </p>
        @endunless

        {{-- Tanggal yang dijual ke penonton, ditambah kotak tanggal untuk melihat jadwal lama. --}}
        <div class="mt-8 flex flex-wrap items-end gap-2">
            @foreach ($hariDijual as $t)
                @php $aktif = $t->isSameDay($tanggal); @endphp
                <a href="{{ url('/admin/jadwal') }}?tanggal={{ $t->format('Y-m-d') }}"
                   @if ($aktif) aria-current="date" @endif
                   @class([
                       'flex min-h-11 w-20 flex-col items-center justify-center rounded-lg py-2 text-sm',
                       'border border-nema-accent bg-nema-maroon text-white' => $aktif,
                       'border border-nema-line text-nema-muted transition-colors hover:bg-nema-surface' => ! $aktif,
                   ])>
                    <span class="text-xs">{{ $loop->first ? 'Hari ini' : substr($namaHari[$t->dayOfWeek], 0, 3) }}</span>
                    <span class="font-semibold">{{ $t->format('j/n') }}</span>
                </a>
            @endforeach

            <form method="get" action="{{ url('/admin/jadwal') }}" class="flex items-end gap-2">
                <label class="block text-xs text-nema-muted">
                    Tanggal lain
                    <input type="date" name="tanggal" value="{{ $tanggal->format('Y-m-d') }}"
                           class="mt-1 block min-h-11 rounded-md border border-nema-line bg-nema-surface px-3 text-sm text-nema-text">
                </label>
                <button type="submit"
                        class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 text-sm transition-colors hover:bg-nema-surface">
                    Lihat
                </button>
            </form>
        </div>

        <p class="mt-4 max-w-prose text-xs text-nema-muted">
            Tiap tayangan memakai studio mulai jam tayang, lalu {{ \App\Models\Showtime::IKLAN_MENIT }} menit iklan,
            durasi film, dan {{ \App\Models\Showtime::JEDA_MENIT }} menit jeda bersih-bersih. Jam operasional
            {{ \App\Models\Showtime::JAM_BUKA }}–{{ \App\Models\Showtime::JAM_TERAKHIR }}.
        </p>

        <div class="mt-6 space-y-6">
            @foreach ($studio as $s)
                @php $daftar = $jadwal->get($s->id, collect()); @endphp

                <section class="rounded-xl border border-nema-line/60">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-nema-line/40 px-4 py-3">
                        <h2 class="text-lg">{{ $s->name }}</h2>
                        <p class="text-sm text-nema-muted">
                            {{ $s->format }} &middot; {{ $s->kapasitas() }} kursi &middot;
                            Rp {{ number_format($s->hargaUntuk($tanggal), 0, ',', '.') }}
                        </p>
                    </div>

                    @if ($daftar->isEmpty())
                        <p class="px-4 py-5 text-sm text-nema-muted">Tidak ada tayangan di tanggal ini.</p>
                    @else
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-2xl text-left text-sm">
                                <thead class="text-nema-muted">
                                    <tr>
                                        <th class="px-4 py-2 font-normal">Jam tayang</th>
                                        <th class="py-2 pr-4 font-normal">Film</th>
                                        <th class="py-2 pr-4 font-normal">Studio siap lagi</th>
                                        <th class="py-2 pr-4 font-normal">Terisi</th>
                                        <th class="py-2 pr-4 font-normal"><span class="sr-only">Tindakan</span></th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-nema-line/40 border-t border-nema-line/40">
                                    @foreach ($daftar as $j)
                                        @php $terisi = count($j->kursiTerisi()); @endphp
                                        <tr @class(['text-nema-muted' => ! $j->masihDijual()])>
                                            <td class="px-4 py-3">
                                                {{ $j->show_time->format('H:i') }}–{{ $j->selesai()->format('H:i') }}
                                            </td>

                                            <td class="py-3 pr-4">
                                                {{ $j->movie->title }}
                                                <span class="block text-xs text-nema-muted">
                                                    {{ $j->movie->durasi() }} menit{{ $j->movie->is_showing ? '' : ' · diarsipkan' }}
                                                </span>
                                            </td>

                                            <td class="py-3 pr-4 text-nema-muted">{{ $j->studioSiap()->format('H:i') }}</td>

                                            <td class="py-3 pr-4 text-nema-muted">
                                                {{ $terisi }}/{{ $s->kapasitas() }}
                                            </td>

                                            <td class="py-3 pr-4">
                                                @if (! $j->masihDijual())
                                                    <p class="text-right text-sm text-nema-muted">Sudah mulai</p>
                                                @elseif ($terisi)
                                                    {{-- Jadwal yang sudah dipesan tidak bisa diubah atau dihapus, supaya tiket penonton tetap sah. --}}
                                                    <p class="text-right text-sm text-nema-muted">Terkunci, sudah dipesan</p>
                                                @else
                                                    <div class="flex justify-end gap-2">
                                                        <a href="{{ url('/admin/jadwal/' . $j->id . '/ubah') }}"
                                                           class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                                            Ubah
                                                        </a>

                                                        <form method="post" action="{{ url('/admin/jadwal/' . $j->id) }}"
                                                              data-konfirmasi="Hapus jadwal {{ $j->movie->title }} jam {{ $j->show_time->format('H:i') }}?"
                                                              onsubmit="return confirm(this.dataset.konfirmasi)">
                                                            @csrf
                                                            @method('delete')

                                                            <button type="submit"
                                                                    class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface">
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

    </div>

@endsection
