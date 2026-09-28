@extends('layouts.app')

@section('judul', 'Kirim Masukan, AoraNema')

@section('konten')

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-10">

        <h1 class="text-2xl sm:text-3xl">Kirim Masukan</h1>

        <p class="mt-3 max-w-prose text-nema-muted">
            Ceritakan pengalamanmu memakai AoraNema, entah yang menyenangkan atau yang menyebalkan.
            Masukanmu dibaca pengelola bioskop.
        </p>

        @if (session('sukses'))
            <p role="status" class="mt-6 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                {{ session('sukses') }}
            </p>
        @endif

        @if (session('gagal'))
            <p role="alert" class="mt-6 rounded-lg border border-nema-accent bg-nema-surface p-4 text-sm">
                {{ session('gagal') }}
            </p>
        @endif

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

        @php
            $kategori = [
                'booking' => 'Pemesanan tiket',
                'payment' => 'Pembayaran',
                'application' => 'Website AoraNema',
                'customer_service' => 'Layanan pelanggan',
                'cinema_service' => 'Layanan di bioskop',
                'other' => 'Lainnya',
            ];

            // Isian yang tadi dikirim ditampilkan lagi kalau ada yang perlu dibetulkan,
            // supaya penonton tidak perlu mengetik ulang.
            $terkirim = old('feedbacks', [['category' => '', 'comment' => '']]);
        @endphp

        <form method="post" action="{{ url('/feedback') }}" class="mt-8 space-y-6" data-form-masukan>
            @csrf

            <div data-daftar-masukan class="space-y-6">
                @foreach ($terkirim as $i => $isi)
                    <fieldset data-masukan class="rounded-xl bg-nema-surface p-5 sm:p-6">
                        <legend class="sr-only">Masukan {{ $i + 1 }}</legend>

                        <div>
                            <label for="kategori-{{ $i }}" class="block text-sm">Masukan ini tentang apa?</label>
                            <select id="kategori-{{ $i }}" name="feedbacks[{{ $i }}][category]" required
                                    class="mt-2 block min-h-11 w-full rounded-md border border-nema-line bg-nema-bg px-4">
                                <option value="">Pilih bagiannya</option>
                                @foreach ($kategori as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(($isi['category'] ?? '') === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-5">
                            <label for="komentar-{{ $i }}" class="block text-sm">Ceritakan pengalamanmu</label>
                            <textarea id="komentar-{{ $i }}" name="feedbacks[{{ $i }}][comment]" rows="4" required maxlength="2000"
                                      aria-describedby="batas-{{ $i }}"
                                      class="mt-2 block w-full rounded-md border border-nema-line bg-nema-bg px-4 py-3">{{ $isi['comment'] ?? '' }}</textarea>
                            <p id="batas-{{ $i }}" class="mt-2 text-xs text-nema-muted">Paling panjang 2000 huruf.</p>
                        </div>

                        {{-- Tombol hapus hanya berguna kalau ada lebih dari satu masukan, jadi
                             ditampilkan lewat skrip saat masukan kedua ditambahkan. --}}
                        <button type="button" data-hapus-masukan hidden
                                class="mt-4 inline-flex min-h-11 items-center rounded-md border border-nema-line px-4 text-sm transition-colors hover:bg-nema-surface-2">
                            Hapus masukan ini
                        </button>
                    </fieldset>
                @endforeach
            </div>

            <button type="button" data-tambah-masukan
                    class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-5 text-sm transition-colors hover:bg-nema-surface">
                Tambah masukan lain
            </button>

            <div class="flex flex-wrap gap-3 border-t border-nema-line/40 pt-6">
                <button type="submit"
                        class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-6 font-medium text-white transition-colors hover:bg-nema-maroon-hover">
                    Kirim masukan
                </button>

                <a href="{{ url('/') }}"
                   class="inline-flex min-h-11 items-center rounded-md border border-nema-line px-6 transition-colors hover:bg-nema-surface">
                    Batal
                </a>
            </div>
        </form>

    </div>

    <script>
        // Satu halaman boleh memuat beberapa masukan sekaligus, misalnya satu soal pembayaran dan
        // satu soal layanan di bioskop. Tanpa skrip ini formnya tetap jalan dengan satu masukan.
        (function () {
            const MAKS = 5;
            const daftar = document.querySelector('[data-daftar-masukan]');
            const tambah = document.querySelector('[data-tambah-masukan]');

            function perbarui() {
                const semua = daftar.querySelectorAll('[data-masukan]');

                semua.forEach(function (kotak, i) {
                    kotak.querySelector('legend').textContent = 'Masukan ' + (i + 1);
                    kotak.querySelector('[data-hapus-masukan]').hidden = semua.length < 2;

                    // Nomor di nama isian harus berurutan, karena Laravel membacanya sebagai daftar.
                    kotak.querySelectorAll('select, textarea').forEach(function (isian) {
                        const bagian = isian.name.endsWith('[category]') ? 'category' : 'comment';
                        isian.name = 'feedbacks[' + i + '][' + bagian + ']';
                        const id = (bagian === 'category' ? 'kategori-' : 'komentar-') + i;
                        const label = kotak.querySelector('label[for="' + isian.id + '"]');
                        if (label) label.setAttribute('for', id);
                        isian.id = id;
                    });

                    // Keterangan batas panjang juga dinomori ulang, supaya tidak ada id kembar.
                    const batas = kotak.querySelector('[id^="batas-"]');
                    if (batas) batas.id = 'batas-' + i;
                    kotak.querySelector('textarea').setAttribute('aria-describedby', 'batas-' + i);
                });

                tambah.hidden = semua.length >= MAKS;
            }

            tambah.addEventListener('click', function () {
                const contoh = daftar.querySelector('[data-masukan]').cloneNode(true);
                contoh.querySelector('select').value = '';
                contoh.querySelector('textarea').value = '';
                daftar.append(contoh);
                perbarui();
                contoh.querySelector('select').focus();
            });

            daftar.addEventListener('click', function (e) {
                if (! e.target.closest('[data-hapus-masukan]')) return;
                e.target.closest('[data-masukan]').remove();
                perbarui();
                tambah.focus();
            });

            perbarui();
        })();
    </script>

@endsection
