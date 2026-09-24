@extends('layouts.app')

@section('judul', $film['judul'] . ', AoraNema')

@section('konten')

    @php
        $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $namaBulan = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        $tanggalIndo = function (string $iso) use ($namaBulan) {
            [$tahun, $bln, $hari] = explode('-', $iso);
            return (int) $hari . ' ' . $namaBulan[(int) $bln] . ' ' . $tahun;
        };

        $hariIni = now()->startOfDay();

        $daftarTanggal = [];
        for ($i = 0; $i < 6; $i++) {
            $daftarTanggal[] = $hariIni->copy()->addDays($i);
        }

        $adaPoster = !empty($film['poster']);
    @endphp



    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">

        {{-- data-kembali: kalau penonton datang dari halaman lain di AoraNema, tautan ini kembali
             ke halaman itu di posisi guliran yang sama. Kalau datang dari luar, ke beranda. --}}
        <a href="{{ url('/') }}" data-kembali
            class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Kembali
        </a>

        <div class="mt-4 grid gap-10 lg:grid-cols-[320px_1fr] lg:gap-16">

            {{-- Kolom kiri menempel saat halaman digulir, jadi poster dan judul selalu
                 terlihat sementara jadwalnya digulir di sebelah kanan. --}}
            <div class="lg:sticky lg:top-24 lg:self-start">

                <div class="flex gap-6 sm:gap-8 lg:block">

                    <div class="w-32 shrink-0 sm:w-40 lg:w-full">
                        @if ($adaPoster)
                            <img src="{{ $film['poster'] }}" alt="Poster film {{ $film['judul'] }}"
                                class="aspect-2/3 w-full rounded-xl object-cover">
                        @else
                            <div class="flex aspect-2/3 w-full items-end rounded-xl bg-nema-surface-2 p-4">
                                <span class="text-xs text-nema-muted">Poster belum tersedia</span>
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 lg:mt-6">
                        <h1 class="text-2xl leading-tight sm:text-3xl">{{ $film['judul'] }}</h1>

                        @if ($film['tagline'])

                            <p class="mt-2 text-nema-muted">{{ $film['tagline'] }}</p>

                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            @if ($film['genre'])
                                <span class="rounded-md bg-nema-surface px-3 py-1.5">{{ $film['genre'] }}</span>
                            @endif
                            @if ($film['durasi'])
                                <span class="rounded-md bg-nema-surface px-3 py-1.5">{{ $film['durasi'] }} menit</span>
                            @endif
                            @if ($film['usia'])
                                <span class="rounded-md bg-nema-maroon px-3 py-1.5 font-medium text-white">{{ $film['usia'] }}</span>
                            @endif
                        </div>

                    </div>

                </div>
            </div>

            <div class="min-w-0">

                @if ($film['mulai'])

                    <div class="rounded-xl border border-nema-line bg-nema-surface p-6 sm:p-8">
                        <h2 class="text-xl sm:text-2xl">Belum tayang</h2>
                        <p class="mt-3 max-w-prose text-nema-muted">
                            Film ini mulai tayang {{ $tanggalIndo($film['mulai']) }}.
                            Jadwal dan pemesanan kursi dibuka mendekati tanggal itu.
                        </p>
                    </div>
                @else
                    {{-- relative: keterangan sr-only di dalam kartu tanggal memakai posisi absolut, dan tanpa
                         pembungkus relative ia keluar dari baris ini lalu melebarkan seluruh halaman. --}}
                    <div class="no-scrollbar relative mt-6 flex gap-2 overflow-x-auto pb-2">
                        @foreach ($daftarTanggal as $t)
                            @php
                                $aktif = $t->format('Y-m-d') === $tanggal->format('Y-m-d');
                                $kosong = ! in_array($t->format('Y-m-d'), $tanggalBerjadwal);
                            @endphp

                            {{-- Tanggal tanpa jadwal diredupkan supaya penonton tahu sebelum mengkliknya. --}}
                            <a href="{{ url('/film/' . $film['slug']) }}?tanggal={{ $t->format('Y-m-d') }}"
                                @if ($aktif) aria-current="date" @endif
                                @class([
                                    'flex min-h-11 w-20 shrink-0 flex-col items-center justify-center rounded-lg py-2',
                                    'border border-nema-accent bg-nema-maroon text-white' => $aktif,
                                    'border border-nema-line text-nema-muted transition-colors hover:bg-nema-surface' => ! $aktif,
                                    'opacity-40' => $kosong && ! $aktif,
                                ])>
                                <span class="text-xs">{{ $loop->first ? 'Hari ini' : $namaHari[$t->dayOfWeek] }}</span>
                                <span class="text-lg font-semibold">{{ $t->format('j') }}</span>
                                @if ($kosong)
                                    <span class="sr-only">, tidak ada jadwal</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    @php $semuaLewat = $jadwal->isNotEmpty() && $jadwal->flatten()->every(fn ($j) => $j->show_time->isPast()); @endphp

                    @if ($jadwal->isEmpty())
                        <div class="mt-4 rounded-xl border border-nema-line bg-nema-surface p-6">
                            @php $berikutnya = collect($tanggalBerjadwal)->first(fn ($t) => $t > $tanggal->format('Y-m-d')) ?? ($tanggalBerjadwal[0] ?? null); @endphp

                            <p>Belum ada jadwal tayang di tanggal ini.</p>

                            @if ($berikutnya)
                                @php $b = \Illuminate\Support\Carbon::parse($berikutnya); @endphp
                                <a href="{{ url('/film/' . $film['slug']) }}?tanggal={{ $berikutnya }}"
                                   class="mt-4 inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 text-sm font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                                    Lihat jadwal {{ $b->isTomorrow() ? 'besok' : $namaHari[$b->dayOfWeek] . ' ' . $b->day }}
                                </a>
                            @else
                                <p class="mt-2 text-sm text-nema-muted">Film ini belum punya jadwal dalam enam hari ke depan.</p>
                            @endif
                        </div>
                    @else
                    <div class="mt-4 divide-y divide-nema-line/40 border-y border-nema-line/40">
                        @foreach ($jadwal as $layar => $daftarJam)
                            <div class="py-5" data-baris>

                                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <h3 class="text-base">{{ $layar }}</h3>
                                    {{-- Harga ditentukan studio dan harinya, jadi semua jam di baris ini sama harganya. --}}
                                    @php
                                        $harga = $daftarJam->map->harga();
                                        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
                                    @endphp
                                    {{-- Beberapa studio bisa berformat sama tapi bertarif berbeda, jadi yang
                                         ditulis rentangnya. --}}
                                    <p class="text-sm text-nema-muted">
                                        {{ $harga->min() === $harga->max() ? $rp($harga->min()) : $rp($harga->min()) . ' – ' . $rp($harga->max()) }}
                                    </p>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($daftarJam as $j)
                                        {{-- Jam yang sudah lewat tetap terlihat supaya jadwal hari ini utuh,
                                             tapi dimatikan karena sudah tidak bisa dipesan. --}}
                                        <button type="button" data-jam="{{ $j->show_time->format('H:i') }}"
                                            data-jadwal="{{ $j->id }}" data-harga="{{ $j->harga() }}" aria-pressed="false"
                                            @disabled($j->show_time->isPast())
                                            class="inline-flex min-h-11 min-w-20 items-center justify-center rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface aria-pressed:border-nema-accent aria-pressed:bg-nema-maroon aria-pressed:text-white disabled:cursor-not-allowed disabled:border-nema-line/40 disabled:text-nema-muted/50 disabled:hover:bg-transparent">
                                            {{ $j->show_time->format('H:i') }}
                                            @if ($j->show_time->isPast())
                                                <span class="sr-only">, sudah lewat</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Panel pemesanan muncul tepat di bawah jam yang dipilih.
                                     Latarnya nema-surface, bukan surface-2, karena garis tombol
                                     di atas surface-2 cuma 2,96:1 dan itu di bawah ambang 3:1. --}}
                                <div data-panel hidden class="mt-4 rounded-lg bg-nema-surface p-4 sm:p-5">

                                    <p class="text-sm text-nema-muted">
                                        {{ $layar }}, <span data-ringkas-jam></span> &middot;
                                        {{ $tanggalIndo($tanggal->format('Y-m-d')) }}
                                    </p>

                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">

                                        <div class="flex items-center gap-3">
                                            <span class="text-sm">Jumlah tiket</span>

                                            <div class="flex items-center gap-2">
                                                <button type="button" data-kurang aria-label="Kurangi jumlah tiket"
                                                    class="inline-flex size-11 items-center justify-center rounded-md border border-nema-line text-lg transition-colors hover:bg-nema-surface-2 disabled:opacity-40 disabled:hover:bg-transparent">
                                                    &minus;
                                                </button>

                                                <output data-jumlah aria-live="polite"
                                                    class="w-8 text-center text-lg font-semibold">1</output>

                                                <button type="button" data-tambah aria-label="Tambah jumlah tiket"
                                                    class="inline-flex size-11 items-center justify-center rounded-md border border-nema-line text-lg transition-colors hover:bg-nema-surface-2 disabled:opacity-40 disabled:hover:bg-transparent">
                                                    +
                                                </button>
                                            </div>
                                        </div>

                                        <p class="text-right">
                                            <span class="block text-xs text-nema-muted">Total</span>
                                            <span data-total aria-live="polite" class="text-lg font-semibold"></span>
                                        </p>

                                    </div>

                                    @if (auth()->check() && auth()->user()->isAdmin())
                                        <p class="mt-5 rounded-md border border-nema-line bg-nema-surface-2 p-3 text-sm text-nema-muted">
                                            Akun admin hanya bisa melihat jadwal, tidak bisa memesan tiket.
                                        </p>
                                    @else
                                        <a data-lanjut
                                            class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover sm:w-auto">
                                            Lanjut pilih kursi
                                        </a>

                                        <p class="mt-3 text-xs text-nema-muted">
                                            Maksimal 6 tiket sekali pesan.
                                            @guest Kamu akan diminta masuk dulu sebelum memilih kursi. @endguest
                                        </p>
                                    @endif

                                </div>

                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if ($semuaLewat)
                        @php $besok = collect($tanggalBerjadwal)->first(fn ($t) => $t > $tanggal->format('Y-m-d')); @endphp

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-nema-surface p-4">
                            <p class="text-sm text-nema-muted">Semua jam tayang di tanggal ini sudah lewat.</p>

                            @if ($besok)
                                @php $b = \Illuminate\Support\Carbon::parse($besok); @endphp
                                <a href="{{ url('/film/' . $film['slug']) }}?tanggal={{ $besok }}"
                                   class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-5 text-sm font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                                    Lihat jadwal {{ $b->isTomorrow() ? 'besok' : $namaHari[$b->dayOfWeek] . ' ' . $b->day }}
                                </a>
                            @endif
                        </div>
                    @endif

                @endif

                <div class="mt-12 border-t border-nema-line/40 pt-6">
                    <h2 class="text-xl sm:text-2xl">Sinopsis</h2>
                    <p class="mt-3 max-w-prose text-nema-muted">{{ $film['sinopsis'] }}</p>
                </div>

            </div>

        </div>
    </div>

        <script>
        (function () {
            const MAKS = 6;
            const semuaBaris = document.querySelectorAll('[data-baris]');
            const dasar = @json(url('/kursi/' . $film['slug']));

            function rupiah(angka) {
                return 'Rp ' + angka.toLocaleString('id-ID');
            }

            function perbarui(baris, jumlah) {
                const harga = Number(baris.dataset.harga);
                const lanjut = baris.querySelector('[data-lanjut]');

                baris.dataset.jumlah = jumlah;
                baris.querySelector('[data-jumlah]').textContent = jumlah;
                baris.querySelector('[data-total]').textContent = rupiah(harga * jumlah);
                baris.querySelector('[data-kurang]').disabled = jumlah <= 1;
                baris.querySelector('[data-tambah]').disabled = jumlah >= MAKS;

                // Akun admin tidak punya tombol lanjut.
                if (lanjut) {
                    lanjut.href = dasar + '?' + new URLSearchParams({ jadwal: baris.dataset.jadwal, jumlah: jumlah });
                }
            }

            function tutupSemua() {
                semuaBaris.forEach(function (baris) {
                    baris.querySelector('[data-panel]').hidden = true;

                    baris.querySelectorAll('[data-jam]').forEach(function (jam) {
                        jam.setAttribute('aria-pressed', 'false');
                    });
                });
            }

            semuaBaris.forEach(function (baris) {
                baris.querySelectorAll('[data-jam]').forEach(function (tombol) {
                    tombol.addEventListener('click', function () {
                        const sedangTerpilih = tombol.getAttribute('aria-pressed') === 'true';

                        tutupSemua();

                        // Menekan jam yang sama untuk kedua kalinya membatalkan pilihan
                        if (sedangTerpilih) return;

                        tombol.setAttribute('aria-pressed', 'true');
                        baris.dataset.jadwal = tombol.dataset.jadwal;
                        baris.dataset.harga = tombol.dataset.harga;
                        baris.querySelector('[data-ringkas-jam]').textContent = tombol.dataset.jam;
                        baris.querySelector('[data-panel]').hidden = false;
                        perbarui(baris, 1);
                    });
                });

                baris.querySelector('[data-kurang]').addEventListener('click', function () {
                    perbarui(baris, Math.max(1, Number(baris.dataset.jumlah || 1) - 1));
                });

                baris.querySelector('[data-tambah]').addEventListener('click', function () {
                    perbarui(baris, Math.min(MAKS, Number(baris.dataset.jumlah || 1) + 1));
                });
            });
        })();
    </script>


@endsection
