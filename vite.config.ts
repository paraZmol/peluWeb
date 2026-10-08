import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            // A second lane builds into its own folder (see CLAUDE.md, carriles).
            buildDirectory: process.env.VITE_BUILD_DIR ?? 'build',
            refresh: true,
            // Platform fonts are preloaded. Template fonts (ui-system §3) are
            // only declared: a browser downloads a @font-face file the first
            // time some text uses it, so a salon page fetches its own pair.
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [
                        { weight: 400 },
                        { weight: 500 },
                        { weight: 600 },
                    ],
                }),
                bunny('Fraunces', {
                    weights: [500, 600],
                }),
                bunny('Bebas Neue', { weights: [400], preload: false }),
                bunny('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                    preload: false,
                }),
                bunny('DM Serif Display', {
                    weights: [400],
                    styles: ['normal', 'italic'],
                    preload: false,
                }),
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    preload: false,
                }),
                bunny('Syne', { weights: [700, 800], preload: false }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            '.claude/**',
            'docs/**',
            'CLAUDE.md',
            'composer.json',
            'public/**',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
