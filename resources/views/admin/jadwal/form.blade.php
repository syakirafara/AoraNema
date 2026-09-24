@extends('layouts.app')

@section('judul', ($jadwal->exists ? 'Ubah' : 'Tambah') . ' Jadwal, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">

        <a href="{{ url('/admin/jadwal') }}"
           class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Kembali ke daftar jadwal
        </a>

        <h1 class="mt-4 text-2xl sm:text-3xl">{{ $jadwal->exists ? 'Ubah Jadwal' : 'Tambah Jadwal' }}</h1>

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-nema-accent bg-nema-surface p-4">
                <p class="text-sm">Ada {{ $errors->count() }} isian yang perlu dibetulkan:</p>
                <ul class="mt-2 list-inside list-disc text-sm text-nema-muted">
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('gagal'))
            <p role="alert" class="mt-6 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                {{ session('gagal') }}
            </p>
        @endif

        <form method="post"
              action="{{ $jadwal->exists ? url('/admin/jadwal/' . $jadwal->id) : url('/admin/jadwal') }}"
              class="mt-8 space-y-6">
            @csrf
            @if ($jadwal->exists)
                @method('put')
            @endif

            <div>
                <label for="movie_id" class="block text-sm">Film</label>
                <select id="movie_id" name="movie_id" required
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                    <option value="">Pilih film</option>
                    @foreach ($film as $f)
                        <option value="{{ $f->id }}" @selected(old('movie_id', $jadwal->movie_id) == $f->id)>
                            {{ $f->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="studio_id" class="block text-sm">Studio</label>
                <select id="studio_id" name="studio_id" required
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                    <option value="">Pilih studio</option>
                    @foreach ($studio as $s)
                        <option value="{{ $s->id }}" @selected(old('studio_id', $jadwal->studio_id) == $s->id)>
                            {{ $s->label() }} ({{ $s->kapasitas() }} kursi)
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($jadwal->exists)
                <div>
                    <label for="show_time" class="block text-sm">Waktu tayang</label>
                    <input type="datetime-local" id="show_time" name="show_time" required
                           value="{{ old('show_time', \Illuminate\Support\Carbon::parse($jadwal->show_time)->format('Y-m-d\TH:i')) }}"
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                </div>
            @else
                {{-- Menambah jadwal bisa sekaligus untuk beberapa hari dan sampai lima jam,
                     supaya admin tidak perlu menyimpan satu per satu. --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="tanggal_mulai" class="block text-sm">Dari tanggal</label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" required
                               min="{{ now()->format('Y-m-d') }}"
                               value="{{ old('tanggal_mulai', now()->format('Y-m-d')) }}"
                               class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                    </div>

                    <div>
                        <label for="tanggal_selesai" class="block text-sm">Sampai tanggal</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" aria-describedby="tanggal-ket"
                               min="{{ now()->format('Y-m-d') }}" max="{{ now()->addDays(30)->format('Y-m-d') }}"
                               value="{{ old('tanggal_selesai') }}"
                               class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                        <p id="tanggal-ket" class="mt-2 text-xs text-nema-muted">Kosongkan kalau cuma satu hari.</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="text-sm">Jam tayang, sampai lima</legend>
                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        @for ($i = 0; $i < 5; $i++)
                            <label class="block">
                                <span class="sr-only">Jam ke-{{ $i + 1 }}</span>
                                <input type="time" name="jam[]" @if ($i === 0) required @endif
                                       value="{{ old('jam.' . $i) }}"
                                       class="block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-3">
                            </label>
                        @endfor
                    </div>
                    <p class="mt-2 text-xs text-nema-muted">
                        Jam yang kosong diabaikan. Jam yang bertabrakan dengan film lain dilewati, sisanya tetap disimpan.
                    </p>
                </fieldset>
            @endif

            <p class="text-sm text-nema-muted">
                Harga per kursi diambil otomatis dari tarif studio: hari biasa, atau akhir pekan
                untuk Jumat sampai Minggu. Tarifnya diubah di halaman Studio.
            </p>

            <p class="text-sm text-nema-muted">
                Satu studio tidak bisa memutar dua film yang waktunya bertabrakan, termasuk jeda
                15 menit di antaranya. Kalau bentrok, simpanannya ditolak dan kamu diberi tahu
                jadwal mana yang bertabrakan.
            </p>

            <div class="flex flex-wrap gap-3 border-t border-nema-line/40 pt-6">
                <button type="submit"
                        class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                    {{ $jadwal->exists ? 'Simpan perubahan' : 'Tambah jadwal' }}
                </button>

                <a href="{{ url('/admin/jadwal') }}"
                   class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                    Batal
                </a>
            </div>
        </form>

    </div>

@endsection
