import React from 'react';
import { useActiveTheme } from '@/core/hooks/useActiveTheme';
import { ThemePlugins } from '@/themes/registry';

export type ThemedPluginKey = keyof ThemePlugins;

type ComponentProps<T> = T extends React.ComponentType<infer P> ? P : Record<string, never>;

export interface ThemedComponentProps<K extends ThemedPluginKey> {
  name: K;
  props?: ComponentProps<NonNullable<ThemePlugins[K]>>;
}

export function ThemedComponent<K extends ThemedPluginKey>({
  name,
  props,
}: ThemedComponentProps<K>): React.ReactElement | null {
  const { plugins } = useActiveTheme();
  const Component = plugins?.[name] as React.ComponentType<any> | undefined;

  if (!Component) {
    return null;
  }

  return <Component {...(props as any)} />;
}

export default ThemedComponent;
