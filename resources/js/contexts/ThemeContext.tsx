import React, { createContext, useContext, useEffect, useState } from 'react';
import { ThemeConfig, ThemeContextType } from '@/types/theme';

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

interface ThemeProviderProps {
    theme: ThemeConfig;
    children: React.ReactNode;
    defaultMode?: 'light' | 'dark';
}

export function ThemeProvider({ theme, children, defaultMode = 'light' }: ThemeProviderProps) {
    const [currentMode, setCurrentMode] = useState<'light' | 'dark'>('light');

    useEffect(() => {
        const root = document.documentElement;
        const colors = theme.colors[currentMode];

        // Apply theme colors as CSS variables
        Object.entries(colors).forEach(([key, value]) => {
            const cssVarName = `--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
            root.style.setProperty(cssVarName, value);
        });

        // Apply fonts
        root.style.setProperty('--font-heading', theme.fonts.heading);
        root.style.setProperty('--font-body', theme.fonts.body);
        root.style.setProperty('--font-mono', theme.fonts.mono);

        // Apply radius
        const radiusMap = {
            sm: '0.25rem',
            md: '0.5rem',
            lg: '0.75rem',
            xl: '1rem',
        };
        root.style.setProperty('--radius', radiusMap[theme.radius]);

        // Update dark mode class
        if (currentMode === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }, [theme, currentMode]);

    const value: ThemeContextType = {
        theme,
        currentMode,
        setMode: setCurrentMode,
    };

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
