import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/pages/research-output.css',

                'resources/js/app.js',
                'resources/js/research-output.js',
                'resources/js/auth-research.js',
            ],

            refresh: true,

            hotFile: 'storage/framework/vite.hot',
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