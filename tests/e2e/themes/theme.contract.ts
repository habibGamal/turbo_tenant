import { Locator, Page } from '@playwright/test';

/**
 * Standard Theme Contract Interfaces for Turbo Tenant.
 * Guarantees decoupling between test specs and theme layout implementations.
 */

export interface ICatalogTheme {
  getCategoryTabs(): Locator;
  getCategoryCards(): Locator;
  getSections(): Locator;
  getSection(index: number): Locator;
  getSectionTitle(section: Locator): Locator;
  getSectionViewAll(section: Locator): Locator;
  getProductCards(): Locator;
  getProductCard(index: number): Locator;
  getProductTitle(card: Locator): Locator;
  getProductPrice(card: Locator): Locator;
  getTotalPrice(): Locator;
  getAddToCartButton(card: Locator): Locator;
  getFavoriteToggleButton(card: Locator): Locator;
  getProductDetailsContainer(): Locator;
  getProductOptionSelectors(): Locator;
  getVariantOptions(): Locator;
  getWeightOptions(): Locator;
  getExtraCheckboxes(): Locator;
  getExtraIncrementButtons(): Locator;
  getExtraDecrementButtons(): Locator;
  getDetailAddToCartButton(): Locator;
  getQuantityIncrementButton(): Locator;
  getQuantityDecrementButton(): Locator;
  getQuantityValue(): Locator;
  getSearchInput(): Locator;
  getSearchSuggestions(): Locator;
  getSearchSuggestionItem(index: number): Locator;
  getNotFoundMessage(): Locator;
  getBrowseMenuButton(): Locator;
  getOptionsModal(): Locator;
}

export interface ICartTheme {
  open(): Promise<void>;
  getTrigger(): Locator;
  getBadgeCount(): Locator;
  getDrawerOrPage(): Locator;
  getItems(): Locator;
  getItem(index: number): Locator;
  getItemTitle(item: Locator): Locator;
  getItemPrice(item: Locator): Locator;
  getItemVariant(item: Locator): Locator;
  getItemWeightSelect(item: Locator): Locator;
  getItemExtras(item: Locator): Locator;
  getQuantityIncrementButton(item: Locator): Locator;
  getQuantityDecrementButton(item: Locator): Locator;
  getItemRemoveButton(item: Locator): Locator;
  getClearCartButton(): Locator;
  getSubtotal(): Locator;
  getCheckoutButton(): Locator;
  getEmptyCartMessage(): Locator;
  getStartShoppingButton(): Locator;
}

export interface ICouponTheme {
  getCouponForm(): Locator;
  getCouponInput(): Locator;
  getApplyButton(): Locator;
  getDiscountValue(): Locator;
  getErrorMessage(): Locator;
  applyCoupon(code: string): Promise<void>;
}

export interface IFavoritesTheme {
  getTrigger(): Locator;
  getBadgeCount(): Locator;
  getFavoritesPage(): Locator;
  getFavoriteCards(): Locator;
  getRemoveButton(card: Locator): Locator;
}

export interface IGuestCheckoutTheme {
  getCheckoutForm(): Locator;
  getOrderSummary(): Locator;
  getNameInput(): Locator;
  getPhoneInput(): Locator;
  getEmailInput(): Locator;
  getDeliveryZoneSelect(): Locator;
  getAddressInput(): Locator;
  getPaymentMethodSelector(): Locator;
  getPaymentCodOption(): Locator;
  getPaymentOnlineOption(): Locator;
  getPlaceOrderButton(): Locator;
  getOrderConfirmation(): Locator;
  getTrackOrderForm(): Locator;
  getTrackOrderInput(): Locator;
  getTrackPhoneInput(): Locator;
  getTrackSubmitButton(): Locator;
  getOrderStatusTimeline(): Locator;
}

export interface IHeroTheme {
  getHeroSlider(): Locator;
  getSlides(): Locator;
  getNextButton(): Locator;
  getPrevButton(): Locator;
  getSlideCta(slide: Locator): Locator;
}

export interface IComboTheme {
  getCombosSection(): Locator;
  getComboCards(): Locator;
  getComboTitle(card: Locator): Locator;
  getComboAddToCartButton(card: Locator): Locator;
}

export interface IReviewTheme {
  getReviewsSection(): Locator;
  getRatingStarsInput(): Locator;
  getCommentInput(): Locator;
  getSubmitButton(): Locator;
  getReviewCards(): Locator;
}

export interface INotificationTheme {
  getTrigger(): Locator;
  getBadgeCount(): Locator;
  getDropdown(): Locator;
  getMarkAllReadButton(): Locator;
}

export interface ICmsTheme {
  getPageContainer(): Locator;
  getTitle(): Locator;
  getContent(): Locator;
}

export interface IOrderTheme {
  getCheckoutForm(): Locator;
  getOrderSummary(): Locator;
  getPlaceOrderButton(): Locator;
  getOrderTypeDelivery(): Locator;
  getOrderTypeTakeaway(): Locator;
  getBranchSelectTrigger(): Locator;
  getOrdersList(): Locator;
  getOrderCards(): Locator;
  getOrderCard(index: number): Locator;
  getOrderNumber(card: Locator): Locator;
  getOrderStatusBadge(card: Locator): Locator;
  getOrderTotal(card: Locator): Locator;
  getViewOrderDetailsButton(card: Locator): Locator;
  getEmptyOrdersMessage(): Locator;
  getOrderDetailsContainer(): Locator;
  getOrderStatusTimeline(): Locator;
  getOrderItemsList(): Locator;
  getDetailOrderTotal(): Locator;
}

export interface IThemeAdapter {
  readonly name: string;
  readonly page: Page;
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
}
