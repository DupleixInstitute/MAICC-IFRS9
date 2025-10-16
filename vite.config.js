import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    server: {
        host:process.env.VITE_HOST || 'localhost', // Allow access from outside the container
        origin: 'http://localhost:5173',
        cors: true,
        port: 5173,
        strictPort: true, // Ensures it uses the specified port
        watch: {
            usePolling: true, // Helps with file changes in Docker volumes
        },
        headers: {
            'Access-Control-Allow-Origin': '*',
        },
    },
});
