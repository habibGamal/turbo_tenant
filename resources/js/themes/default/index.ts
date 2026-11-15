import { lazy } from 'react';
import { ThemeDefinition } from '@/themes/registry';
import MainLayout from './layouts/MainLayout';
const Checkout = lazy(() => import('./pages/Checkout'));
const OrderShow = lazy(() => import('./pages/OrderShow'));
const Login = lazy(() => import('./pages/Login'));
const Register = lazy(() => import('./pages/Register'));
const ForgotPassword = lazy(() => import('./pages/ForgotPassword'));
const ResetPassword = lazy(() => import('./pages/ResetPassword'));
const Profile = lazy(() => import('./pages/Profile'));
const HomePage = lazy(() => import('./pages/HomePage'));
const NotFound = lazy(() => import('./pages/NotFound'));

import { ThemedCmsPages, registerCmsPagesThemeOverride } from './plugins/cms-pages';
import { registerHeroBannersThemeOverride } from './plugins/hero-banners';
import { ThemedCustomerFavorites, registerCustomerFavoritesThemeOverride } from './plugins/customer-favorites';
import { registerLocationsDeliveryThemeOverride } from './plugins/locations-delivery';
import {
  ThemedProductShow,
  ThemedMenuPage,
  registerCatalogProductsThemeOverride,
} from './plugins/catalog-products';
import { ThemedOrdersList, registerOrderManagementThemeOverride } from './plugins/order-management';
import { ThemedCart, registerCartEngineThemeOverride } from './plugins/cart-engine';
import { ThemedGuestOrderTracking, registerGuestCheckoutThemeOverride } from './plugins/guest-checkout';
import {
  ThemedPaymentCallback,
  ThemedKashierPayment,
  registerPaymentProcessingThemeOverride,
} from './plugins/payment-processing';
import { registerProductReviewsThemeOverride } from './plugins/product-reviews';
import { registerCouponsPromotionsThemeOverride } from './plugins/coupons-promotions';
import { registerPushNotificationsThemeOverride } from './plugins/push-notifications';
import { registerComboPackagesThemeOverride } from './plugins/combo-packages';

export const defaultTheme: ThemeDefinition = {
  name: 'default',
  layouts: {
    MainLayout,
  },
  pages: {
    Home: HomePage,
    Checkout,
    OrderShow,
    Login,
    Register,
    ForgotPassword,
    ResetPassword,
    Profile,
    NotFound,
  },
  plugins: {
    ThemedCmsPages,
    ThemedCustomerFavorites,
    ThemedCart,
    ThemedGuestOrderTracking,
    ThemedOrdersList,
    ThemedPaymentCallback,
    ThemedKashierPayment,
    ThemedProductShow,
    ThemedMenuPage,
  },
  registerOverrides(): void {
    registerCmsPagesThemeOverride('cms.page');
    registerHeroBannersThemeOverride('home.hero');
    registerCustomerFavoritesThemeOverride();
    registerLocationsDeliveryThemeOverride('profile.addresses');
    registerCatalogProductsThemeOverride();
    registerOrderManagementThemeOverride('customer.orders');
    registerCartEngineThemeOverride();
    registerGuestCheckoutThemeOverride();
    registerPaymentProcessingThemeOverride();
    registerProductReviewsThemeOverride();
    registerCouponsPromotionsThemeOverride();
    registerPushNotificationsThemeOverride();
    registerComboPackagesThemeOverride();
  },
};

export { default as HomePage } from './pages/HomePage';
export default defaultTheme;
