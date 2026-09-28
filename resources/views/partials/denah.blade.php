{{-- Denah kursi studio, dipakai halaman pilih kursi penonton dan halaman loket kasir.
     Butuh $studio dan $kursiTerisi (nomor kursi yang sudah diambil). Setiap kursi yang masih
     kosong berupa tombol dengan data-kursi; halaman pemakainya yang mengatur pilihan lewat skrip. --}}
@php
    // Denah dibentuk dari susunan kursi studio. Nomor kursi berupa teks seperti "A1", jadi
    // dipecah jadi huruf baris dan nomor.
    $barisKursi = collect($studio->daftarKursi())
        ->map(fn ($k) => ['kode' => $k, 'baris' => preg_replace('/\d+$/', '', $k), 'nomor' => (int) preg_replace('/^\D+/', '', $k)])
        ->groupBy('baris');

    // Lorong di tengah baris terpanjang.
    $kursiTerbanyak = $barisKursi->max(fn ($b) => $b->count()) ?? 0;
    $lorongSetelah = intdiv($kursiTerbanyak, 2);

    // Kolom denah: huruf baris, kursi kiri, lorong, kursi kanan. Lebar kursi mengikuti layar,
    // paling kecil 24 piksel supaya sepuluh kursi per baris muat di HP tanpa digeser, dan paling
    // besar 44 piksel di layar lebar. Studio yang barisnya sangat panjang tetap bisa digeser.
    $kolomKursi = 'minmax(1.5rem, 2.75rem)';
    // Studio dengan satu kursi per baris tidak diberi lorong, karena repeat(0, ...) tidak sah di CSS.
    $kolomDenah = $lorongSetelah > 0
        ? '1.25rem repeat(' . $lorongSetelah . ', ' . $kolomKursi . ') 1rem repeat(' . ($kursiTerbanyak - $lorongSetelah) . ', ' . $kolomKursi . ')'
        : '1.25rem repeat(' . max(1, $kursiTerbanyak) . ', ' . $kolomKursi . ')';
@endphp

{{-- Garisnya memudar di kedua ujung supaya terbaca sebagai layar yang
     memantulkan cahaya, bukan sekadar garis pembatas. --}}
<div class="mt-10">
    <div class="mx-auto h-1 w-2/3 rounded-full bg-linear-to-r from-transparent via-nema-line to-transparent"></div>
    <p class="mt-3 text-center text-xs text-nema-muted">Layar</p>
</div>

<div class="mt-10 overflow-x-auto pb-2">
    <div class="mx-auto max-w-max space-y-1.5 sm:space-y-2">
        @foreach ($barisKursi as $b => $kursiBaris)
            <div class="grid items-center gap-1 sm:gap-2" style="grid-template-columns: {{ $kolomDenah }}">
                <span class="text-center text-xs text-nema-muted">{{ $b }}</span>

                @foreach ($kursiBaris as $k)
                    @php
                        $kode = $k['kode'];
                        $n = $k['nomor'];
                        $sudahTerisi = in_array($kode, $kursiTerisi ?? []);
                    @endphp

                    <button type="button" data-kursi="{{ $kode }}" aria-pressed="false"
                            @disabled($sudahTerisi)
                            aria-label="Kursi {{ $kode }}{{ $sudahTerisi ? ', sudah terisi' : '' }}"
                            class="aspect-square w-full rounded-md border text-[11px] transition-colors sm:text-xs {{ $sudahTerisi ? 'cursor-not-allowed border-transparent bg-nema-surface-2 text-nema-muted/40' : 'border-nema-line hover:bg-nema-surface aria-pressed:border-nema-accent aria-pressed:bg-nema-maroon aria-pressed:text-white' }}">
                        {{ $n }}
                    </button>

                    @if ($loop->iteration === $lorongSetelah && ! $loop->last)
                        <span aria-hidden="true"></span>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<div class="mt-8 flex flex-wrap justify-center gap-5 text-xs text-nema-muted">
    <span class="flex items-center gap-2">
        <span class="size-4 rounded border border-nema-line" aria-hidden="true"></span> Tersedia
    </span>
    <span class="flex items-center gap-2">
        <span class="size-4 rounded border border-nema-accent bg-nema-maroon" aria-hidden="true"></span> {{ $labelPilihan ?? 'Pilihanmu' }}
    </span>
    <span class="flex items-center gap-2">
        <span class="size-4 rounded bg-nema-surface-2" aria-hidden="true"></span> Sudah terisi
    </span>
</div>
