<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'AoraNema')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-dvh bg-nema-bg text-nema-text antialiased">

    {{-- Header dibuat tembus pandang karena slider poster lewat di bawahnya saat digulir --}}
    <header class="sticky top-0 z-50 border-b border-nema-line/40 bg-nema-bg/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">

            <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center font-display text-2xl font-semibold tracking-tight">
                Aora<span class="text-nema-accent">Nema</span>
            </a>

            <div class="flex items-center gap-1 sm:gap-3">

                <a href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif
                    class="inline-flex min-h-11 items-center px-2 text-sm transition-colors sm:px-3 {{ request()->is('/') ? 'text-nema-text' : 'text-nema-muted hover:text-nema-text' }}">
                    Beranda
                </a>

                <a href="{{ url('/film') }}" @if (request()->is('film')) aria-current="page" @endif
                    class="inline-flex min-h-11 items-center px-2 text-sm transition-colors sm:px-3 {{ request()->is('film') ? 'text-nema-text' : 'text-nema-muted hover:text-nema-text' }}">
                    Film
                </a>

                @auth
                    {{-- Menu akun memakai <details> supaya terbuka dengan ketukan di ponsel dan Enter
                         di keyboard. Menu yang hanya terbuka saat kursor lewat tidak bisa dipakai di HP. --}}
                    <details data-menu-akun class="relative">
                        <summary
                            class="inline-flex min-h-11 cursor-pointer list-none items-center gap-1.5 px-2 text-sm text-nema-text transition-colors hover:text-white sm:px-3 [&::-webkit-details-marker]:hidden">
                            {{-- Di HP nama akun diganti inisial supaya logo, Beranda, dan Film tetap muat
                                 dalam satu baris. Nama lengkapnya tetap dibacakan pembaca layar. --}}
                            <span class="grid size-8 place-items-center rounded-full bg-nema-surface-2 text-sm font-semibold sm:hidden" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span class="sr-only sm:not-sr-only sm:max-w-40 sm:truncate">{{ Auth::user()->name }}</span>
                            <svg class="size-4 shrink-0 text-nema-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </summary>

                        <div class="absolute right-0 top-full mt-1 flex w-52 flex-col rounded-lg border border-nema-line bg-nema-surface p-1 shadow-xl shadow-black/40">

                            @if (Auth::user()->isUser())
                                <a href="{{ url('/tiket-saya') }}"
                                   class="flex min-h-11 items-center rounded-md px-3 text-sm text-nema-muted transition-colors hover:bg-nema-surface-2 hover:text-nema-text">
                                    Tiket Saya
                                </a>

                                <a href="{{ url('/feedback') }}"
                                   class="flex min-h-11 items-center rounded-md px-3 text-sm text-nema-muted transition-colors hover:bg-nema-surface-2 hover:text-nema-text">
                                    Kirim Masukan
                                </a>
                            @endif

                            @if (Auth::user()->isAdmin())
                                <a href="{{ url('/admin') }}"
                                   class="flex min-h-11 items-center rounded-md px-3 text-sm text-nema-muted transition-colors hover:bg-nema-surface-2 hover:text-nema-text">
                                    Panel Admin
                                </a>
                            @endif

                            <div class="my-1 h-px bg-nema-line/40"></div>

                            <form method="POST" action="{{ url('/keluar') }}">
                                @csrf
                                <button type="submit"
                                        class="flex min-h-11 w-full items-center rounded-md px-3 text-left text-sm text-nema-muted transition-colors hover:bg-nema-surface-2 hover:text-nema-text">
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </details>

                    <script>
                        // Menu akun ditutup lagi kalau pengguna mengetuk di luar menu atau menekan Esc.
                        (function () {
                            const menu = document.querySelector('[data-menu-akun]');

                            document.addEventListener('click', function (e) {
                                if (menu.open && ! menu.contains(e.target)) menu.open = false;
                            });

                            document.addEventListener('keydown', function (e) {
                                if (e.key === 'Escape' && menu.open) {
                                    menu.open = false;
                                    menu.querySelector('summary').focus();
                                }
                            });
                        })();
                    </script>
                @else
                    <a href="{{ url('/masuk') }}"
                        class="inline-flex min-h-11 items-center rounded-md bg-nema-maroon px-4 font-medium text-white sm:px-5 transition-colors hover:bg-nema-maroon-hover">
                        Masuk
                    </a>
                @endauth

            </div>

        </div>
    </header>

    <main>
        @yield('konten')
    </main>

    <footer class="mt-20 border-t border-nema-line/40 px-4 py-10 sm:px-6">
        <p class="mx-auto max-w-7xl text-sm text-nema-muted">
            AoraNema
        </p>
    </footer>

    <script>
        // Posisi gulir diingat per halaman, lalu dikembalikan saat penonton kembali ke halaman itu:
        // waktu di-refresh, waktu menekan Kembali di browser, waktu tombol seperti pilihan tanggal,
        // saringan, urutan, atau tab memuat ulang halaman yang sama, dan waktu penonton kembali dari
        // halaman yang tadi dibuka dari sini, misalnya beranda, lalu detail film, lalu beranda lagi.
        // Halaman yang dibuka pertama kali tetap mulai dari atas.
        (function () {
            const kunci = 'gulir:' + location.pathname;
            const simpan = (k, v) => { try { sessionStorage.setItem(k, v); } catch (e) {} };
            const baca = (k) => { try { return sessionStorage.getItem(k); } catch (e) { return null; } };

            // Setiap tautan ke halaman lain di AoraNema mencatat dari halaman mana tujuannya dibuka,
            // lengkap dengan ?isian-nya, misalnya /film?urut=az. Tautan ke halaman yang sama, seperti
            // pilihan tanggal, tidak dicatat supaya asal yang lama tidak tertimpa.
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a[href]');
                if (! a || a.origin !== location.origin || a.pathname === location.pathname) return;
                simpan('asal:' + a.pathname, location.pathname + location.search);
            }, true);

            // Tautan Kembali menuju halaman asal yang tercatat, bukan selalu ke beranda.
            const asal = baca('asal:' + location.pathname);
            if (asal) {
                document.querySelectorAll('[data-kembali]').forEach(function (a) { a.href = asal; });
            }

            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

            window.addEventListener('pagehide', function () {
                simpan(kunci, String(window.scrollY));
            });

            // Setelah form dikirim, halaman jawabannya dimulai dari atas, supaya pesan hasilnya
            // terlihat. Tanpa ini penonton mendarat di tengah halaman seperti sebelum mengirim.
            document.addEventListener('submit', function (e) {
                if (e.target.method && e.target.method.toLowerCase() === 'post') simpan('kirim:' + location.pathname, '1');
            }, true);

            if (baca('kirim:' + location.pathname)) {
                try { sessionStorage.removeItem('kirim:' + location.pathname); } catch (e) {}
                simpan(kunci, '0');
                return;
            }

            // Tautan ke bagian tertentu, seperti #semua-film, lebih diutamakan daripada posisi lama.
            if (location.hash) return;

            const jenis = (performance.getEntriesByType('navigation')[0] || {}).type;
            let dari = null;
            try { dari = new URL(document.referrer); } catch (e) {}

            const halamanSama = dari && dari.origin === location.origin && dari.pathname === location.pathname;
            // Halaman sebelumnya dibuka dari halaman ini, jadi penonton sedang kembali ke sini.
            const asalSebelumnya = dari && dari.origin === location.origin ? baca('asal:' + dari.pathname) : null;
            const kembaliKeSini = asalSebelumnya !== null && asalSebelumnya.split('?')[0] === location.pathname;

            if (jenis !== 'reload' && jenis !== 'back_forward' && ! halamanSama && ! kembaliKeSini) return;

            const posisi = baca(kunci);
            if (posisi === null) return;

            // behavior 'instant' supaya tidak dianimasikan dari atas oleh scroll-smooth di <html>.
            window.scrollTo({ top: Number(posisi), behavior: 'instant' });
        })();
    </script>

</body>

</html>