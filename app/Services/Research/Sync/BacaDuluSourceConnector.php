<?php

namespace App\Services\Research\Sync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BacaDuluSourceConnector implements SourceConnector
{
    public function code(): string
    {
        return 'bacadulu';
    }

    public function discover(): array
    {
        $config =
            $this->config();

        $items = [];

        $page =
            1;

        $maximumPages =
            max(
                1,
                (int) (
                    $config[
                        'max_pages'
                    ]
                    ?? 25
                )
            );

        $pageSize =
            max(
                1,
                min(
                    100,
                    (int) (
                        $config[
                            'page_size'
                        ]
                        ?? 50
                    )
                )
            );

        do {
            $response =
                $this
                    ->client(
                        $config
                    )
                    ->get(
                        $this->endpoint(
                            (string)
                                $config[
                                    'discover_path'
                                ],
                            $config
                        ),
                        [
                            'page' =>
                                $page,

                            'per_page' =>
                                $pageSize,
                        ]
                    );

            $payload =
                $this->json(
                    $response,
                    'discover'
                );

            $pageItems =
                $this->listItems(
                    $payload
                );

            foreach (
                $pageItems as
                $item
            ) {
                $items[] =
                    $this
                        ->normalizeDiscoveryItem(
                            $item
                        );
            }

            $lastPage =
                $this->lastPage(
                    $payload
                );

            $page++;

        } while (
            $page <= $lastPage
            &&
            $page <= $maximumPages
        );

        return collect(
            $items
        )
            ->unique(
                'external_id'
            )
            ->values()
            ->all();
    }

