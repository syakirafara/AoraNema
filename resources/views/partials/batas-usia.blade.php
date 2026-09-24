{{-- Peringatan batas usia di halaman pilih kursi dan bayar. Bioskop berhak menolak penonton
     yang belum cukup umur, jadi penonton diberi tahu sebelum membayar, bukan di pintu studio.
     Film tanpa batas usia atau untuk semua umur tidak menampilkan apa pun. --}}
@if ($usia && $usia !== 'SU')
    <p class="mt-5 flex items-start gap-3 rounded-lg border border-nema-line p-3 text-sm text-nema-muted">
        @include('partials.usia', ['usia' => $usia])
        <span>
            Film ini untuk penonton usia {{ rtrim($usia, '+') }} tahun ke atas. Petugas bisa meminta
            kartu identitas di pintu studio, dan tiket tidak bisa dikembalikan kalau penonton ditolak masuk.
        </span>
    </p>
@endif
