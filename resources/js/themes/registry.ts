import React from 'react';
import { PageProps, Address, Branch, Cart, Governorate, Order } from '@/types';

export interface MainLayoutProps {
  children: React.ReactNode;
  categories?: Array<{ id: number; name: string }>;
  cartItemsCount?: number;
  className?: string;
}

export interface ThemeLayouts {
  MainLayout: React.ComponentType<MainLayoutProps>;
}

export interface HomePageCategory {
  id: number;
  name: string;
  name_ar?: string;
  nameAr?: string;
  slug: string;
  description?: string;
  description_ar?: string;
  descriptionAr?: string;
  image?: string;
}

export interface HomePageSection {
  id: number;
  title: string;
  title_ar: string;
  products: any[];
}

export interface HomePageProps {
  categories?: HomePageCategory[];
  sections: HomePageSection[];
  cartItemsCount?: number;
}

export interface CheckoutPageProps extends Partial<PageProps> {
  cart?: Cart;
  addresses?: Address[];
  branches?: Branch[];
  governorates?: Governorate[];
  is_guest?: boolean;
}

export interface OrderShowPageProps extends Partial<PageProps> {
  order?: Order;
  orderId?: number | string;
}

export interface LoginPageProps {
  canResetPassword?: boolean;
  status?: string;
}

export interface ForgotPasswordPageProps {
  status?: string;
}

export interface ResetPasswordPageProps {
  token?: string;
  email?: string;
}

export interface ProfilePageProps {
  mustVerifyEmail?: boolean;
  status?: string;
}

export interface ThemePages {
  Home?: React.ComponentType<HomePageProps>;
  Checkout: React.ComponentType<CheckoutPageProps>;
  OrderShow: React.ComponentType<OrderShowPageProps>;
  Login: React.ComponentType<LoginPageProps>;
  Register: React.ComponentType<Record<string, never>>;
  ForgotPassword: React.ComponentType<ForgotPasswordPageProps>;
  ResetPassword: React.ComponentType<ResetPasswordPageProps>;
  Profile: React.ComponentType<ProfilePageProps>;
  NotFound?: React.ComponentType<Record<string, never>>;
}

export interface ThemedCmsPagesProps {
  slug?: string;
  className?: string;
}

export interface ThemedCustomerFavoritesProps {
  onExploreMenu?: () => void;
}

export interface ThemedGuestOrderTrackingProps {
  initialOrderNumber?: string;
  initialPhone?: string;
  className?: string;
}

export interface ThemedOrdersListProps {
  initialPage?: number;
}

export interface ThemedProductShowProps {
  productId?: string | number;
}

export interface ThemePlugins {
  ThemedCmsPages?: React.ComponentType<ThemedCmsPagesProps>;
  ThemedCustomerFavorites?: React.ComponentType<ThemedCustomerFavoritesProps>;
  ThemedCart?: React.ComponentType<Record<string, never>>;
  ThemedGuestOrderTracking?: React.ComponentType<ThemedGuestOrderTrackingProps>;
  ThemedOrdersList?: React.ComponentType<ThemedOrdersListProps>;
  ThemedPaymentCallback?: React.ComponentType<Record<string, never>>;
  ThemedKashierPayment?: React.ComponentType<Record<string, never>>;
  ThemedProductShow?: React.ComponentType<ThemedProductShowProps>;
  ThemedMenuPage?: React.ComponentType<Record<string, never>>;
}

export interface ThemeDefinition {
  name: string;
  layouts: ThemeLayouts;
  pages: ThemePages;
  plugins?: ThemePlugins;
  registerOverrides(): void;
}

const themeRegistry = new Map<string, ThemeDefinition>();

export function registerTheme(definition: ThemeDefinition): void {
  themeRegistry.set(definition.name, definition);
}

export function resolveTheme(name?: string | null): ThemeDefinition {
  if (name && themeRegistry.has(name)) {
    return themeRegistry.get(name)!;
  }
  const defaultTheme = themeRegistry.get('default');
  if (defaultTheme) {
    return defaultTheme;
  }
  throw new Error(`Theme '${name || 'default'}' is not registered and no fallback 'default' theme is available.`);
}

export function getRegisteredThemes(): string[] {
  return Array.from(themeRegistry.keys());
}
