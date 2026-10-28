import { registerCmsPagesPlugin } from './cms-pages';
import { registerCmsPagesThemeOverride } from '@/themes/default/plugins/cms-pages';
import { registerHeroBannersPlugin } from './hero-banners';
import { registerHeroBannersThemeOverride } from '@/themes/default/plugins/hero-banners';
import { registerCustomerFavoritesPlugin } from './customer-favorites';
import { registerCustomerFavoritesThemeOverride } from '@/themes/default/plugins/customer-favorites';
import { registerLocationsDeliveryPlugin } from './locations-delivery';
import { registerLocationsDeliveryThemeOverride } from '@/themes/default/plugins/locations-delivery';
import { registerCatalogProductsPlugin } from './catalog-products';
import { registerCatalogProductsThemeOverride } from '@/themes/default/plugins/catalog-products';
import { registerOrderManagementPlugin } from './order-management';
import { registerOrderManagementThemeOverride } from '@/themes/default/plugins/order-management';
import { registerCartEnginePlugin } from './cart-engine';
import { registerCartEngineThemeOverride } from '@/themes/default/plugins/cart-engine';
import { registerGuestCheckoutPlugin } from './guest-checkout';
import { registerGuestCheckoutThemeOverride } from '@/themes/default/plugins/guest-checkout';
import { registerPaymentProcessingPlugin } from './payment-processing';
import { registerPaymentProcessingThemeOverride } from '@/themes/default/plugins/payment-processing';
import { registerProductReviewsPlugin } from './product-reviews';
import { registerProductReviewsThemeOverride } from '@/themes/default/plugins/product-reviews';
import { registerCouponsPromotionsPlugin } from './coupons-promotions';
import { registerCouponsPromotionsThemeOverride } from '@/themes/default/plugins/coupons-promotions';
import { registerPushNotificationsPlugin } from './push-notifications';
import { registerPushNotificationsThemeOverride } from '@/themes/default/plugins/push-notifications';
import { registerComboPackagesPlugin } from './combo-packages';
import { registerComboPackagesThemeOverride } from '@/themes/default/plugins/combo-packages';

let initialized = false;

/**
 * Initialize all registered frontend plugin slots & theme overrides
 */
export function initializePlugins(): void {
  if (initialized) return;
  initialized = true;

  // Register CmsPages plugin default and theme override
  registerCmsPagesPlugin('cms.page');
  registerCmsPagesThemeOverride('cms.page');

  // Register HeroBanners plugin default and theme override
  registerHeroBannersPlugin('home.hero');
  registerHeroBannersThemeOverride('home.hero');

  // Register CustomerFavorites plugin default and theme override
  registerCustomerFavoritesPlugin();
  registerCustomerFavoritesThemeOverride();

  // Register LocationsDelivery plugin default and theme override
  registerLocationsDeliveryPlugin('profile.addresses');
  registerLocationsDeliveryThemeOverride('profile.addresses');

  // Register CatalogProducts plugin default and theme override
  registerCatalogProductsPlugin();
  registerCatalogProductsThemeOverride();

  // Register OrderManagement plugin default and theme override
  registerOrderManagementPlugin('customer.orders');
  registerOrderManagementThemeOverride('customer.orders');

  // Register CartEngine plugin default and theme override
  registerCartEnginePlugin();
  registerCartEngineThemeOverride();

  // Register GuestCheckout plugin default and theme override
  registerGuestCheckoutPlugin();
  registerGuestCheckoutThemeOverride();

  // Register PaymentProcessing plugin default and theme override
  registerPaymentProcessingPlugin();
  registerPaymentProcessingThemeOverride();

  // Register ProductReviews plugin default and theme override
  registerProductReviewsPlugin();
  registerProductReviewsThemeOverride();

  // Register CouponsPromotions plugin default and theme override
  registerCouponsPromotionsPlugin();
  registerCouponsPromotionsThemeOverride();

  // Register PushNotifications plugin default and theme override
  registerPushNotificationsPlugin();
  registerPushNotificationsThemeOverride();

  // Register ComboPackages plugin default and theme override
  registerComboPackagesPlugin();
  registerComboPackagesThemeOverride();
}
