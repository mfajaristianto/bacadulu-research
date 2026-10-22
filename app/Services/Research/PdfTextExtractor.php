<?php

namespace App\Services\Research;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractor
{
    private string $lastMethod = 'unknown';

    public function extract(
        string $absolutePdfPath
    ): string {
        if (! is_file($absolutePdfPath)) {
            throw new RuntimeException(
                'File PDF tidak ditemukan.'
            );
        }

        $this->lastMethod = 'unknown';

        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | 1. Coba Poppler / pdftotext jika tersedia
        |--------------------------------------------------------------------------
        |
        | pdftotext tetap diprioritaskan karena cepat dan biasanya
        | mempertahankan layout/page break dengan baik.
        |
        | Tetapi di Windows local atau shared hosting binary ini sering
        | tidak tersedia. Kegagalannya tidak boleh membuat ingestion
        | langsung berhenti.
        |
        */

        if (function_exists('proc_open')) {
            $binary = trim(
                (string) config(
                    'research.ingestion.pdftotext_binary',
                    'pdftotext'
                )
            );

            if ($binary !== '') {
                try {
                    $text =
                        $this->extractWithPdftotext(
                            $binary,
                            $absolutePdfPath
                        );

                    if ($text !== '') {
                        $this->lastMethod =
                            'pdftotext';

                        return $text;
                    }
                } catch (Throwable $exception) {
                    $errors[] =
                        'pdftotext: '
                        .$this->cleanError(
                            $exception->getMessage()
                        );
                }
            }
        } else {
            $errors[] =
                'pdftotext: proc_open tidak tersedia';
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Fallback pure PHP
        |--------------------------------------------------------------------------
        |
        | smalot/pdfparser tidak membutuhkan executable pdftotext.
        | Jadi ini bisa dipakai di Windows local maupun hosting yang
        | tidak mempunyai Poppler.
        |
        */

        if (class_exists(Parser::class)) {
            try {
                $text =
                    $this->extractWithPhpParser(
                        $absolutePdfPath
                    );

                if ($text !== '') {
                    $this->lastMethod =
                        'smalot/pdfparser';

                    return $text;
                }
            } catch (Throwable $exception) {
                $errors[] =
                    'smalot/pdfparser: '
                    .$this->cleanError(
                        $exception->getMessage()
                    );
            }
        } else {
            $errors[] =
                'smalot/pdfparser belum terpasang';
        }

        $detail =
            $errors !== []
                ? ' Detail: '.implode(
                    ' | ',
                    $errors
                )
                : '';

        throw new RuntimeException(
            'PDF extraction gagal. '
            .'Pastikan package smalot/pdfparser sudah terpasang. '
            .'Jika PDF berupa scan/image-only, parser teks biasa '
            .'memang tidak dapat membacanya.'
            .$detail
        );
    }

    public function lastMethod(): string
    {
        return $this->lastMethod;
    }

    private function extractWithPdftotext(
        string $binary,
        string $absolutePdfPath
    ): string {
        $process = new Process([
            $binary,
            '-layout',
            '-enc',
            'UTF-8',
            $absolutePdfPath,
            '-',
        ]);

        $process->setTimeout(
            max(
                10,
                (int) config(
                    'research.ingestion.parser_timeout',
                    60
                )
            )
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim(
                $process->getErrorOutput()
            );

            if ($error === '') {
                $error = trim(
                    $process->getOutput()
                );
            }

            throw new RuntimeException(
                $error !== ''
                    ? $error
                    : 'command gagal dijalankan'
            );
        }

        return $this->normalizeExtractedText(
            $process->getOutput()
        );
    }

    private function extractWithPhpParser(
        string $absolutePdfPath
    ): string {
        $parser =
            new Parser;

        $pdf =
            $parser->parseFile(
                $absolutePdfPath
            );

        $pageTexts = [];

        foreach ($pdf->getPages() as $page) {
            $pageText =
                $this->normalizeExtractedText(
                    (string) $page->getText()
                );

            if ($pageText !== '') {
                $pageTexts[] =
                    $pageText;
            }
        }

        /*
         * Gunakan form-feed sebagai pemisah halaman.
         *
         * DocumentChunker kita sudah memahami \f sebagai
         * page separator sehingga metadata halaman tetap
         * bisa dibuat.
         */

        $text =
            $pageTexts !== []
                ? implode(
                    "\f",
                    $pageTexts
                )
                : $this->normalizeExtractedText(
                    (string) $pdf->getText()
                );

        if ($text === '') {
            throw new RuntimeException(
                'PDF tidak menghasilkan text. '
                .'File mungkin berupa scan/image-only.'
            );
        }

        return $text;
    }

    private function normalizeExtractedText(
        string $text
    ): string {
        $text = str_replace(
            [
                "\r\n",
                "\r",
                "\0",
            ],
            [
                "\n",
                "\n",
                '',
            ],
            $text
        );

        return trim(
            $text
        );
    }

    private function cleanError(
        string $message
    ): string {
        $message = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $message
            ) ?? $message
        );

        return $message !== ''
            ? $message
            : 'unknown error';
    }
}