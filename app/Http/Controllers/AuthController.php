<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Menampilkan halaman form login
    public function showLoginForm()
    {
        return view('masuk');
    }

    // Memproses data yang dikirim dari form
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Admin langsung ke panel admin dan kasir ke loket, walaupun tadi sempat membuka halaman
            // penonton. Penonton kembali ke halaman yang tadi ingin dibuka, misalnya pilih kursi,
            // atau ke beranda kalau tidak ada.
            if ($request->user()->isAdmin() || $request->user()->isCashier()) {
                $request->session()->forget('url.intended');

                return redirect($request->user()->isAdmin() ? '/admin' : '/kasir');
            }

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi salah.',
        ])->onlyInput('email');
    }

    // Memproses logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function showRegisterForm()
    {
        $genres = \App\Models\Genre::orderBy('name')->get();
        return view('daftar', compact('genres'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // bcrypt hanya membaca 72 byte pertama, jadi kata sandi yang lebih panjang ditolak.
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ]);

        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            // Formulir mengirim id genre, yang disimpan di akun adalah namanya.
            'favorite_genres' => \App\Models\Genre::whereIn('id', $data['genres'] ?? [])->pluck('name')->all(),
        ]);

        Auth::login($user);

        return redirect('/');
    }
}