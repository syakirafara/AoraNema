<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Website yang dibuka lewat ngrok atau hosting di balik proxy tetap membuat alamat dengan
        // domain dan https yang benar, misalnya alamat kembali dari halaman Midtrans.
        $middleware->trustProxies(at: '*');

        // Pemberitahuan pembayaran dikirim server Midtrans, bukan dari form di website, jadi tidak
        // membawa token CSRF. Keasliannya dicek lewat tanda tangan di BookingController.
        $middleware->validateCsrfTokens(except: ['midtrans/notifikasi']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
