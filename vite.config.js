import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/theme-profiles.js', 'resources/js/maintenance-transaction-links.js', 'resources/js/transaction-category-controls.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
