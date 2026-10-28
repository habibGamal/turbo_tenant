import { registerCmsPagesPlugin } from './cms-pages';
import { registerHeroBannersPlugin } from './hero-banners';
import { registerCustomerFavoritesPlugin } from './customer-favorites';
import { registerLocationsDeliveryPlugin } from './locations-delivery';
import { registerCatalogProductsPlugin } from './catalog-products';
import { registerOrderManagementPlugin } from './order-management';
import { registerCartEnginePlugin } from './cart-engine';
import { registerGuestCheckoutPlugin } from './guest-checkout';
import { registerPaymentProcessingPlugin } from './payment-processing';
import { registerProductReviewsPlugin } from './product-reviews';
import { registerCouponsPromotionsPlugin } from './coupons-promotions';
import { registerPushNotificationsPlugin } from './push-notifications';
import { registerComboPackagesPlugin } from './combo-packages';

let initialized = false;

/**
 * Initialize all registered frontend plugin defaults
 */
export function initializePlugins(): void {
  if (initialized) return;
  initialized = true;

  // Register plugin default slot implementations
  registerCmsPagesPlugin('cms.page');
  registerHeroBannersPlugin('home.hero');
  registerCustomerFavoritesPlugin();
  registerLocationsDeliveryPlugin('profile.addresses');
  registerCatalogProductsPlugin();
  registerOrderManagementPlugin('customer.orders');
  registerCartEnginePlugin();
  registerGuestCheckoutPlugin();
  registerPaymentProcessingPlugin();
  registerProductReviewsPlugin();
  registerCouponsPromotionsPlugin();
  registerPushNotificationsPlugin();
  registerComboPackagesPlugin();
}
