@extends('layouts.app')

@section('judul', ($studio->exists ? 'Ubah' : 'Tambah') . ' Studio, AoraNema')

@section('konten')

    @include('admin.nav')

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">

        <a href="{{ url('/admin/studio') }}"
           class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Kembali ke daftar studio
        </a>

        <h1 class="mt-4 text-2xl sm:text-3xl">{{ $studio->exists ? 'Ubah Studio' : 'Tambah Studio' }}</h1>

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

        @if ($terkunci)
            <p class="mt-6 rounded-lg border border-nema-line bg-nema-surface p-4 text-sm text-nema-muted">
                Studio ini punya jadwal mendatang yang sudah dipesan, jadi format dan susunan kursinya
                dikunci. Penonton sudah membeli tiket untuk format dan kursi itu. Nama dan tarif tetap bisa
                diubah; kuncinya terbuka lagi setelah jadwal-jadwal itu lewat.
            </p>
        @endif

        <form method="post"
              action="{{ $studio->exists ? url('/admin/studio/' . $studio->id) : url('/admin/studio') }}"
              class="mt-8 space-y-6">
            @csrf
            @if ($studio->exists)
                @method('put')
            @endif

            <div>
                <label for="name" class="block text-sm">Nama studio</label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="{{ old('name', $studio->name) }}"
                       class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                <p class="mt-2 text-xs text-nema-muted">
                    Contoh: Studio 1. Nama ini tampil di tiket supaya penonton tahu pintu mana yang dituju.
                </p>
            </div>

            <div>
                <label for="format" class="block text-sm">Format layar</label>
                <select id="format" name="format" required @disabled($terkunci)
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4 disabled:opacity-40">
                    @foreach (\App\Models\Studio::FORMAT as $f)
                        <option value="{{ $f }}" @selected(old('format', $studio->format ?? 'Regular 2D') === $f)>{{ $f }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-nema-muted">
                    Di halaman film, jam tayang dikelompokkan menurut format ini. Beberapa studio boleh berformat sama.
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="baris" class="block text-sm">Jumlah baris</label>
                    <input type="number" id="baris" name="baris" min="1" max="26" required
                           @disabled($terkunci)
                           value="{{ old('baris', $baris) }}"
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4 disabled:opacity-40">
                    <p class="mt-2 text-xs text-nema-muted">Baris diberi huruf A sampai Z.</p>
                </div>

                <div>
                    <label for="per_baris" class="block text-sm">Kursi per baris</label>
                    <input type="number" id="per_baris" name="per_baris" min="1" max="30" required
                           @disabled($terkunci)
                           value="{{ old('per_baris', $perBaris) }}"
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4 disabled:opacity-40">
                    <p class="mt-2 text-xs text-nema-muted">Kursi dinomori 1 sampai angka ini.</p>
                </div>
            </div>

            <fieldset class="grid gap-6 sm:grid-cols-2">
                <legend class="mb-3 text-sm">Harga per kursi, dalam rupiah tanpa titik</legend>

                <div>
                    <label for="harga_biasa" class="block text-sm text-nema-muted">Senin sampai Jumat</label>
                    <input type="number" id="harga_biasa" name="harga_biasa" min="0" max="1000000" step="1000" required
                           value="{{ old('harga_biasa', $studio->harga_biasa ?? 45000) }}"
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                </div>

                <div>
                    <label for="harga_akhir_pekan" class="block text-sm text-nema-muted">Sabtu dan Minggu</label>
                    <input type="number" id="harga_akhir_pekan" name="harga_akhir_pekan" min="0" max="1000000" step="1000" required
                           value="{{ old('harga_akhir_pekan', $studio->harga_akhir_pekan ?? 55000) }}"
                           class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                </div>
            </fieldset>

            <p class="text-sm text-nema-muted">
                Tarif baru berlaku untuk tiket yang dijual berikutnya, termasuk jadwal studio ini yang
                belum lewat. Harga di pesanan yang sudah dibuat tidak berubah.
            </p>

            <p class="text-sm text-nema-muted">
                Kursi dibuat otomatis dari dua angka di atas, misalnya 8 baris dikali 10 kursi
                menghasilkan A1 sampai H10. Kapasitas studio ikut dihitung dari situ.
            </p>

            <div class="flex flex-wrap gap-3 border-t border-nema-line/40 pt-6">
                <button type="submit"
                        class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                    {{ $studio->exists ? 'Simpan perubahan' : 'Tambah studio' }}
                </button>

                <a href="{{ url('/admin/studio') }}"
                   class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                    Batal
                </a>
            </div>
        </form>

    </div>

@endsection
