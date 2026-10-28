import { Locator, Page } from '@playwright/test';
import {
  IThemeAdapter,
  ICatalogTheme,
  ICartTheme,
  ICouponTheme,
  IFavoritesTheme,
  IGuestCheckoutTheme,
  IHeroTheme,
  IComboTheme,
  IReviewTheme,
  INotificationTheme,
  ICmsTheme,
  IOrderTheme,
} from '../theme.contract';

export class BaseCatalogTheme implements ICatalogTheme {
  constructor(protected page: Page) {}

  getCategoryTabs(): Locator {
    return this.page.locator('[data-testid="category-tab"]');
  }

  getCategoryCards(): Locator {
    return this.page.locator('[data-testid="category-card"]');
  }

  getSections(): Locator {
    return this.page.locator('[data-testid="catalog-section"]');
  }

  getSection(index: number): Locator {
    return this.getSections().nth(index);
  }

  getSectionTitle(section: Locator): Locator {
    return section.locator('[data-testid="section-title"]').first();
  }

  getSectionViewAll(section: Locator): Locator {
    return section.locator('[data-testid="section-view-all"], a, button');
  }

  getProductCards(): Locator {
    return this.page.locator('[data-testid="product-card"]');
  }

  getProductCard(index: number): Locator {
    return this.getProductCards().nth(index);
  }

  getProductTitle(card: Locator): Locator {
    return card.locator('[data-testid="product-card-title"]').first();
  }

  getProductPrice(card: Locator): Locator {
    return card.locator('[data-testid="product-card-price"]').first();
  }

  getTotalPrice(): Locator {
    return this.page.locator('[data-testid="product-total-price"]').first();
  }

  getAddToCartButton(card: Locator): Locator {
    return card.locator('[data-testid="product-add-to-cart"]').first();
  }

  getFavoriteToggleButton(card: Locator): Locator {
    return card.locator('[data-testid="product-favorite-toggle"]').first();
  }

  getProductDetailsContainer(): Locator {
    return this.page.locator('[data-testid="product-details-container"]').first();
  }

  getProductOptionSelectors(): Locator {
    return this.page.locator('[data-testid="product-option-selector"], input[type="radio"], select');
  }

  getVariantOptions(): Locator {
    return this.page.locator('[data-testid="product-variant-option"]');
  }

  getWeightOptions(): Locator {
    return this.page.locator('[data-testid="product-weight-option"]');
  }

  getExtraCheckboxes(): Locator {
    return this.page.locator('[data-testid="extra-option-checkbox"]');
  }

  getExtraIncrementButtons(): Locator {
    return this.page.locator('[data-testid="extra-qty-increment"]');
  }

  getExtraDecrementButtons(): Locator {
    return this.page.locator('[data-testid="extra-qty-decrement"]');
  }

  getDetailAddToCartButton(): Locator {
    return this.page.locator('[data-testid="product-detail-add-to-cart"]');
  }

  getQuantityIncrementButton(): Locator {
    return this.page.locator('[data-testid="product-quantity-increment"]');
  }

  getQuantityDecrementButton(): Locator {
    return this.page.locator('[data-testid="product-quantity-decrement"]');
  }

  getQuantityValue(): Locator {
    return this.page.locator('[data-testid="product-quantity-value"]');
  }

  getSearchInput(): Locator {
    return this.page.locator('[data-testid="search-input"]:visible, [data-testid="menu-search-input"]:visible').first();
  }

  getSearchSuggestions(): Locator {
    return this.page.locator('[data-testid="search-suggestions-container"]');
  }

  getSearchSuggestionItem(index: number): Locator {
    return this.page.locator('[data-testid="search-suggestion-item"]').nth(index);
  }

  getNotFoundMessage(): Locator {
    return this.page.locator('[data-testid="product-not-found"]');
  }

  getBrowseMenuButton(): Locator {
    return this.page.locator('[data-testid="browse-menu-button"]');
  }

  getOptionsModal(): Locator {
    return this.page.locator('[data-testid="product-options-modal"]');
  }
}

export class BaseCartTheme implements ICartTheme {
  constructor(protected page: Page) {}

  async open(): Promise<void> {
    const trigger = this.getTrigger();
    if (await trigger.isVisible()) {
      await trigger.click();
    } else {
      await this.page.goto('/cart');
    }
  }

