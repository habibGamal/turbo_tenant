import { getTheme } from '@/lib/theme-registry';

interface PageModule {
    default: React.ComponentType<any>;
}

interface ResolveOptions {
    themeName?: string;
    fallbackTheme?: string;
}

/**
 * Resolve page component from theme or fallback to default Pages directory
 */
export async function resolveThemedPage(
    name: string,
    options: ResolveOptions = {}
): Promise<PageModule> {
    const { themeName = 'default', fallbackTheme = 'default' } = options;

    // Try to load from theme-specific pages first
    try {
        const themeComponent = await import(
            `../themes/${themeName}/pages/${name}.tsx`
        );
        return themeComponent;
    } catch (error) {
        // If theme-specific page doesn't exist, try fallback theme
        if (themeName !== fallbackTheme) {
            try {
                const fallbackComponent = await import(
                    `../themes/${fallbackTheme}/pages/${name}.tsx`
                );
                return fallbackComponent;
            } catch (fallbackError) {
                // Continue to default Pages directory
            }
        }

        // Fall back to default Pages directory
        try {
            const defaultComponent = await import(`../Pages/${name}.tsx`);
            return defaultComponent;
        } catch (defaultError) {
            throw new Error(
                `Page component "${name}" not found in theme "${themeName}", fallback theme "${fallbackTheme}", or default Pages directory`
            );
        }
    }
}

/**
 * Get the current theme name from page props or use default
 */
export function getThemeName(props: any): string {
    return props?.tenant?.theme || props?.theme || 'default';
}
