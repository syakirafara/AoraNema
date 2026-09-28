@extends('layouts.app')

@section('judul', ($movie->exists ? 'Ubah' : 'Tambah') . ' Film, AoraNema')

@section('konten')

    @include('admin.nav')

    @php
        // Setelah isian ditolak, kotak centang mengikuti isian terakhir, bukan data film yang tersimpan.
        // Kotak yang tidak dicentang tidak ikut terkirim, jadi old() saja tidak bisa membedakannya.
        $adaIsianLama = session()->hasOldInput();
    @endphp

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">

        <a href="{{ url('/admin/film') }}"
            class="inline-flex min-h-11 items-center text-sm text-nema-muted transition-colors hover:text-nema-text">
            &larr;&nbsp; Kembali ke daftar film
        </a>

        <h1 class="mt-4 text-2xl sm:text-3xl">{{ $movie->exists ? 'Ubah Film' : 'Tambah Film' }}</h1>

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

        <form method="post" action="{{ $movie->exists ? url('/admin/film/' . $movie->id) : url('/admin/film') }}"
            class="mt-8 space-y-6">
            @csrf
            @if ($movie->exists)
                @method('put')
            @endif

            <div>
                <label for="title" class="block text-sm">Judul</label>
                <input type="text" id="title" name="title" required maxlength="255"
                    value="{{ old('title', $movie->title) }}"
                    class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
            </div>

            <div>
                <label for="tagline" class="block text-sm">Tagline</label>
                <input type="text" id="tagline" name="tagline" maxlength="255" aria-describedby="tagline-ket"
                    value="{{ old('tagline', $movie->tagline) }}"
                    class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                <p id="tagline-ket" class="mt-2 text-xs text-nema-muted">
                    Satu kalimat pendek di bawah judul. Boleh dikosongkan, nanti tidak ditampilkan.
                </p>
            </div>

            <div>
                <label for="synopsis" class="block text-sm">Sinopsis</label>
                <textarea id="synopsis" name="synopsis" rows="4"
                    class="mt-2 block w-full rounded-md border border-nema-line bg-nema-surface px-4 py-3">{{ old('synopsis', $movie->synopsis) }}</textarea>
            </div>

            <div>
                <label for="poster_url" class="block text-sm">Alamat poster</label>
                <input type="text" id="poster_url" name="poster_url" maxlength="255"
                    value="{{ old('poster_url', $movie->poster_url) }}"
                    class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                <p class="mt-2 text-xs text-nema-muted">
                    Boleh alamat lengkap seperti <code>https://...jpg</code>, atau nama berkas yang ada di
                    <code>public/img/</code>.
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div>
                    <label for="duration_minutes" class="block text-sm">Durasi (menit)</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" min="30" max="300" required
                        aria-describedby="durasi-ket"
                        value="{{ old('duration_minutes', $movie->duration_minutes) }}"
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                    <p id="durasi-ket" class="mt-2 text-xs text-nema-muted">Dipakai untuk menghitung kapan studio kosong lagi.</p>
                </div>

                <div>
                    <label for="usia" class="block text-sm">Batas usia</label>
                    <select id="usia" name="usia"
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                        <option value="">Belum diisi</option>
                        @foreach (['SU' => 'SU, semua umur', '13+' => '13+', '17+' => '17+', '21+' => '21+'] as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('usia', $movie->usia) === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="release_date" class="block text-sm">Tanggal rilis</label>
                    <input type="date" id="release_date" name="release_date"
                        value="{{ old('release_date', $movie->release_date ? \Illuminate\Support\Carbon::parse($movie->release_date)->format('Y-m-d') : '') }}"
                        class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-surface px-4">
                </div>
            </div>

            <fieldset>
                <legend class="text-sm">Genre</legend>

                @if ($genre->isEmpty())
                    <p class="mt-2 text-sm text-nema-muted">
                        Belum ada genre di database. Genre terisi lewat seeder TMDB.
                    </p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($genre as $g)
                            <label
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-nema-line px-4 transition-colors hover:bg-nema-surface has-checked:border-nema-accent has-checked:bg-nema-surface">
                                <input type="checkbox" name="genre[]" value="{{ $g->id }}"
                                    @checked(in_array($g->id, $adaIsianLama ? old('genre', []) : $movie->genres->pluck('id')->all())) class="size-4 accent-nema-maroon">
                                {{ $g->name }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input type="checkbox" name="is_showing" value="1" @checked($adaIsianLama ? old('is_showing') : ($movie->exists ? $movie->is_showing : true))
                    class="mt-0.5 size-5 shrink-0 accent-nema-maroon">
                <span>
                    Sedang tayang
                    <span class="mt-1 block text-xs text-nema-muted">
                        Hilangkan centang untuk mengarsipkan film: film disembunyikan dari penonton dan tiketnya
                        berhenti dijual. Jadwalnya tetap disimpan.
                    </span>
                </span>
            </label>

            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input type="checkbox" name="pilihan" value="1" @checked($adaIsianLama ? old('pilihan') : $movie->pilihan)
                    class="mt-0.5 size-5 shrink-0 accent-nema-maroon">
                <span>
                    Dipilih pengelola
                    <span class="mt-1 block text-xs text-nema-muted">
                        Muncul di bagian Dipilih Pengelola di beranda, selama filmnya sedang tayang.
                    </span>
                </span>
            </label>

            <div class="flex flex-wrap gap-3 border-t border-nema-line/40 pt-6">
                <button type="submit"
                    class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                    {{ $movie->exists ? 'Simpan perubahan' : 'Tambah film' }}
                </button>

                <a href="{{ url('/admin/film') }}"
                    class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                    Batal
                </a>
            </div>
        </form>

    </div>

@endsection