  getTrigger(): Locator {
    return this.page.locator('[data-testid="cart-trigger"]').first();
  }

  getBadgeCount(): Locator {
    return this.page.locator('[data-testid="cart-badge-count"]');
  }

  getDrawerOrPage(): Locator {
    return this.page.locator('[data-testid="cart-drawer"], [data-testid="cart-page"], div[role="dialog"]');
  }

  getItems(): Locator {
    return this.page.locator('[data-testid="cart-item"]');
  }

  getItem(index: number): Locator {
    return this.getItems().nth(index);
  }

  getItemTitle(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-title"], h3, h4, span.font-medium');
  }

  getItemPrice(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-price"], span:has-text("EGP"), span:has-text("ج.م")');
  }

  getItemVariant(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-variant"]');
  }

  getItemWeightSelect(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-weight-select"]');
  }

  getItemExtras(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-extras"]');
  }

  getQuantityIncrementButton(item: Locator): Locator {
    return item.locator('[data-testid="cart-qty-increment"], button:has-text("+"), button[aria-label*="increment"]');
  }

  getQuantityDecrementButton(item: Locator): Locator {
    return item.locator('[data-testid="cart-qty-decrement"], button:has-text("-"), button[aria-label*="decrement"]');
  }

  getItemRemoveButton(item: Locator): Locator {
    return item.locator('[data-testid="cart-item-remove"], button[aria-label*="remove"], button:has-text("Remove")');
  }

  getClearCartButton(): Locator {
    return this.page.locator('[data-testid="clear-cart-button"]');
  }

  getClearCartConfirmButton(): Locator {
    return this.page.locator('[data-testid="confirm-clear-cart-action"]');
  }

  getSubtotal(): Locator {
    return this.page.locator('[data-testid="cart-subtotal"]');
  }

  getCheckoutButton(): Locator {
    return this.page.locator('[data-testid="cart-checkout-button"]');
  }

  getEmptyCartMessage(): Locator {
    return this.page.locator('[data-testid="empty-cart-message"]');
  }

  getStartShoppingButton(): Locator {
    return this.page.locator('[data-testid="start-shopping-button"]');
  }
}

export class BaseCouponTheme implements ICouponTheme {
  constructor(protected page: Page) {}

  getCouponForm(): Locator {
    return this.page.locator('[data-testid="coupon-form"], form:has([data-testid="coupon-input"])');
  }

  getCouponInput(): Locator {
    return this.page.locator('[data-testid="coupon-input"], input[placeholder*="Coupon"], input[placeholder*="كوبون"]');
  }

  getApplyButton(): Locator {
    return this.page.locator('[data-testid="coupon-apply-button"], button:has-text("Apply"), button:has-text("تطبيق")');
  }

  getDiscountValue(): Locator {
    return this.page.locator('[data-testid="coupon-discount-value"]');
  }

  getErrorMessage(): Locator {
    return this.page.locator('[data-testid="coupon-error-message"], div[role="alert"]');
  }

  async applyCoupon(code: string): Promise<void> {
    await this.getCouponInput().fill(code);
    await this.getApplyButton().click();
  }
}

export class BaseFavoritesTheme implements IFavoritesTheme {
  constructor(protected page: Page) {}

  getTrigger(): Locator {
    return this.page.locator('[data-testid="favorites-trigger"], a[href="/favorites"]');
  }

  getBadgeCount(): Locator {
    return this.page.locator('[data-testid="favorites-badge-count"]');
  }

  getFavoritesPage(): Locator {
    return this.page.locator('[data-testid="favorites-page"]');
  }

  getFavoriteCards(): Locator {
    return this.page.locator('[data-testid="favorite-item-card"], [data-testid="product-card"]');
  }

  getRemoveButton(card: Locator): Locator {
    return card.locator('[data-testid="remove-favorite-button"], [data-testid="product-favorite-toggle"]');
  }
}

export class BaseGuestCheckoutTheme implements IGuestCheckoutTheme {
  constructor(protected page: Page) {}

  getCheckoutForm(): Locator {
    return this.page.locator('[data-testid="checkout-form"]').first();
  }

  getOrderSummary(): Locator {
    return this.page.locator('[data-testid="order-summary"]');
  }

  getNameInput(): Locator {
    return this.page.locator('[data-testid="guest-name-input"], input[name="name"], input[placeholder*="Name"], input[placeholder*="الاسم"]');
  }

