<?php

namespace App\Services\Documents;

use App\Models\DocumentGeneration;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentRenderService
{
    public function __construct(
        private readonly DocxRenderer $docxRenderer,
        private readonly PdfRenderer $pdfRenderer
    ) {}

    public function render(
        DocumentGeneration $generation
    ): DocumentGeneration {
        $disk =
            (string) config(
                'research.documents.disk',
                'research_outputs'
            );

        $content =
            is_array(
                $generation
                    ->content_json
            )
                ? $generation
                    ->content_json
                : [];

        if (
            empty(
                $content['title']
            )
            || empty(
                $content['sections']
            )
        ) {
            throw new RuntimeException(
                'Structured document content tidak valid.'
            );
        }

        $directory =
            'documents/'
            .$generation->user_id
            .'/'
            .$generation->uuid;

        $docxPath =
            $directory
            .'/document.docx';

        $pdfPath =
            $directory
            .'/document.pdf';

        $docxAbsolute =
            Storage::disk(
                $disk
            )->path(
                $docxPath
            );

        $pdfAbsolute =
            Storage::disk(
                $disk
            )->path(
                $pdfPath
            );

        /*
         * DOCX sebenarnya merupakan ZIP package.
         *
         * Temp file sengaja memakai extension .zip
         * agar PharData dapat membuat archive
         * secara konsisten.
         */
        $docxTemp =
            $docxAbsolute
            .'.rendering.zip';

        $pdfTemp =
            $pdfAbsolute
            .'.rendering';

        $docxBackup =
            $docxAbsolute
            .'.backup';

        $pdfBackup =
            $pdfAbsolute
            .'.backup';

        $generation->update([
            'status' => 'generating',

            'error_message' => null,
        ]);

        try {
            $this->ensureDirectory(
                dirname(
                    $docxAbsolute
                )
            );

            $this->ensureDirectory(
                dirname(
                    $pdfAbsolute
                )
            );

            /*
             * Bersihkan temporary stale output.
             */
            $this->safeDelete(
                $docxTemp
            );

            $this->safeDelete(
                $pdfTemp
            );

            $this->safeDelete(
                $docxBackup
            );

            $this->safeDelete(
                $pdfBackup
            );

            /*
            |--------------------------------------------------------------------------
            | Render Temporary Outputs
            |--------------------------------------------------------------------------
            */

            $this
                ->docxRenderer
                ->render(
                    $content,
                    $docxTemp
                );

            $this
                ->pdfRenderer
                ->render(
                    $content,
                    $pdfTemp
                );

            $this->assertValidFile(
                $docxTemp,
                'DOCX'
            );

            $this->assertValidFile(
                $pdfTemp,
                'PDF'
            );

            /*
            |--------------------------------------------------------------------------
            | Backup Existing Outputs
            |--------------------------------------------------------------------------
            |
            | Saat user edit document dan render ulang,
            | file lama tidak langsung dihancurkan.
            |
            */

            if (
                is_file(
                    $docxAbsolute
                )
            ) {
                if (
                    ! rename(
                        $docxAbsolute,
                        $docxBackup
                    )
                ) {
                    throw new RuntimeException(
                        'DOCX existing tidak dapat dipersiapkan untuk replacement.'
                    );
                }
            }

            if (
                is_file(
                    $pdfAbsolute
                )
            ) {
                if (
                    ! rename(
                        $pdfAbsolute,
                        $pdfBackup
                    )
                ) {
                    $this->restoreBackup(
                        $docxBackup,
                        $docxAbsolute
                    );

                    throw new RuntimeException(
                        'PDF existing tidak dapat dipersiapkan untuk replacement.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Promote New DOCX
            |--------------------------------------------------------------------------
            */

            if (
                ! rename(
                    $docxTemp,
                    $docxAbsolute
                )
            ) {
                $this->restoreBackup(
                    $docxBackup,
                    $docxAbsolute
                );

                $this->restoreBackup(
                    $pdfBackup,
                    $pdfAbsolute
                );

                throw new RuntimeException(
                    'DOCX tidak dapat dipindahkan ke output final.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Promote New PDF
            |--------------------------------------------------------------------------
            */

            if (
                ! rename(
                    $pdfTemp,
                    $pdfAbsolute
                )
            ) {
                /*
                 * New DOCX belum dianggap valid
                 * karena pasangannya PDF gagal.
                 */
                $this->safeDelete(
                    $docxAbsolute
                );

                $this->restoreBackup(
                    $docxBackup,
                    $docxAbsolute
                );

                $this->restoreBackup(
                    $pdfBackup,
                    $pdfAbsolute
                );

                throw new RuntimeException(
                    'PDF tidak dapat dipindahkan ke output final.'
                );
            }

            /*
             * Kedua output baru sukses.
             * Backup lama boleh dihapus.
             */
            $this->safeDelete(
                $docxBackup
            );

            $this->safeDelete(
                $pdfBackup
            );

            $generation->update([
                'status' => 'completed',

                'docx_disk' => $disk,

                'docx_path' => $docxPath,

                'pdf_disk' => $disk,

                'pdf_path' => $pdfPath,

                'generated_at' => now(),

                'error_message' => null,
            ]);
        } catch (
            Throwable $exception
        ) {
            /*
             * Jangan menyisakan partial temporary
             * output setelah kegagalan.
             */
            $this->safeDelete(
                $docxTemp
            );

            $this->safeDelete(
                $pdfTemp
            );

            /*
             * Jika backup masih ada,
             * pulihkan output valid sebelumnya.
             */
            $this->restoreBackup(
                $docxBackup,
                $docxAbsolute
            );

            $this->restoreBackup(
                $pdfBackup,
                $pdfAbsolute
            );

            $generation->update([
                'status' => 'failed',

                'error_message' => $exception
                    ->getMessage(),
            ]);

            throw $exception;
        }

        return $generation
            ->fresh();
    }

    private function ensureDirectory(
        string $directory
    ): void {
        if (
            is_dir(
                $directory
            )
        ) {
            return;
        }

        if (
            ! mkdir(
                $directory,
                0775,
                true
            )
            && ! is_dir(
                $directory
            )
        ) {
            throw new RuntimeException(
                'Folder output dokumen tidak dapat dibuat.'
            );
        }
    }

    private function assertValidFile(
        string $path,
        string $label
    ): void {
        if (
            ! is_file(
                $path
            )
        ) {
            throw new RuntimeException(
                $label
                .' gagal dibuat.'
            );
        }

        $size =
            filesize(
                $path
            );

        if (
            $size === false
            || $size <= 0
        ) {
            throw new RuntimeException(
                $label
                .' output kosong.'
            );
        }
    }

    private function restoreBackup(
        string $backup,
        string $target
    ): void {
        if (
            ! is_file(
                $backup
            )
        ) {
            return;
        }

        $this->safeDelete(
            $target
        );

        @rename(
            $backup,
            $target
        );
    }

    private function safeDelete(
        string $path
    ): void {
        if (
            is_file(
                $path
            )
        ) {
            @unlink(
                $path
            );
        }
    }
}
