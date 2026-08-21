import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    // Bind the dev server to IPv4 loopback so injected script URLs always use
    // http://127.0.0.1:5173 — matching the local-only CSP exception below.
    // Production builds are unaffected; this key is ignored by `vite build`.
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
});