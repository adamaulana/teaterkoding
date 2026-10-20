import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/landing.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
    // CodeMirror 6 memakai `instanceof` lintas paket (@codemirror/state, view,
    // language, @lezer/*). Bila salah satunya termuat dua kali, ekstensi yang
    // sah akan ditolak dengan "Unrecognized extension value ... breaking
    // instanceof checks". Dedupe memaksa satu salinan di grafik modul.
    resolve: {
        dedupe: [
            '@codemirror/state',
            '@codemirror/view',
            '@codemirror/language',
            '@lezer/common',
            '@lezer/highlight',
        ],
    },
});
