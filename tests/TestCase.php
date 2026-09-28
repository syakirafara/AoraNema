<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak butuh berkas CSS dan JavaScript hasil Vite, jadi tetap jalan walaupun
        // npm run build atau npm run dev belum dijalankan.
        $this->withoutVite();
    }
}
