import { ThemeDefinition } from '@/themes/registry';
import MainLayout from './layouts/MainLayout';
import Checkout from './pages/Checkout';
import OrderShow from './pages/OrderShow';
import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Profile from './pages/Profile';
import HomePage from './pages/HomePage';

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
