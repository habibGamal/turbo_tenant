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
            overlay: false,
        },
        watch: {
            ignored: ['**/test-results/**', '**/playwright-report/**', '**/storage/**', '**/.git/**'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.tsx', 'resources/css/app.css', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('react/') || id.includes('react-dom/') || id.includes('react-router')) {
                            return 'vendor-react';
                        }
                        if (id.includes('@tanstack/react-query')) {
                            return 'vendor-query';
                        }
                        if (id.includes('lucide-react')) {
                            return 'vendor-icons';
                        }
                        if (id.includes('@radix-ui')) {
                            return 'vendor-radix';
                        }
                        if (id.includes('i18next')) {
                            return 'vendor-i18n';
                        }
                    }
                },
            },
        },
    },
});
