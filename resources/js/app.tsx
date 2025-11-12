import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/contexts/ThemeContext';
import { getTheme } from '@/lib/theme-registry';
import { getThemeName, resolveThemedPage } from '@/lib/theme-resolver';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        // Try to resolve from theme first, fallback to default Pages
        try {
            return await resolveThemedPage(name);
        } catch {
            // If theme resolver fails, use default Laravel resolution
            return resolvePageComponent(
                `./Pages/${name}.tsx`,
                import.meta.glob('./Pages/**/*.tsx'),
            );
        }
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Get theme configuration based on tenant or default
        const themeName = getThemeName(props.initialPage.props);
        const themeConfig = getTheme(themeName);

        root.render(
            <ThemeProvider theme={themeConfig}>
                <App {...props} />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
