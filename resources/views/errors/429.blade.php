@extends('layouts.app')

@section('judul', 'Terlalu banyak percobaan, AoraNema')

@section('konten')

    <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-20 text-center sm:px-6 sm:py-28">

        {{-- Angkanya dibuat besar dan redup sebagai latar, bukan sebagai judul, supaya
             yang pertama terbaca tetap kalimat penjelasnya. --}}
        <p class="text-7xl font-semibold text-nema-surface-2 sm:text-8xl" aria-hidden="true">429</p>

        <h1 class="mt-4 text-2xl sm:text-3xl">Terlalu banyak percobaan</h1>

        <p class="mt-4 text-nema-muted">
            Demi keamanan, isian yang sama hanya bisa dikirim beberapa kali dalam satu menit.
            Tunggu sebentar, lalu coba lagi.
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
