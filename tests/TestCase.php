<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // DocumentPromptImportController memasang set_time_limit(40) yang bocor ke seluruh proses tes; suite panjang mati di tengah jalan.
        @set_time_limit(0);

        parent::tearDown();
    }
}
