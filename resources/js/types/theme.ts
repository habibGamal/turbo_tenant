export interface ThemeColors {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    foreground: string;
    muted: string;
    mutedForeground: string;
    card: string;
    cardForeground: string;
    border: string;
    input: string;
    ring: string;
}

export interface ThemeFonts {
    heading: string;
    body: string;
    mono: string;
}

export interface ThemeConfig {
    name: string;
    slug: string;
    colors: {
        light: ThemeColors;
        dark: ThemeColors;
    };
    fonts: ThemeFonts;
    logo?: string;
    favicon?: string;
    radius: 'sm' | 'md' | 'lg' | 'xl';
}

export interface ThemeContextType {
    theme: ThemeConfig;
    currentMode: 'light' | 'dark';
    setMode: (mode: 'light' | 'dark') => void;
}
