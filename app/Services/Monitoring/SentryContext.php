<?php

namespace App\Services\Monitoring;

use App\Jobs\ProcessResearchRun;
use App\Jobs\ProcessSourceIngestion;
use App\Models\AiRun;
use App\Models\Document;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Throwable;

use function Sentry\configureScope;

class SentryContext
{
    public function forRequest(
        Request $request
    ): void {
        configureScope(
            function (
                Scope $scope
            ) use (
                $request
            ): void {
                $this->resetDomainScope(
                    $scope
                );

                $routeName =
                    (string) (
                        $request
                            ->route()
                            ?->getName()
                        ?: 'unnamed'
                    );

                $isAdmin =
                    str_starts_with(
                        $routeName,
                        'admin.'
                    );

                $adminUserId =
                    $request
                        ->session()
                        ->get(
                            'research_admin_user_id'
                        );

                $webUser =
                    $request
                        ->user();

                /*
                |--------------------------------------------------------------------------
                | User Context
                |--------------------------------------------------------------------------
                |
                | HANYA ID.
                |
                | Tidak kirim:
                |
                | - nama
                | - email
                | - nomor HP
                | - alamat
                | - isi research
                |
                */

                if (
                    $isAdmin
                    &&
                    $adminUserId
                ) {
                    $scope->setUser([
                        'id' =>
                            (string)
                                $adminUserId,
                    ]);

                    $scope->setTag(
                        'actor.type',
                        'admin'
                    );
                } elseif ($webUser) {
                    $scope->setUser([
                        'id' =>
                            (string)
                                $webUser
                                    ->getAuthIdentifier(),
                    ]);

                    $scope->setTag(
                        'actor.type',
                        'user'
                    );
                } else {
                    $scope->setTag(
                        'actor.type',
                        'guest'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Searchable Tags
                |--------------------------------------------------------------------------
                */

                $scope->setTag(
                    'app.area',
                    $this->requestArea(
                        $routeName
                    )
                );

                $scope->setTag(
                    'route.name',
                    $routeName
                );

                $scope->setTag(
                    'http.method',
                    strtoupper(
                        $request
                            ->method()
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | Safe Request Context
                |--------------------------------------------------------------------------
                |
                | Jangan pernah masukkan:
                |
                | $request->all()
                | $request->input()
                |
                */

                $scope->setContext(
                    'bacadulu_request',
                    [
                        'route' =>
                            $routeName,

                        'method' =>
                            strtoupper(
                                $request
                                    ->method()
                            ),

                        'path' =>
                            '/'
                            .ltrim(
                                $request
                                    ->path(),
                                '/'
                            ),
                    ]
                );
            }
        );
    }

    public function forQueueJob(
        QueueJob $queueJob
    ): void {
        configureScope(
            function (
                Scope $scope
            ) use (
                $queueJob
            ): void {
                $this->resetDomainScope(
                    $scope
                );

                $scope->setTag(
                    'app.area',
                    'queue'
                );

                $scope->setTag(
                    'queue.name',
                    (string) (
                        $queueJob
                            ->getQueue()
                        ?: 'default'
                    )
                );

                $command =
                    $this->commandFromPayload(
                        $queueJob
                            ->payload()
                    );

                if (! $command) {
                    return;
                }

                $scope->setTag(
                    'queue.job',
                    $command::class
                );

                /*
                |--------------------------------------------------------------------------
                | AI Research Queue
                |--------------------------------------------------------------------------
                */

                if (
                    $command
                    instanceof
                    ProcessResearchRun
                ) {
                    $run =
                        AiRun::query()
                            ->select([
                                'id',
                                'uuid',
                                'user_id',
                                'status',
                            ])
                            ->find(
                                $command
                                    ->aiRunId
                            );

                    if (
                        $run
                            ?->user_id
                    ) {
                        $scope->setUser([
                            'id' =>
                                (string)
                                    $run
                                        ->user_id,
                        ]);
                    }

                    $scope->setTag(
                        'actor.type',
                        'user'
                    );

                    $scope->setTag(
                        'queue.domain',
                        'research'
                    );

                    $scope->setContext(
                        'research_run',
                        [
                            'ai_run_id' =>
                                $run
                                    ?->id
                                ?:
                                $command
                                    ->aiRunId,

                            'ai_run_uuid' =>
                                $run
                                    ?->uuid,

                            'status' =>
                                $run
                                    ?->status,
                        ]
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | PDF Source Ingestion
                |--------------------------------------------------------------------------
                */

                if (
                    $command
                    instanceof
                    ProcessSourceIngestion
                ) {
                    $document =
                        Document::query()
                            ->select([
                                'id',
                                'uuid',
                                'uploaded_by',
                                'status',
                                'processing_stage',
                            ])
                            ->find(
                                $command
                                    ->documentId
                            );

                    $actorId =
                        $command
                            ->adminUserId
                        ?:
                        $document
                            ?->uploaded_by;

                    if ($actorId) {
                        $scope->setUser([
                            'id' =>
                                (string)
                                    $actorId,
                        ]);
                    }

                    $scope->setTag(
                        'actor.type',
                        'admin'
                    );

                    $scope->setTag(
                        'queue.domain',
                        'source_ingestion'
                    );

                    $scope->setContext(
                        'source_ingestion',
                        [
                            'document_id' =>
                                $document
                                    ?->id
                                ?:
                                $command
                                    ->documentId,

                            'document_uuid' =>
                                $document
                                    ?->uuid,

                            'document_file_id' =>
                                $command
                                    ->documentFileId,

                            'status' =>
                                $document
                                    ?->status,

                            'processing_stage' =>
                                $document
                                    ?->processing_stage,

                            'target_status' =>
                                $command
                                    ->targetStatus,
                        ]
                    );
                }
            }
        );
    }

    public function clear(): void
    {
        configureScope(
            function (
                Scope $scope
            ): void {
                $this->resetDomainScope(
                    $scope
                );
            }
        );
    }

    private function commandFromPayload(
        array $payload
    ): ?object {
        $serialized =
            data_get(
                $payload,
                'data.command'
            );

        if (
            ! is_string(
                $serialized
            )
            ||
            $serialized === ''
        ) {
            return null;
        }

        try {
            $command =
                unserialize(
                    $serialized,
                    [
                        'allowed_classes' =>
                            true,
                    ]
                );

            return is_object(
                $command
            )
                ? $command
                : null;
        } catch (
            Throwable
        ) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Only BacaDulu Context
    |--------------------------------------------------------------------------
    |
    | Jangan Scope::clear().
    |
    | Scope::clear() bisa ikut menghapus data tracing SDK.
    |
    */

    private function resetDomainScope(
        Scope $scope
    ): void {
        $scope->removeUser();

        foreach (
            [
                'actor.type',
                'app.area',
                'route.name',
                'http.method',
                'queue.name',
                'queue.job',
                'queue.domain',
            ]
            as
            $tag
        ) {
            $scope->removeTag(
                $tag
            );
        }

        foreach (
            [
                'bacadulu_request',
                'research_run',
                'source_ingestion',
            ]
            as
            $context
        ) {
            $scope->removeContext(
                $context
            );
        }
    }

    private function requestArea(
        string $routeName
    ): string {
        return match (true) {

            str_starts_with(
                $routeName,
                'admin.'
            ) =>
                'admin',

            str_starts_with(
                $routeName,
                'research.'
            ) =>
                'research',

            str_starts_with(
                $routeName,
                'workspace'
            ) =>
                'research',

            str_starts_with(
                $routeName,
                'documents.'
            ) =>
                'documents',

            str_starts_with(
                $routeName,
                'saved.'
            ) =>
                'saved_results',

            str_starts_with(
                $routeName,
                'profile.'
            ) =>
                'profile',

            str_starts_with(
                $routeName,
                'verification.'
            ) =>
                'auth',

            str_starts_with(
                $routeName,
                'google.'
            ) =>
                'auth',

            in_array(
                $routeName,
                [
                    'login',
                    'login.store',
                    'register',
                    'register.store',
                    'logout',
                ],
                true
            ) =>
                'auth',

            default =>
                'web',
        };
    }
}