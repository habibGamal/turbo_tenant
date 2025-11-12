import { ThemeConfig } from '@/types/theme';

export const defaultTheme: ThemeConfig = {
    name: 'Default Restaurant Theme',
    slug: 'default',
    colors: {
        light: {
            primary: '24 9.8% 10%',
            secondary: '24 5% 64.5%',
            accent: '24 5.4% 63.9%',
            background: '0 0% 100%',
            foreground: '24 9.8% 10%',
            muted: '24 5.9% 95%',
            mutedForeground: '24 5.4% 46.1%',
            card: '0 0% 100%',
            cardForeground: '24 9.8% 10%',
            border: '24 5.9% 90%',
            input: '24 5.9% 90%',
            ring: '24 9.8% 10%',
        },
        dark: {
            primary: '24 9.8% 90%',
            secondary: '24 3.7% 15.9%',
            accent: '24 3.7% 15.9%',
            background: '24 9.8% 10%',
            foreground: '24 9.8% 90%',
            muted: '24 3.7% 15.9%',
            mutedForeground: '24 5% 64.5%',
            card: '24 9.8% 10%',
            cardForeground: '24 9.8% 90%',
            border: '24 3.7% 15.9%',
            input: '24 3.7% 15.9%',
            ring: '24 5.7% 82.9%',
        },
    },
    fonts: {
        heading: 'Inter, sans-serif',
        body: 'Inter, sans-serif',
        mono: 'Fira Code, monospace',
    },
    radius: 'lg',
};
