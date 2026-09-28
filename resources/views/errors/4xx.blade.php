@extends('layouts.app')

@section('judul', 'Permintaan tidak bisa diproses, AoraNema')

@section('konten')

    {{-- Dipakai untuk kode galat 4xx yang tidak punya halaman sendiri, misalnya 405 kalau alamat
         yang seharusnya dikirim lewat formulir dibuka langsung di browser. --}}
    <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-20 text-center sm:px-6 sm:py-28">

        <p class="text-7xl font-semibold text-nema-surface-2 sm:text-8xl" aria-hidden="true">{{ $exception->getStatusCode() }}</p>

        <h1 class="mt-4 text-2xl sm:text-3xl">Permintaan tidak bisa diproses</h1>

        <p class="mt-4 text-nema-muted">
            Halaman ini tidak bisa dibuka dengan cara tadi. Kembali ke halaman sebelumnya, lalu coba lagi
            lewat tombol atau tautan yang tersedia.
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url()->previous() }}"
               class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                Kembali ke halaman tadi
            </a>

            <a href="{{ url('/') }}"
               class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                Ke beranda
            </a>
        </div>

    </div>

@endsection
