import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    base: '/vendor/statamic-gutenberg/build/',
    plugins: [
        react(),
        laravel({
            input: [
                'resources/js/addon.js',
                'resources/js/editor-window.js',
                'resources/css/addon.css',
            ],
            publicDirectory: 'resources/dist',
        }),
        statamic(),
    ],
    define: {
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
    },
});
