import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [600, 700, 800],
                }),
                bunny('Fraunces', {
                    weights: [600, 700, 800],
                }),
                bunny('Playfair Display', {
                    weights: [600, 700, 800],
                }),
                bunny('Fredoka', {
                    weights: [500, 600, 700],
                }),
                bunny('Space Grotesk', {
                    weights: [500, 600, 700],
                }),
                bunny('Sora', {
                    weights: [600, 700, 800],
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
