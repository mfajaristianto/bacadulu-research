<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan membaca berkas Word. Pesannya aman dan boleh ditampilkan ke admin apa adanya. */
class WordImportException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
