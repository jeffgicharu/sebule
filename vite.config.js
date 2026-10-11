import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                // Each family names the fonts a device already has that are shaped most like it. The plugin
                // sizes those to match, so text keeps its place when the real font arrives a moment later.
                bunny('Young Serif', {
                    weights: [400],
                    fallbacks: ['Georgia', 'Times New Roman'],
                }),
                bunny('Hanken Grotesk', {
                    weights: [400, 500, 600],
                    fallbacks: ['Arial', 'Helvetica Neue'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
