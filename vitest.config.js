import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@wordpress/blocks': fileURLToPath(new URL('./node_modules/@wordpress/blocks/build/index.cjs', import.meta.url)),
        },
    },
    test: {
        include: ['resources/js/**/*.test.js'],
        exclude: ['vendor/**', 'node_modules/**'],
        environment: 'jsdom',
        globals: true,
    },
});
