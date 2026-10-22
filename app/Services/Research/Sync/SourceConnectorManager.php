<?php

namespace App\Services\Research\Sync;

use RuntimeException;

class SourceConnectorManager
{
    public function driver(
        ?string $connector = null
    ): SourceConnector {
        $connector =
            strtolower(
                trim(
                    (string) (
                        $connector
                        ?:
                        config(
                            'source-sync.provider',
                            'mock'
                        )
                    )
                )
            );

        return match (
            $connector
        ) {
            'mock' =>
                app(
                    MockSourceConnector::class
                ),

            'bacadulu' =>
                app(
                    BacaDuluSourceConnector::class
                ),

            default =>
                throw new RuntimeException(
                    'Source connector ['
                    .$connector
                    .'] belum tersedia. Connector yang didukung: mock, bacadulu.'
                ),
        };
    }
}