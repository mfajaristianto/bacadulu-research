<?php

namespace App\Exceptions;

use App\Models\Document;
use RuntimeException;

/*
 * Item Source Sync cocok dengan sumber lain yang sudah ada di Pustaka (isi identik atau DOI sama).
 * Dilempar sebelum ada perubahan apa pun, jadi item tetap menunggu review dan bisa diabaikan atau diperiksa.
 */
class SourceDuplicateException extends RuntimeException
{
    public function __construct(public readonly Document $match, public readonly string $kind)
    {
        parent::__construct(
            $kind === 'exact'
                ? 'Isi sumber ini identik dengan "'.$match->title.'" yang sudah ada di Pustaka. Tidak dibuat sumber kedua.'
                : 'DOI sumber ini sama dengan "'.$match->title.'" di Pustaka. Periksa sumber yang ada, lalu abaikan item ini atau perbarui sumber tersebut secara manual.'
        );
    }
}
