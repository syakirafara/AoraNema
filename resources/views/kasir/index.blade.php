@extends('layouts.app')

@section('judul', 'Loket Kasir, AoraNema')

@section('konten')

    @php
        $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    @endphp

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <h1 class="text-2xl sm:text-3xl">Loket Kasir</h1>
        <p class="mt-1 text-sm text-nema-muted">
            {{ auth()->user()->name }}. Pilih jam tayang, pilih kursi, lalu terima pembayaran tunai.
        </p>

        @if (session('error'))
            <p role="alert" class="mt-6 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                {{ session('error') }}
            </p>
        @endif

        {{-- Penjualan loket kasir ini hari ini. --}}
        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Transaksi hari ini</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $penjualan->count() }}</dd>
            </div>
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Tiket terjual hari ini</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $tiketTerjual }}</dd>
            </div>
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Uang tunai diterima</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $rp($pendapatan) }}</dd>
            </div>
        </dl>

        <div class="no-scrollbar mt-8 flex gap-2 overflow-x-auto pb-2">
            @foreach ($daftarTanggal as $t)
                @php $aktif = $t->isSameDay($tanggal); @endphp
                <a href="{{ url('/kasir') }}?tanggal={{ $t->format('Y-m-d') }}"
                   @if ($aktif) aria-current="date" @endif
                   @class([
                       'flex min-h-11 w-20 shrink-0 flex-col items-center justify-center rounded-lg py-2',
                       'border border-nema-accent bg-nema-maroon text-white' => $aktif,
                       'border border-nema-line text-nema-muted transition-colors hover:bg-nema-surface' => ! $aktif,
                   ])>
                    <span class="text-xs">{{ $loop->first ? 'Hari ini' : $namaHari[$t->dayOfWeek] }}</span>
                    <span class="text-lg font-semibold">{{ $t->format('j') }}</span>
                </a>
            @endforeach
        </div>

        {{-- Satu kartu per film. Jam tayangnya dikelompokkan per format layar, dan tombolnya cukup berisi
             jam. Studio tertulis di halaman kursi dan di tiket; sisa kursi baru ditulis kalau tinggal sedikit. --}}
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @forelse ($film as $f)
                <section class="flex gap-4 rounded-xl bg-nema-surface p-4">
                    <div class="w-16 shrink-0">
                        @if ($f->alamatPoster())
                            <img src="{{ $f->alamatPoster() }}" alt="" class="aspect-2/3 w-full rounded-md object-cover">
                        @else
                            <div class="aspect-2/3 w-full rounded-md bg-nema-surface-2"></div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg leading-tight">{{ $f->title }}</h2>
                        <p class="mt-1 text-xs text-nema-muted">{{ $f->durasi() }} menit{{ $f->usia ? ' · ' . $f->usia : '' }}</p>

                        <div class="mt-3 space-y-3">
                            @foreach ($f->showtimes->groupBy(fn ($j) => $j->studio->format)->sortBy(fn ($jam, $format) => array_search($format, \App\Models\Studio::FORMAT)) as $format => $daftarJam)
                                <div>
                                    <p class="text-xs text-nema-muted">{{ $format }}</p>

                                    <div class="mt-1.5 flex flex-wrap gap-2">
                                        @foreach ($daftarJam as $j)
                                            @php $sisa = $j->sisaKursi(); @endphp

                                            @if ($sisa > 0)
                                                <a href="{{ url('/kasir/jadwal/' . $j->id) }}"
                                                   title="{{ $j->studio->name }}, sisa {{ $sisa }} kursi"
                                                   aria-label="{{ $j->show_time->format('H:i') }}, {{ $j->studio->name }}, sisa {{ $sisa }} kursi"
                                                   class="inline-flex min-h-11 min-w-18 flex-col items-center justify-center rounded-md border border-nema-line px-3 transition-colors hover:border-nema-accent hover:bg-nema-surface-2">
                                                    {{ $j->show_time->format('H:i') }}
                                                    @if ($sisa <= 10)
                                                        <span class="text-[11px] leading-tight text-nema-accent">sisa {{ $sisa }}</span>
                                                    @endif
                                                </a>
                                            @else
                                                <span aria-label="{{ $j->show_time->format('H:i') }}, penuh"
                                                      class="inline-flex min-h-11 min-w-18 flex-col items-center justify-center rounded-md border border-nema-line/40 px-3 text-nema-muted/50">
                                                    {{ $j->show_time->format('H:i') }}
                                                    <span class="text-[11px] leading-tight">Penuh</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @empty
                <p class="col-span-full rounded-xl bg-nema-surface py-10 text-center text-nema-muted">Tidak ada jadwal yang masih dijual di tanggal ini.</p>
            @endforelse
        </div>

        <h2 class="mt-12 text-xl">Transaksi hari ini</h2>

        <div class="relative mt-4 overflow-x-auto">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-nema-line/40 text-nema-muted">
                    <tr>
                        <th class="py-3 pr-4 font-normal">Kode</th>
                        <th class="py-3 pr-4 font-normal">Waktu</th>
                        <th class="py-3 pr-4 font-normal">Film dan jadwal</th>
                        <th class="py-3 pr-4 font-normal">Kursi</th>
                        <th class="py-3 font-normal">Total</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-nema-line/40">
                    @forelse ($penjualan as $p)
                        <tr>
                            <td class="py-3 pr-4">
                                {{-- Tiket bisa dibuka lagi untuk dicetak ulang. --}}
                                <a href="{{ url('/tiket/' . $p->booking_code) }}" class="font-mono text-nema-accent underline">{{ $p->booking_code }}</a>
                            </td>
                            <td class="py-3 pr-4 text-nema-muted">{{ $p->created_at->format('H:i') }}</td>
                            <td class="py-3 pr-4">
                                {{ $p->showtime->movie->title }}
                                <span class="block text-xs text-nema-muted">
                                    {{ $p->showtime->show_time->format('d/m H:i') }} &middot; {{ $p->showtime->studio->label() }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">{{ implode(', ', $p->kursi) }}</td>
                            <td class="py-3">{{ $rp($p->total_price) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-nema-muted">Belum ada penjualan hari ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
