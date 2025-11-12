# Multi-Theme Frontend Structure

This project supports multiple themes for different restaurant tenants. Each theme can have its own layouts, components, and pages while sharing common UI components from shadcn.

## Directory Structure

```
resources/js/
├── themes/
│   ├── default/           # Default restaurant theme
│   │   ├── components/    # Theme-specific components
│   │   ├── layouts/       # Theme layouts (header, footer, etc.)
│   │   ├── pages/         # Theme-specific pages
│   │   ├── config.ts      # Theme configuration (colors, fonts, etc.)
│   │   └── index.ts       # Theme exports
│   └── [theme-name]/      # Additional themes follow same structure
├── components/ui/         # Shared shadcn UI components
├── contexts/
│   └── ThemeContext.tsx   # Theme provider and context
├── lib/
│   ├── theme-registry.ts  # Theme registration and retrieval
│   └── theme-resolver.ts  # Dynamic page resolution
└── types/
    └── theme.ts           # TypeScript theme types
```

## How It Works

1. **Theme Configuration**: Each theme has a `config.ts` file defining colors, fonts, and other visual properties
2. **Theme Provider**: Wraps the entire app and applies theme CSS variables dynamically
3. **Theme Resolver**: Automatically resolves pages from theme directories or falls back to default Pages
4. **Tenant-Specific Themes**: Each tenant can specify their theme in the database

## Creating a New Theme

### 1. Create Theme Directory Structure

```bash
mkdir -p resources/js/themes/[theme-name]/{components,layouts,pages}
```

### 2. Create Theme Configuration

Create `resources/js/themes/[theme-name]/config.ts`:

```typescript
import { ThemeConfig } from '@/types/theme';

export const modernTheme: ThemeConfig = {
    name: 'Modern Restaurant Theme',
    slug: 'modern',
    colors: {
        light: {
            primary: '220 90% 56%',    // Blue
            secondary: '280 80% 60%',  // Purple
            // ... other colors
        },
        dark: {
            primary: '220 90% 66%',
            secondary: '280 80% 70%',
            // ... other colors
        },
    },
    fonts: {
        heading: 'Playfair Display, serif',
        body: 'Open Sans, sans-serif',
        mono: 'Fira Code, monospace',
    },
    radius: 'md',
};
```

### 3. Create Theme Layout

Create `resources/js/themes/[theme-name]/layouts/ModernLayout.tsx`:

```tsx
import React from 'react';
import { Link } from '@inertiajs/react';
// ... import components

export default function ModernLayout({ children }) {
    return (
        <div className="min-h-screen">
            {/* Your custom header */}
            <main>{children}</main>
            {/* Your custom footer */}
        </div>
    );
}
```

### 4. Create Theme Pages

Create theme-specific pages in `resources/js/themes/[theme-name]/pages/`:

```tsx
import React from 'react';
import ModernLayout from '../layouts/ModernLayout';

export default function HomePage({ categories, featuredProducts }) {
    return (
        <ModernLayout>
            {/* Your theme-specific homepage design */}
        </ModernLayout>
    );
}
```

### 5. Register Theme

Add your theme to `resources/js/lib/theme-registry.ts`:

```typescript
import { modernTheme } from '@/themes/modern/config';

const themes: Record<string, ThemeConfig> = {
    default: defaultTheme,
    modern: modernTheme,  // Add your theme here
};
```

### 6. Export Theme Components

Create `resources/js/themes/[theme-name]/index.ts`:

```typescript
export { default as ModernLayout } from './layouts/ModernLayout';
export { default as HomePage } from './pages/HomePage';
export { modernTheme } from './config';
```

## Setting Theme for Tenant

You can store the theme preference in the tenant's data:

```php
// In your tenant setup or settings controller
$tenant->update([
    'data' => [
        'theme' => 'modern',
        // ... other tenant data
    ],
]);
```

The app will automatically use the tenant's theme when rendering pages.

## Theme Customization

### Colors

Colors use Tailwind's color system in HSL format:
- Format: `hue saturation lightness` (e.g., `220 90% 56%`)
- Applied as CSS variables: `--primary`, `--secondary`, etc.
- Use in components: `bg-primary`, `text-primary`, etc.

### Fonts

Define fonts in the theme config:
- `heading`: For titles and headings
- `body`: For body text
- `mono`: For code blocks

Apply with CSS classes or variables:
- `font-heading`, `font-body`, `font-mono`

### Border Radius

Choose from: `sm`, `md`, `lg`, `xl`
- Applied globally via `--radius` CSS variable

## Using shadcn Components

All themes share the same shadcn UI components from `resources/js/components/ui/`.

### Adding New Components

```bash
npx shadcn@latest add [component-name]
```

### Available Components

Currently installed:
- button
- card
- input
- badge
- separator
- navigation-menu
- sheet
- dialog
- avatar
- dropdown-menu

See full list: `npx shadcn@latest view @shadcn`

## Theme Context & Hooks

### useTheme Hook

Access theme information and controls in any component:

```tsx
import { useTheme } from '@/contexts/ThemeContext';

function MyComponent() {
    const { theme, currentMode, setMode } = useTheme();

    return (
        <button onClick={() => setMode(currentMode === 'light' ? 'dark' : 'light')}>
            Toggle Theme
        </button>
    );
}
```

## Best Practices

1. **Reuse shadcn components** across all themes for consistency
2. **Keep theme-specific logic** in theme directories
3. **Use theme colors** via Tailwind classes (e.g., `bg-primary`)
4. **Test both light and dark modes** for each theme
5. **Follow naming conventions** for theme slugs (lowercase, hyphenated)
6. **Document theme features** specific to each restaurant style

## Example Themes Ideas

- **Classic**: Traditional restaurant with serif fonts
- **Modern**: Contemporary design with bold colors
- **Minimalist**: Clean, simple design with lots of whitespace
- **Luxury**: Elegant design with gold accents
- **Fast Food**: Bright, energetic design
- **Cafe**: Warm, cozy aesthetic

## Troubleshooting

### Theme not loading
- Check theme is registered in `theme-registry.ts`
- Verify tenant data has correct theme slug
- Ensure theme config exports are correct

### Components not styled
- Verify tailwind is processing theme CSS variables
- Check component imports are correct
- Run `npm run build` or `npm run dev`

### TypeScript errors
- Ensure theme config matches `ThemeConfig` interface
- Check all required properties are defined
- Run `npm run build` to check for type errors
