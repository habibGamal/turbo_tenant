import { useTenantSafe } from '@/core/context/TenantContext';
import { resolveTheme, ThemeDefinition } from '@/themes/registry';

export function useActiveTheme(): ThemeDefinition {
  const tenantCtx = useTenantSafe();
  const themeName = tenantCtx?.tenant?.theme;
  return resolveTheme(themeName);
}
