import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',

                /*
                |--------------------------------------------------------------------------
                | Admin styles (loaded by the admin layouts instead of app.css)
                |--------------------------------------------------------------------------
                */

                'resources/css/admin.css',

                'resources/js/admin.js',

                'resources/js/app.js',

                /*
                |--------------------------------------------------------------------------
                | Workspace UI
                |--------------------------------------------------------------------------
                */

                'resources/js/workspace-ui.js',

                /*
                |--------------------------------------------------------------------------
                | Landing motion
                |--------------------------------------------------------------------------
                */

                'resources/js/landing-motion.js',

                'resources/js/research-output.js',

                'resources/js/auth-research.js',

                'resources/js/bacadulu-onboarding.js',
            ],

            refresh: true,

            hotFile:
                'storage/framework/vite.hot',
        }),

        tailwindcss(),
    ],

    server: {
        watch: {
            ignored: [
                '**/storage/framework/views/**',
            ],
        },
    },
});     