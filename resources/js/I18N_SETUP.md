# i18next Setup - Arabic/English Support

## Overview
This project now has full internationalization support with Arabic as the default language.

## Features
- ✅ Arabic (ar) as default language
- ✅ English (en) as secondary language
- ✅ Automatic RTL/LTR direction switching
- ✅ Language detection (localStorage, cookie, browser)
- ✅ Ready-to-use LanguageSwitcher component

## Usage

### 1. Using translations in components

```tsx
import { useTranslation } from 'react-i18next';

export function MyComponent() {
    const { t } = useTranslation();
    
    return (
        <div>
            <h1>{t('welcome')}</h1>
            <button>{t('submit')}</button>
        </div>
    );
}
```

### 2. Using the language hook

```tsx
import { useLanguage } from '@/hooks/useLanguage';

export function MyComponent() {
    const { currentLanguage, changeLanguage, isRTL } = useLanguage();
    
    return (
        <div>
            <p>Current language: {currentLanguage}</p>
            <p>Is RTL: {isRTL ? 'Yes' : 'No'}</p>
            <button onClick={() => changeLanguage('en')}>Switch to English</button>
            <button onClick={() => changeLanguage('ar')}>Switch to Arabic</button>
        </div>
    );
}
```

### 3. Using the LanguageSwitcher component

```tsx
import { LanguageSwitcher } from '@/components/LanguageSwitcher';

export function Header() {
    return (
        <header>
            <nav>
                {/* Your navigation items */}
                <LanguageSwitcher />
            </nav>
        </header>
    );
}
```

## File Structure

```
resources/js/
├── i18n.ts                           # i18next configuration
├── hooks/
│   └── useLanguage.ts                # Custom hook for language management
├── components/
│   └── LanguageSwitcher.tsx          # Language switcher dropdown component
└── locales/
    ├── ar/
    │   └── translation.json          # Arabic translations
    └── en/
        └── translation.json          # English translations
```

## Adding New Translations

1. Add the key-value pair to both `ar/translation.json` and `en/translation.json`:

**ar/translation.json**
```json
{
  "myNewKey": "الترجمة العربية"
}
```

**en/translation.json**
```json
{
  "myNewKey": "English Translation"
}
```

2. Use it in your component:
```tsx
const { t } = useTranslation();
<p>{t('myNewKey')}</p>
```

## Translation Namespaces (Optional)

For larger projects, you can organize translations into namespaces:

```
locales/
├── ar/
│   ├── common.json
│   ├── dashboard.json
│   └── auth.json
└── en/
    ├── common.json
    ├── dashboard.json
    └── auth.json
```

Then use them like:
```tsx
const { t } = useTranslation(['dashboard', 'common']);
t('dashboard:title');
t('common:save');
```

## RTL Support

The project automatically:
- Sets `dir="rtl"` for Arabic
- Sets `dir="ltr"` for English
- Updates the `lang` attribute on the HTML element

Your Tailwind CSS classes with `rtl:` and `ltr:` prefixes will work automatically.

## Configuration

Default settings are in `resources/js/i18n.ts`:
- Default language: `ar` (Arabic)
- Fallback language: `ar` (Arabic)
- Detection order: localStorage → cookie → browser navigator

To change the default language, modify the `lng` value in `i18n.ts`.
