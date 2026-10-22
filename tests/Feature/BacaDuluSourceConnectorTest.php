<?php

namespace Tests\Feature;

use App\Services\Research\Sync\BacaDuluSourceConnector;
use App\Services\Research\Sync\SourceConnectorManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BacaDuluSourceConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'source-sync.connectors.bacadulu',
            [
                'base_url' =>
                    'https://bacadulu.example.test',

                'token' =>
                    'test-secret-token',

                'require_token' =>
                    true,

                'discover_path' =>
                    '/api/research/sources',

                'fetch_path' =>
                    '/api/research/sources/{external_id}',

                'page_size' =>
                    50,

                'max_pages' =>
                    25,

                'timeout' =>
                    30,

                'connect_timeout' =>
                    10,

                'retry_times' =>
                    0,

                'retry_sleep_ms' =>
                    100,

                'verify_ssl' =>
                    true,

                'allow_insecure_http' =>
                    false,

                'user_agent' =>
                    'BacaDulu-Research-SourceSync-Test/1.0',
            ]
        );
    }

    public function test_manager_resolves_bacadulu_connector(): void
    {
        $connector =
            app(
                SourceConnectorManager::class
            )
                ->driver(
                    'bacadulu'
                );

        $this->assertInstanceOf(
            BacaDuluSourceConnector::class,
            $connector
        );

        $this->assertSame(
            'bacadulu',
            $connector->code()
        );
    }

    public function test_discover_maps_remote_sources_into_internal_contract(): void
    {
        Http::fake([
            'https://bacadulu.example.test/api/research/sources*' =>
                Http::response(
                    [
                        'data' => [

                            [
                                'id' =>
                                    'BD-SRC-001',

                                'title' =>
                                    'ESG Disclosure and Firm Value',

                                'content_checksum' =>
                                    str_repeat(
                                        'a',
                                        64
                                    ),

                                'updated_at' =>
                                    '2026-10-02T10:00:00+07:00',

                                'publisher' =>
                                    'BacaDulu Publisher',

                                'publication_year' =>
                                    2026,

                                'language' =>
                                    'en',
                            ],

                        ],

                        'meta' => [
                            'current_page' =>
                                1,

                            'last_page' =>
                                1,
                        ],
                    ],
                    200
                ),
        ]);

        $items =
            app(
                BacaDuluSourceConnector::class
            )
                ->discover();

        $this->assertCount(
            1,
            $items
        );

        $this->assertSame(
            'BD-SRC-001',
            $items[
                0
            ][
                'external_id'
            ]
        );

        $this->assertSame(
            'ESG Disclosure and Firm Value',
            $items[
                0
            ][
                'title'
            ]
        );

        $this->assertSame(
            str_repeat(
                'a',
                64
            ),
            $items[
                0
            ][
                'checksum'
            ]
        );

        $this->assertSame(
            'BacaDulu Publisher',
            data_get(
                $items[
                    0
                ],
                'metadata.publisher'
            )
        );

        /*
         * Pastikan connector benar-benar
         * mengirim authentication header.
         */

        Http::assertSent(
            function (
                Request $request
            ): bool {
                return $request
                    ->method()
                    ===
                    'GET'

                    &&

                    str_starts_with(
                        $request
                            ->url(),
                        'https://bacadulu.example.test/api/research/sources'
                    )

                    &&

                    $request
                        ->hasHeader(
                            'Authorization',
                            'Bearer test-secret-token'
                        )

                    &&

                    $request
                        ->hasHeader(
                            'X-BacaDulu-Connector',
                            'research-source-sync'
                        );
            }
        );
    }

    public function test_fetch_maps_extracted_text_and_metadata_without_using_remote_pdf(): void
    {
        Http::fake([
            'https://bacadulu.example.test/api/research/sources/BD-SRC-001' =>
                Http::response(
                    [
                        'data' => [

                            'external_id' =>
                                'BD-SRC-001',

                            'title' =>
                                'ESG Disclosure and Firm Value',

                            'checksum' =>
                                str_repeat(
                                    'b',
                                    64
                                ),

                            'updated_at' =>
                                '2026-10-02T10:10:00+07:00',

                            'extracted_text' =>
                                'ESG disclosure quality is associated with firm value and information asymmetry. This text is intentionally returned as extracted text rather than a public PDF file.',

                            'metadata' => [

                                'publisher' =>
                                    'BacaDulu Publisher',

                                'publication_year' =>
                                    2026,

                                'language' =>
                                    'en',

                                'source_label' =>
                                    'BacaDulu Library',

                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $source =
            app(
                BacaDuluSourceConnector::class
            )
                ->fetch(
                    'BD-SRC-001'
                );

        $this->assertSame(
            'BD-SRC-001',
            $source[
                'external_id'
            ]
        );

        $this->assertSame(
            str_repeat(
                'b',
                64
            ),
            $source[
                'checksum'
            ]
        );

        $this->assertStringContainsString(
            'ESG disclosure',
            $source[
                'extracted_text'
            ]
        );

        $this->assertSame(
            'BacaDulu Library',
            data_get(
                $source,
                'metadata.source_label'
            )
        );
    }

    public function test_connector_rejects_plain_http_by_default(): void
    {
        config()->set(
            'source-sync.connectors.bacadulu.base_url',
            'http://bacadulu.example.test'
        );

        config()->set(
            'source-sync.connectors.bacadulu.allow_insecure_http',
            false
        );

        Http::fake();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'mewajibkan HTTPS'
        );

        app(
            BacaDuluSourceConnector::class
        )
            ->discover();
    }
}