<?php

return [

    'default'
        => env(
            'FILESYSTEM_DISK',
            'local'
        ),

    'disks'
        => [

            'local'
                => [
                    'driver'
                        => 'local',

                    'root'
                        => storage_path(
                            'app/private'
                        ),

                    'serve'
                        => true,

                    'throw'
                        => false,

                    'report'
                        => false,
                ],

            'public'
                => [
                    'driver'
                        => 'local',

                    'root'
                        => storage_path(
                            'app/public'
                        ),

                    'url'
                        => rtrim(
                            env(
                                'APP_URL',
                                'http://localhost'
                            ),
                            '/'
                        )
                            .'/storage',

                    'visibility'
                        => 'public',

                    'throw'
                        => false,

                    'report'
                        => false,
                ],

            /*
            |--------------------------------------------------------------------------
            | Private Profile Avatar
            |--------------------------------------------------------------------------
            */

            'profile_avatars'
                => [
                    'driver'
                        => 'local',

                    'root'
                        => storage_path(
                            'app/private/profile-avatars'
                        ),

                    'visibility'
                        => 'private',

                    'throw'
                        => false,

                    'report'
                        => false,
                ],

            /*
            |--------------------------------------------------------------------------
            | Private Research Source PDF
            |--------------------------------------------------------------------------
            */

            'research_sources'
                => [
                    'driver'
                        => 'local',

                    'root'
                        => storage_path(
                            'app/private/research-sources'
                        ),

                    'visibility'
                        => 'private',

                    'throw'
                        => false,

                    'report'
                        => false,
                ],

            /*
            |--------------------------------------------------------------------------
            | Private Research Output
            |--------------------------------------------------------------------------
            |
            | DOCX dan PDF di-download melalui authenticated
            | controller route, bukan URL publik.
            |
            */

            'research_outputs'
                => [
                    'driver'
                        => 'local',

                    'root'
                        => storage_path(
                            'app/private/research-outputs'
                        ),

                    'visibility'
                        => 'private',

                    'throw'
                        => false,

                    'report'
                        => false,
                ],

            's3'
                => [
                    'driver'
                        => 's3',

                    'key'
                        => env(
                            'AWS_ACCESS_KEY_ID'
                        ),

                    'secret'
                        => env(
                            'AWS_SECRET_ACCESS_KEY'
                        ),

                    'region'
                        => env(
                            'AWS_DEFAULT_REGION'
                        ),

                    'bucket'
                        => env(
                            'AWS_BUCKET'
                        ),

                    'url'
                        => env(
                            'AWS_URL'
                        ),

                    'endpoint'
                        => env(
                            'AWS_ENDPOINT'
                        ),

                    'use_path_style_endpoint'
                        => env(
                            'AWS_USE_PATH_STYLE_ENDPOINT',
                            false
                        ),

                    'throw'
                        => false,

                    'report'
                        => false,
                ],
        ],

    'links'
        => [
            public_path(
                'storage'
            )
                => storage_path(
                    'app/public'
                ),
        ],

];