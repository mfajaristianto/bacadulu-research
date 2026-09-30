<?php

namespace App\Services\Research;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    public function extract(
        string $absolutePdfPath
    ): string {
        if (! is_file($absolutePdfPath)) {
            throw new RuntimeException(
                'File PDF tidak ditemukan.'
            );
        }

        if (! function_exists('proc_open')) {
            throw new RuntimeException(
                'Server menonaktifkan proc_open. Gunakan field Extracted Text atau konfigurasi parser PDF lain.'
            );
        }

        $binary = trim(
            (string) config(
                'research.ingestion.pdftotext_binary',
                'pdftotext'
            )
        );

        if ($binary === '') {
            throw new RuntimeException(
                'PDFTOTEXT_BINARY belum dikonfigurasi.'
            );
        }

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

            throw new RuntimeException(
                $error !== ''
                    ? 'PDF extraction gagal: '.$error
                    : 'PDF extraction gagal. Pastikan pdftotext tersedia atau gunakan Extracted Text.'
            );
        }

        $text = trim(
            $process->getOutput()
        );

        if ($text === '') {
            throw new RuntimeException(
                'PDF tidak menghasilkan text. File mungkin berupa scan/image-only. Gunakan Extracted Text atau OCR pada tahap berikutnya.'
            );
        }

        return $text;
    }
}
