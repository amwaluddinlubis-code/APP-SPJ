import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/theme-profiles.js', 'resources/js/maintenance-transaction-links.js', 'resources/js/transaction-category-controls.js', 'resources/js/spj-preparation-state-normalizer.js', 'resources/js/transaction-summary-three-column.js', 'resources/js/legacy-action-icon-migrator.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
