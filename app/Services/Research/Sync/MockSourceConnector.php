<?php

namespace App\Services\Research\Sync;

use RuntimeException;

class MockSourceConnector implements SourceConnector
{
    public function code(): string
    {
        return 'mock';
    }

    public function discover(): array
    {
        return collect(
            $this->items()
        )
            ->map(
                fn (
                    array $item
                ): array => [
                    'external_id' =>
                        (string)
                            $item[
                                'external_id'
                            ],

                    'title' =>
                        (string)
                            $item[
                                'title'
                            ],

                    'checksum' =>
                        $this->checksum(
                            $item
                        ),

                    'updated_at' =>
                        $item[
                            'updated_at'
                        ]
                        ?? null,

                    'metadata' =>
                        is_array(
                            $item[
                                'metadata'
                            ]
                            ?? null
                        )
                            ? $item[
                                'metadata'
                            ]
                            : [],
                ]
            )
            ->values()
            ->all();
    }

    public function fetch(
        string $externalId
    ): array {
        $item =
            collect(
                $this->items()
            )
                ->first(
                    fn (
                        array $candidate
                    ): bool =>
                        (string) (
                            $candidate[
                                'external_id'
                            ]
                            ?? ''
                        )
                        ===
                        $externalId
                );

        if (! $item) {
            throw new RuntimeException(
                'Mock source tidak ditemukan: '
                .$externalId
            );
        }

        $text =
            trim(
                (string) (
                    $item[
                        'extracted_text'
                    ]
                    ?? ''
                )
            );

        if ($text === '') {
            throw new RuntimeException(
                'Mock source tidak mempunyai extracted text.'
            );
        }

        return [
            'external_id' =>
                (string)
                    $item[
                        'external_id'
                    ],

            'title' =>
                (string)
                    $item[
                        'title'
                    ],

            'checksum' =>
                $this->checksum(
                    $item
                ),

            'updated_at' =>
                $item[
                    'updated_at'
                ]
                ?? null,

            'extracted_text' =>
                $text,

            'metadata' =>
                is_array(
                    $item[
                        'metadata'
                    ]
                    ?? null
                )
                    ? $item[
                        'metadata'
                    ]
                    : [],
        ];
    }

    private function items(): array
    {
        $items =
            config(
                'source-sync.connectors.mock.items',
                []
            );

        if (! is_array($items)) {
            throw new RuntimeException(
                'Konfigurasi mock Source Sync tidak valid.'
            );
        }

        foreach (
            $items as
            $item
        ) {
            if (
                ! is_array($item)
                ||
                blank(
                    $item[
                        'external_id'
                    ]
                    ?? null
                )
                ||
                blank(
                    $item[
                        'title'
                    ]
                    ?? null
                )
            ) {
                throw new RuntimeException(
                    'Setiap mock source wajib mempunyai external_id dan title.'
                );
            }
        }

        return array_values(
            $items
        );
    }

    private function checksum(
        array $item
    ): string {
        $payload = [
            'external_id' =>
                (string) (
                    $item[
                        'external_id'
                    ]
                    ?? ''
                ),

            'title' =>
                (string) (
                    $item[
                        'title'
                    ]
                    ?? ''
                ),

            'updated_at' =>
                $item[
                    'updated_at'
                ]
                ?? null,

            'extracted_text' =>
                trim(
                    (string) (
                        $item[
                            'extracted_text'
                        ]
                        ?? ''
                    )
                ),

            'metadata' =>
                is_array(
                    $item[
                        'metadata'
                    ]
                    ?? null
                )
                    ? $item[
                        'metadata'
                    ]
                    : [],
        ];

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_THROW_ON_ERROR
            )
        );
    }
}