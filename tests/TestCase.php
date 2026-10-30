<?php

namespace Tests;

use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Cada prueba arma su propio esquema; la caché de la petición anterior no aplica.
        SchemaCache::flush();
    }
}
