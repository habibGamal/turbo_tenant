import { Page } from '@playwright/test';
import { IThemeAdapter } from './theme.contract';
import { DefaultThemeAdapter } from './default';

export type ThemeName = 'default' | (string & {});

/**
 * Registry of available theme adapters.
 * Future themes (modern, minimal, luxury, etc.) register their adapter class here.
 */
const themeAdapters: Record<string, new (page: Page) => IThemeAdapter> = {
  default: DefaultThemeAdapter,
};

/**
 * Register a new theme adapter dynamically (used when adding new themes).
 */
export function registerThemeAdapter(name: string, adapterClass: new (page: Page) => IThemeAdapter): void {
  themeAdapters[name] = adapterClass;
}

/**
 * Theme Factory: Returns the appropriate theme adapter for the active test session.
 */
export function getThemeAdapter(page: Page, themeName: string = process.env.E2E_THEME || 'default'): IThemeAdapter {
  const AdapterClass = themeAdapters[themeName] || DefaultThemeAdapter;
  return new AdapterClass(page);
}