  getPhoneInput(): Locator {
    return this.page.locator('[data-testid="guest-phone-input"], input[name="phone"], input[type="tel"]');
  }

  getEmailInput(): Locator {
    return this.page.locator('[data-testid="guest-email-input"], input[name="email"], input[type="email"]');
  }

  getDeliveryZoneSelect(): Locator {
    return this.page.locator('[data-testid="delivery-zone-select"], select[name="zone_id"], button[role="combobox"]');
  }

  getAddressInput(): Locator {
    return this.page.locator('[data-testid="delivery-address-input"], textarea[name="address"], input[name="address"]');
  }

  getPaymentMethodSelector(): Locator {
    return this.page.locator('[data-testid="payment-method-selector"]');
  }

  getPaymentCodOption(): Locator {
    return this.page.locator('[data-testid="payment-cod-radio"], input[value="cod"], label:has-text("Cash on Delivery"), label:has-text("الدفع عند الاستلام")');
  }

  getPaymentOnlineOption(): Locator {
    return this.page.locator('[data-testid="payment-online-radio"], input[value="kashier"], label:has-text("Kashier"), label:has-text("بطاقة")');
  }

  getPlaceOrderButton(): Locator {
    return this.page.locator('[data-testid="place-order-button"], button:has-text("Place Order"), button:has-text("تأكيد الطلب")');
  }

  getOrderConfirmation(): Locator {
    return this.page.locator('[data-testid="order-confirmation-container"]');
  }

  getTrackOrderForm(): Locator {
    return this.page.locator('[data-testid="track-order-form"]');
  }

  getTrackOrderInput(): Locator {
    return this.page.locator('[data-testid="track-order-input"], input[name="order_number"], input[name="id"]');
  }

  getTrackPhoneInput(): Locator {
    return this.page.locator('[data-testid="track-phone-input"], input[name="phone"]');
  }

  getTrackSubmitButton(): Locator {
    return this.page.locator('[data-testid="track-submit-button"], button[type="submit"]');
  }

  getOrderStatusTimeline(): Locator {
    return this.page.locator('[data-testid="order-status-timeline"]');
  }
}

export class BaseHeroTheme implements IHeroTheme {
  constructor(protected page: Page) {}

  getHeroSlider(): Locator {
    return this.page.locator('[data-testid="hero-banners-slider"], section[aria-label*="hero"]');
  }

  getSlides(): Locator {
    return this.page.locator('[data-testid="hero-slide"]');
  }

  getNextButton(): Locator {
    return this.page.locator('[data-testid="hero-next-button"], button[aria-label*="next"]');
  }

  getPrevButton(): Locator {
    return this.page.locator('[data-testid="hero-prev-button"], button[aria-label*="prev"]');
  }

  getSlideCta(slide: Locator): Locator {
    return slide.locator('[data-testid="hero-slide-cta"], a, button');
  }
}

export class BaseComboTheme implements IComboTheme {
  constructor(protected page: Page) {}

  getCombosSection(): Locator {
    return this.page.locator('[data-testid="combos-section"]');
  }

  getComboCards(): Locator {
    return this.page.locator('[data-testid="combo-package-card"]');
  }

  getComboTitle(card: Locator): Locator {
    return card.locator('[data-testid="combo-package-title"], h3, h4');
  }

  getComboAddToCartButton(card: Locator): Locator {
    return card.locator('[data-testid="combo-add-to-cart"], button');
  }
}

export class BaseReviewTheme implements IReviewTheme {
  constructor(protected page: Page) {}

  getReviewsSection(): Locator {
    return this.page.locator('[data-testid="reviews-section"]');
  }

  getRatingStarsInput(): Locator {
    return this.page.locator('[data-testid="rating-stars-input"]');
  }

  getCommentInput(): Locator {
    return this.page.locator('[data-testid="review-comment-input"], textarea');
  }

  getSubmitButton(): Locator {
    return this.page.locator('[data-testid="review-submit-button"], button:has-text("Submit"), button:has-text("إرسال")');
  }

  getReviewCards(): Locator {
    return this.page.locator('[data-testid="review-card"]');
  }
}

export class BaseNotificationTheme implements INotificationTheme {
  constructor(protected page: Page) {}

