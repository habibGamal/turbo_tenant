import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        host: 'localhost', // External browsers should look at your local machine
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: {
            host: 'localhost',
            protocol: 'ws', // Use 'wss' if you are using a local SSL cert
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.tsx', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
