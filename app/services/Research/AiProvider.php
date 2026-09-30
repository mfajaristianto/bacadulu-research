<?php

namespace App\Services\Research;

interface AiProvider
{
    public function generate(array $payload): array;
}