  getTrigger(): Locator {
    return this.page.locator('[data-testid="notifications-trigger"], button[aria-label*="notification"]');
  }

  getBadgeCount(): Locator {
    return this.page.locator('[data-testid="notifications-badge-count"]');
  }

  getDropdown(): Locator {
    return this.page.locator('[data-testid="notifications-dropdown"], div[role="dialog"]');
  }

  getMarkAllReadButton(): Locator {
    return this.page.locator('[data-testid="mark-all-read-button"], button:has-text("Mark all"), button:has-text("تحديد الكل")');
  }
}

export class BaseCmsTheme implements ICmsTheme {
  constructor(protected page: Page) {}

  getPageContainer(): Locator {
    return this.page.locator('[data-testid="cms-page-container"]').or(this.page.locator('main').first());
  }

  getTitle(): Locator {
    return this.page.locator('[data-testid="cms-page-title"], h1');
  }

  getContent(): Locator {
    return this.page.locator('[data-testid="cms-page-content"]');
  }
}

export class BaseOrderTheme implements IOrderTheme {
  constructor(protected page: Page) {}

  getCheckoutForm(): Locator {
    return this.page.locator('[data-testid="checkout-form"]');
  }

  getOrderSummary(): Locator {
    return this.page.locator('[data-testid="order-summary"]');
  }

  getPlaceOrderButton(): Locator {
    return this.page.locator('[data-testid="place-order-button"]');
  }

  getOrderTypeDelivery(): Locator {
    return this.page.locator('[data-testid="order-type-delivery"]');
  }

  getOrderTypeTakeaway(): Locator {
    return this.page.locator('[data-testid="order-type-takeaway"]');
  }

  getBranchSelectTrigger(): Locator {
    return this.page.locator('[data-testid="branch-select-trigger"]');
  }

  getOrdersList(): Locator {
    return this.page.locator('[data-testid="orders-history-list"]');
  }

  getOrderCards(): Locator {
    return this.page.locator('[data-testid="order-history-item"]');
  }

  getOrderCard(index: number): Locator {
    return this.getOrderCards().nth(index);
  }

  getOrderNumber(card: Locator): Locator {
    return card.locator('[data-testid="order-number-display"]');
  }

  getOrderStatusBadge(card: Locator): Locator {
    return card.locator('[data-testid="order-status-badge"]');
  }

  getOrderTotal(card: Locator): Locator {
    return card.locator('[data-testid="order-total-display"]');
  }

  getViewOrderDetailsButton(card: Locator): Locator {
    return card.locator('[data-testid="view-order-details-button"]');
  }

  getEmptyOrdersMessage(): Locator {
    return this.page.locator('[data-testid="empty-orders-message"]');
  }

  getOrderDetailsContainer(): Locator {
    return this.page.locator('[data-testid="order-details-container"]');
  }

  getOrderStatusTimeline(): Locator {
    return this.page.locator('[data-testid="order-status-timeline"]');
  }

  getOrderItemsList(): Locator {
    return this.page.locator('[data-testid="order-items-list"]');
  }

  getDetailOrderTotal(): Locator {
    return this.page.locator('[data-testid="order-total-display"]');
  }
}

export class BaseThemeAdapter implements IThemeAdapter {
  readonly name: string = 'base';
  readonly catalog: ICatalogTheme;
  readonly cart: ICartTheme;
  readonly coupons: ICouponTheme;
  readonly favorites: IFavoritesTheme;
  readonly checkout: IGuestCheckoutTheme;
  readonly hero: IHeroTheme;
  readonly combos: IComboTheme;
  readonly reviews: IReviewTheme;
  readonly notifications: INotificationTheme;
  readonly cms: ICmsTheme;
  readonly orders: IOrderTheme;

  constructor(public readonly page: Page) {
    this.catalog = new BaseCatalogTheme(page);
    this.cart = new BaseCartTheme(page);
    this.coupons = new BaseCouponTheme(page);
    this.favorites = new BaseFavoritesTheme(page);
    this.checkout = new BaseGuestCheckoutTheme(page);
    this.hero = new BaseHeroTheme(page);
    this.combos = new BaseComboTheme(page);
    this.reviews = new BaseReviewTheme(page);
    this.notifications = new BaseNotificationTheme(page);
    this.cms = new BaseCmsTheme(page);
    this.orders = new BaseOrderTheme(page);
  }
}
