import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // app.* loads on every page; home.js, type.js and project.js only on their page.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/home.js', 'resources/js/type.js', 'resources/js/project.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
