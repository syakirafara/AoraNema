@extends('layouts.app')

@section('judul', 'Daftar Pesanan, AoraNema')

@section('konten')

    @include('admin.nav')

    @php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <h1 class="text-2xl sm:text-3xl">Daftar Pesanan</h1>

        {{-- Ringkasan penjualan hari ini, dari pesanan yang sudah lunas. --}}
        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Pendapatan hari ini</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $rp($pendapatanHariIni) }}</dd>
            </div>
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Tiket terjual hari ini</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $tiketHariIni }}</dd>
            </div>
            <div class="rounded-xl bg-nema-surface p-4">
                <dt class="text-sm text-nema-muted">Pesanan lunas hari ini</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $pesananHariIni }}</dd>
            </div>
        </dl>

        <form method="get" action="{{ url('/admin/pesanan') }}" class="mt-8 flex flex-wrap items-end gap-3">
            <label class="block min-w-60 flex-1 text-sm">
                Cari kode pesanan, nama, atau email
                <input type="search" name="cari" value="{{ $cari }}" maxlength="100"
                       class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
            </label>

            <label class="block text-sm">
                Status
                <select name="status" class="mt-2 block min-h-11 rounded-md border border-nema-line bg-nema-surface px-4">
                    <option value="semua">Semua</option>
                    @foreach (\App\Models\Booking::NAMA_STATUS as $nilai => $nama)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $nama }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit"
                    class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                Tampilkan
            </button>
        </form>

        <p class="mt-4 text-sm text-nema-muted">{{ $pesanan->total() }} pesanan.</p>

        <div class="relative mt-4 overflow-x-auto">
            <table class="w-full min-w-4xl text-left text-sm">
                <thead class="border-b border-nema-line/40 text-nema-muted">
                    <tr>
                        <th class="py-3 pr-4 font-normal">Kode</th>
                        <th class="py-3 pr-4 font-normal">Waktu pesan</th>
                        <th class="py-3 pr-4 font-normal">Pemesan</th>
                        <th class="py-3 pr-4 font-normal">Film dan jadwal</th>
                        <th class="py-3 pr-4 font-normal">Kursi</th>
                        <th class="py-3 pr-4 font-normal">Total</th>
                        <th class="py-3 font-normal">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-nema-line/40">
                    @forelse ($pesanan as $p)
                        <tr>
                            <td class="py-4 pr-4">
                                <a href="{{ url('/tiket/' . $p->booking_code) }}" class="font-mono text-nema-accent underline">
                                    {{ $p->booking_code }}
                                </a>
                            </td>

                            <td class="py-4 pr-4 text-nema-muted">{{ $p->created_at->format('d/m/Y H:i') }}</td>

                            <td class="py-4 pr-4">
                                {{ $p->user?->name ?? '—' }}
                                <span class="block text-xs text-nema-muted">{{ $p->user?->email }}</span>
                            </td>

                            <td class="py-4 pr-4">
                                {{ $p->showtime->movie->title }}
                                <span class="block text-xs text-nema-muted">
                                    {{ $p->showtime->show_time->format('d/m H:i') }} &middot; {{ $p->showtime->studio->label() }}
                                </span>
                            </td>

                            <td class="py-4 pr-4">{{ implode(', ', $p->kursi) }}</td>

                            <td class="py-4 pr-4 text-nema-muted">{{ $rp($p->total_price) }}</td>

                            <td class="py-4">{{ $p->namaStatus() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-nema-muted">
                                {{ $cari !== '' || $status !== 'semua' ? 'Tidak ada pesanan yang cocok.' : 'Belum ada pesanan.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $pesanan->links('partials.halaman') }}
        </div>

    </div>

@endsection
