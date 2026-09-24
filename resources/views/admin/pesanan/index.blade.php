@extends('layouts.app')

@section('judul', 'Daftar Pesanan, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

        <h1 class="text-2xl sm:text-3xl">Daftar Pesanan</h1>
        <p class="mt-1 max-w-prose text-sm text-nema-muted">
            {{ $pesanan->total() }} pesanan tercatat. Halaman ini hanya membaca, tidak mengubah apa pun.
        </p>

        <div class="relative mt-8 overflow-x-auto">
            <table class="w-full min-w-3xl text-left text-sm">
                <thead class="border-b border-nema-line/40 text-nema-muted">
                    <tr>
                        <th class="py-3 pr-4 font-normal">Waktu pesan</th>
                        <th class="py-3 pr-4 font-normal">Pemesan</th>
                        <th class="py-3 pr-4 font-normal">Film</th>
                        <th class="py-3 pr-4 font-normal">Jadwal</th>
                        <th class="py-3 pr-4 font-normal">Kursi</th>
                        <th class="py-3 pr-4 font-normal">Total</th>
                        <th class="py-3 font-normal">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-nema-line/40">
                    @forelse ($pesanan as $p)
                        <tr>
                            <td class="py-4 pr-4 text-nema-muted">
                                {{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}
                            </td>

                            <td class="py-4 pr-4">{{ $p->user?->name ?? '—' }}</td>

                            <td class="py-4 pr-4">{{ $p->showtime?->movie?->title ?? '—' }}</td>

                            <td class="py-4 pr-4 text-nema-muted">
                                @if ($p->showtime)
                                    {{ \Illuminate\Support\Carbon::parse($p->showtime->show_time)->format('d/m H:i') }}
                                    &middot; {{ $p->showtime->studio?->label() ?? '—' }}
                                @else
                                    &mdash;
                                @endif
                            </td>

                            <td class="py-4 pr-4">{{ $p->seats->pluck('seat_number')->sort(SORT_NATURAL)->implode(', ') ?: '—' }}</td>

                            <td class="py-4 pr-4 text-nema-muted">
                                Rp {{ number_format($p->total_price, 0, ',', '.') }}
                            </td>

                            <td class="py-4">{{ $p->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-nema-muted">
                                Belum ada pesanan. Pesanan baru bisa masuk setelah sistem akun jadi,
                                karena tiap pesanan wajib punya pemesan.
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