    public function fetch(
        string $externalId
    ): array {
        $externalId =
            trim(
                $externalId
            );

        if (
            $externalId === ''
        ) {
            throw new RuntimeException(
                'External source ID tidak boleh kosong.'
            );
        }

        $config =
            $this->config();

        $path =
            str_replace(
                '{external_id}',
                rawurlencode(
                    $externalId
                ),
                (string)
                    $config[
                        'fetch_path'
                    ]
            );

        $response =
            $this
                ->client(
                    $config
                )
                ->get(
                    $this->endpoint(
                        $path,
                        $config
                    )
                );

        $payload =
            $this->json(
                $response,
                'fetch'
            );

        $item =
            $this->detailItem(
                $payload
            );

        $normalizedId =
            $this->externalId(
                $item
            );

        /*
        |--------------------------------------------------------------------------
        | Request / Response ID Consistency
        |--------------------------------------------------------------------------
        */

        if (
            $normalizedId
            !==
            $externalId
        ) {
            throw new RuntimeException(
                'Source ID dari response tidak sesuai dengan source yang diminta.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Research menerima extracted text.
        |--------------------------------------------------------------------------
        |
        | Tidak perlu mengunduh raw PDF dari server utama.
        |
        */

        $text =
            trim(
                (string) (
                    $item[
                        'extracted_text'
                    ]
                    ??
                    $item[
                        'text'
                    ]
                    ??
                    $item[
                        'content'
                    ]
                    ??
                    ''
                )
            );

        if (
            $text === ''
        ) {
            throw new RuntimeException(
                'BacaDulu connector tidak menerima extracted_text untuk source ['
                .$externalId
                .'].'
            );
        }

        return [
            'external_id' =>
                $normalizedId,

            'title' =>
                $this->title(
                    $item
                ),

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
                $this->metadata(
                    $item
                ),
        ];
    }

    private function config(): array
    {
        $config =
            config(
                'source-sync.connectors.bacadulu',
                []
            );

        if (
            ! is_array(
                $config
            )
        ) {
            throw new RuntimeException(
                'Konfigurasi BacaDulu Source Connector tidak valid.'
            );
        }

        $baseUrl =
            trim(
                (string) (
                    $config[
                        'base_url'
                    ]
                    ?? ''
                )
            );

        $discoverPath =
            trim(
                (string) (
                    $config[
                        'discover_path'
                    ]
                    ?? ''
                )
            );

        $fetchPath =
            trim(
                (string) (
                    $config[
                        'fetch_path'
                    ]
                    ?? ''
                )
            );

        if (
            $baseUrl === ''
        ) {
            throw new RuntimeException(
                'SOURCE_SYNC_BACADULU_BASE_URL belum dikonfigurasi.'
            );
        }

        if (
            $discoverPath === ''
            ||
            $fetchPath === ''
        ) {
            throw new RuntimeException(
                'Endpoint BacaDulu Source Connector belum dikonfigurasi lengkap.'
            );
        }

        if (
            ! str_contains(
                $fetchPath,
                '{external_id}'
            )
        ) {
            throw new RuntimeException(
                'SOURCE_SYNC_BACADULU_FETCH_PATH wajib mengandung {external_id}.'
            );
        }

        $parts =
            parse_url(
                $baseUrl
            );

        $scheme =
            strtolower(
                (string) (
                    $parts[
                        'scheme'
                    ]
                    ?? ''
                )
            );

        $host =
            trim(
                (string) (
                    $parts[
                        'host'
                    ]
                    ?? ''
                )
            );

        if (
            $host === ''
            ||
            ! in_array(
                $scheme,
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'SOURCE_SYNC_BACADULU_BASE_URL harus berupa URL HTTP/HTTPS yang valid.'
            );
        }

        $allowInsecure =
            (bool) (
                $config[
                    'allow_insecure_http'
                ]
                ?? false
            );

        /*
        |--------------------------------------------------------------------------
        | Production Security
        |--------------------------------------------------------------------------
        */

        if (
            $scheme !== 'https'
            &&
            ! $allowInsecure
        ) {
            throw new RuntimeException(
                'BacaDulu Source Connector mewajibkan HTTPS. Untuk development lokal saja, aktifkan SOURCE_SYNC_BACADULU_ALLOW_INSECURE_HTTP=true.'
            );
        }

        $requireToken =
            (bool) (
                $config[
                    'require_token'
                ]
                ?? true
            );

        $token =
            trim(
                (string) (
                    $config[
                        'token'
                    ]
                    ?? ''
                )
            );

        if (
            $requireToken
            &&
            $token === ''
        ) {
            throw new RuntimeException(
                'SOURCE_SYNC_BACADULU_TOKEN belum dikonfigurasi.'
            );
        }

        return $config;
    }

    private function client(
        array $config
    ): PendingRequest {
        $request =
            Http::acceptJson()
                ->timeout(
                    max(
                        5,
                        (int) (
                            $config[
                                'timeout'
                            ]
                            ?? 30
                        )
                    )
                )
                ->connectTimeout(
                    max(
                        2,
                        (int) (
                            $config[
                                'connect_timeout'
                            ]
                            ?? 10
                        )
                    )
                )
                ->retry(
                    max(
                        0,
                        (int) (
                            $config[
                                'retry_times'
                            ]
                            ?? 2
                        )
                    ),
                    max(
                        100,
                        (int) (
                            $config[
                                'retry_sleep_ms'
                            ]
                            ?? 300
                        )
                    ),
                    throw: false
                )
                ->withOptions([
                    'verify' =>
                        (bool) (
                            $config[
                                'verify_ssl'
                            ]
                            ?? true
                        ),
                ])
                ->withHeaders([
                    'User-Agent' =>
                        (string) (
                            $config[
                                'user_agent'
                            ]
                            ??
                            'BacaDulu-Research-SourceSync/1.0'
                        ),

                    'X-BacaDulu-Connector' =>
                        'research-source-sync',
                ]);

        $token =
            trim(
                (string) (
                    $config[
                        'token'
                    ]
                    ?? ''
                )
            );

        if (
            $token !== ''
        ) {
            $request =
                $request
                    ->withToken(
                        $token
                    );
        }

        return $request;
    }

    private function endpoint(
        string $path,
        array $config
    ): string {
        $baseUrl =
            rtrim(
                (string)
                    $config[
                        'base_url'
                    ],
                '/'
            );

        $path =
            '/'
            .ltrim(
                $path,
                '/'
            );

        return $baseUrl
            .$path;
    }

    private function json(
        Response $response,
        string $operation
    ): array {
        if (
            ! $response
                ->successful()
        ) {
            throw new RuntimeException(
                'BacaDulu Source Connector gagal saat '
                .$operation
                .' (HTTP '
                .$response->status()
                .').'
            );
        }

        $payload =
            $response
                ->json();

        if (
            ! is_array(
                $payload
            )
        ) {
            throw new RuntimeException(
                'Response BacaDulu Source Connector bukan JSON object yang valid.'
            );
        }

        return $payload;
    }

    private function listItems(
        array $payload
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Supported:
        |--------------------------------------------------------------------------
        |
        | { data: { items: [] } }
        | { data: [] }
        | { items: [] }
        |
        */

        $items =
            data_get(
                $payload,
                'data.items'
            );

        if (
            ! is_array(
                $items
            )
        ) {
            $items =
                $payload[
                    'data'
                ]
                ?? null;
        }

        if (
            ! is_array(
                $items
            )
        ) {
            $items =
                $payload[
                    'items'
                ]
                ?? null;
        }

        if (
            ! is_array(
                $items
            )
        ) {
            throw new RuntimeException(
                'Response discover BacaDulu tidak mempunyai array data/items.'
            );
        }

        if (
            ! array_is_list(
                $items
            )
        ) {
            throw new RuntimeException(
                'Response discover BacaDulu harus berupa list source.'
            );
        }

        return $items;
    }

    private function detailItem(
        array $payload
    ): array {
        $item =
            $payload[
                'data'
            ]
            ??
            $payload[
                'source'
            ]
            ??
            $payload;

        if (
            ! is_array(
                $item
            )
            ||
            array_is_list(
                $item
            )
        ) {
            throw new RuntimeException(
                'Response detail BacaDulu tidak mempunyai object source yang valid.'
            );
        }

        return $item;
    }

    private function normalizeDiscoveryItem(
        array $item
    ): array {
        return [
            'external_id' =>
                $this->externalId(
                    $item
                ),

            'title' =>
                $this->title(
                    $item
                ),

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
                $this->metadata(
                    $item
                ),
        ];
    }

    private function externalId(
        array $item
    ): string {
        $externalId =
            trim(
                (string) (
                    $item[
                        'external_id'
                    ]
                    ??
                    $item[
                        'id'
                    ]
                    ??
                    $item[
                        'uuid'
                    ]
                    ??
                    ''
                )
            );

        if (
            $externalId === ''
        ) {
            throw new RuntimeException(
                'Source BacaDulu tidak mempunyai external_id/id/uuid.'
            );
        }

        return $externalId;
    }

    private function title(
        array $item
    ): string {
        $title =
            trim(
                (string) (
                    $item[
                        'title'
                    ]
                    ?? ''
                )
            );

        if (
            $title === ''
        ) {
            throw new RuntimeException(
                'Source BacaDulu tidak mempunyai title.'
            );
        }

        return $title;
    }

    private function checksum(
        array $item
    ): string {
        $checksum =
            trim(
                (string) (
                    $item[
                        'checksum'
                    ]
                    ??
                    $item[
                        'content_checksum'
                    ]
                    ??
                    ''
                )
            );

        if (
            $checksum === ''
        ) {
            throw new RuntimeException(
                'Source BacaDulu tidak mempunyai checksum/content_checksum. Checksum wajib untuk diff yang aman.'
            );
        }

        /*
         * Column source_sync_items.external_checksum
         * maksimum 128 karakter.
         */

        if (
            strlen(
                $checksum
            )
            > 128
        ) {
            throw new RuntimeException(
                'Checksum source BacaDulu melebihi batas 128 karakter.'
            );
        }

        return $checksum;
    }

    private function metadata(
        array $item
    ): array {
        $metadata =
            is_array(
                $item[
                    'metadata'
                ]
                ?? null
            )
                ? $item[
                    'metadata'
                ]
                : [];

        /*
         * Remote boleh mengirim metadata langsung
         * di root atau di object metadata.
         */

        foreach (
            [
                'abstract',
                'publisher',
                'publication_year',
                'language',
                'doi',
                'isbn',
                'source_label',
            ] as
            $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $item
                )
                &&
                $item[
                    $key
                ]
                !== null
            ) {
                $metadata[
                    $key
                ] =
                    $item[
                        $key
                    ];
            }
        }

        return $metadata;
    }

    private function lastPage(
        array $payload
    ): int {
        $lastPage =
            data_get(
                $payload,
                'meta.last_page'
            )
            ??
            data_get(
                $payload,
                'pagination.last_page'
            )
            ??
            $payload[
                'last_page'
            ]
            ??
            1;

        return max(
            1,
            (int)
                $lastPage
        );
    }
}