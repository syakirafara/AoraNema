<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Percobaan masuk dibatasi 5 kali per menit untuk tiap pasangan email dan alamat IP, supaya
        // kata sandi tidak bisa ditebak terus-menerus. Dihitung per email, jadi satu kelas yang
        // memakai jaringan kampus yang sama tidak saling menghabiskan jatah.
        RateLimiter::for('masuk', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')) . '|' . $request->ip()));
    }
}
