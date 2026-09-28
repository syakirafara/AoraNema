@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Genre[] $genres */
@endphp

@extends('layouts.app')

@section('judul', 'Daftar, AoraNema')

@section('konten')
<section class="mx-auto max-w-lg px-4 py-16 sm:px-6">
    <div class="text-center">
        <h1 class="font-display text-3xl">Daftar Akun</h1>
        <p class="mt-2 text-sm text-nema-muted">Dengan akun, kamu bisa memesan kursi dan melihat lagi tiket yang sudah dibeli.</p>
    </div>

    <form method="POST" action="{{ url('/daftar') }}" class="mt-8 space-y-6">
        @csrf

        {{-- Menampilkan pesan error validasi --}}
        @if ($errors->any())
            <div class="rounded-md border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-500">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-nema-muted">Nama Lengkap</label>
                <input type="text" name="name" id="name" required value="{{ old('name') }}"
                    class="mt-2 block w-full rounded-md border border-nema-line bg-nema-surface px-4 py-2.5 text-nema-text focus:border-nema-accent focus:outline-none focus:ring-1 focus:ring-nema-accent">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-nema-muted">Alamat Email</label>
                <input type="email" name="email" id="email" required value="{{ old('email') }}"
                    class="mt-2 block w-full rounded-md border border-nema-line bg-nema-surface px-4 py-2.5 text-nema-text focus:border-nema-accent focus:outline-none focus:ring-1 focus:ring-nema-accent">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-nema-muted">Kata Sandi</label>
                <input type="password" name="password" id="password" required minlength="8"
                    class="mt-2 block w-full rounded-md border border-nema-line bg-nema-surface px-4 py-2.5 text-nema-text focus:border-nema-accent focus:outline-none focus:ring-1 focus:ring-nema-accent">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-nema-muted">Konfirmasi Kata Sandi</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8"
                    class="mt-2 block w-full rounded-md border border-nema-line bg-nema-surface px-4 py-2.5 text-nema-text focus:border-nema-accent focus:outline-none focus:ring-1 focus:ring-nema-accent">
            </div>
        </div>

        {{-- Pemilihan Genre untuk ML Rekomendasi --}}
        @if(isset($genres) && $genres->isNotEmpty())
            <div class="pt-4 border-t border-nema-line/40">
                <h2 class="text-sm font-medium text-nema-muted mb-3">Genre favorit, boleh lebih dari satu</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($genres as $genre)
                        <label class="flex cursor-pointer items-start gap-2 rounded-md border border-nema-line bg-nema-surface p-3 transition-colors hover:border-nema-accent has-checked:border-nema-accent has-checked:bg-nema-accent/10">
                            <input type="checkbox" name="genres[]" value="{{ $genre->id }}" @checked(in_array($genre->id, old('genres', []))) class="mt-0.5 size-4 accent-nema-maroon">
                            <span class="text-sm">{{ $genre->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-nema-muted">Dipakai untuk menyarankan film yang mungkin kamu suka. Boleh dikosongkan.</p>
            </div>
        @endif

        <button type="submit"
            class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-nema-maroon px-5 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
            Daftar Sekarang
        </button>
        
        <p class="text-center text-sm text-nema-muted">
            Sudah punya akun? <a href="{{ url('/masuk') }}" class="inline-flex min-h-11 items-center text-nema-accent hover:underline">Masuk di sini</a>
        </p>
    </form>
</section>
@endsection
