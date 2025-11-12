import { ThemeConfig } from '@/types/theme';
import { defaultTheme } from '@/themes/default/config';

// Map of available themes
const themes: Record<string, ThemeConfig> = {
    default: defaultTheme,
    // Add more themes here as they are created
    // modern: modernTheme,
    // classic: classicTheme,
};

/**
 * Get theme configuration by slug
 */
export function getTheme(slug: string): ThemeConfig {
    return themes[slug] || themes.default;
}

/**
 * Get all available themes
 */
export function getAllThemes(): ThemeConfig[] {
    return Object.values(themes);
}

/**
 * Check if theme exists
 */
export function themeExists(slug: string): boolean {
    return slug in themes;
}
