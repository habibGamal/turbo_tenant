import React, { useState, useEffect, useMemo, useRef } from "react";
import { Link, useParams } from "react-router-dom";
import { emitCommerceEvent } from "@/core/analytics";
import { Head } from "@/components/Head";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { PluginSlot } from "@/core/slots/PluginSlot";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "react-i18next";
import { Product, Review, ExtraOptionItem, ProductVariant } from "@/types";
import { normalizeProduct } from "@/plugins/catalog-products/utils/normalizeProduct";
import { NormalizedProduct } from "@/plugins/catalog-products/types";
import { useProductOptions } from "@/hooks/useProductOptions";
import ProductImage from "./components/ProductImage";
import ProductInfo from "./components/ProductInfo";
import ProductOptions from "./components/ProductOptions";
import ProductCard from "./components/ProductCard";
import ProductPageSkeleton from "./components/ProductPageSkeleton";
import ProductComboUpsell from "./components/ProductComboUpsell";
import { useFavorites } from "@/hooks/useFavorites";
import { useCatalogProduct } from "@/plugins/catalog-products/api/useCatalogProduct";
import { getEffectivePrice } from "@/lib/pricing";
import { useFormatMoney, useCurrency } from "@/lib/money";

function pushUnavailableToEnd(products: Product[]): Product[] {
  const available: Product[] = [];
  const unavailable: Product[] = [];
  for (const product of products) {
    const isAvail = (product.is_active ?? true) && (product as any).is_available !== false;
    if (isAvail) {
      available.push(product);
    } else {
      unavailable.push(product);
    }
  }
  return [...available, ...unavailable];
}

export interface ThemedProductShowProps {
  productId?: string | number;
}

export function ThemedProductShow({ productId: propProductId }: ThemedProductShowProps) {
  const { id: paramProductId } = useParams<{ id: string }>();
  const productId = propProductId || paramProductId;

  const { data: rawProduct, isLoading, isError } = useCatalogProduct(productId);
  const { t, i18n } = useTranslation();
  const formatMoney = useFormatMoney();
  const currency = useCurrency();
  const isRTL = i18n.language === "ar";

  const getText = (text: string, textAr?: string) => {
    return isRTL && textAr ? textAr : text;
  };

  // Normalize product options and relations
  const product: NormalizedProduct | null = useMemo(() => {
    return rawProduct ? normalizeProduct(rawProduct) : null;
  }, [rawProduct]);

  const { isFavorite, toggleFavorite } = useFavorites();

  const productOptions = useProductOptions({
    product,
  });

  const {
    quantity,
    setQuantity,
    selectedWeightValue,
    setSelectedWeightValue,
    selectedVariant,
    setSelectedVariant,
    selectedExtras,
    handleExtraToggle,
    handleExtraQuantityChange,
    handleQuantityChange,
    calculateTotalPrice,
    handleAddToCart,
    isAddingToCart,
    limitReachedFor,
    canAddToCart,
    missingRequiredSelections,
  } = productOptions;

  const handleToggleFavorite = () => {
    if (product?.id) {
      toggleFavorite(product.id, getEffectivePrice(product));
    }
  };

  const lastViewedRef = useRef<string | null>(null);

  useEffect(() => {
    if (!product) return;

    const viewKey = `${product.id}:${selectedVariant ?? 'none'}`;
    if (lastViewedRef.current === viewKey) return;
    lastViewedRef.current = viewKey;

    const hasVariants = Boolean(product.variants && product.variants.length > 0);
    const isVariantSelected = selectedVariant !== null && selectedVariant !== undefined;

    let effectivePrice = getEffectivePrice(product);
    let contentType: 'product' | 'product_group' = 'product';

    if (hasVariants && !isVariantSelected) {
      contentType = 'product_group';
    } else if (isVariantSelected && product.variants) {
      const variant = product.variants.find((v) => v.id === selectedVariant);
      if (variant && variant.price !== null && variant.price !== undefined) {
        effectivePrice = Number(variant.price);
      }
    }

    emitCommerceEvent('ViewContent', {
      items: [
        {
          product_id: product.id,
          variant_id: isVariantSelected ? selectedVariant : null,
          quantity: 1,
          item_price: effectivePrice,
          name: product.name,
          category: (product as any).category?.name,
        },
      ],
      value: effectivePrice,
      currency,
      content_type: contentType,
      content_name: product.name,
      content_category: (product as any).category?.name,
    });
  }, [product?.id, selectedVariant, currency]);

  if (isLoading) {
    return (
      <MainLayout>
        <ProductPageSkeleton />
      </MainLayout>
    );
  }

  if (isError || !product) {
    return (
      <MainLayout>
        <div data-testid="product-not-found" className="min-h-[50vh] flex flex-col items-center justify-center p-8 text-center">
          <h2 className="text-2xl font-bold mb-2">{t("productNotFound", { defaultValue: "Product Not Found" })}</h2>
          <p className="text-muted-foreground mb-6">
            {t("productUnavailableMessage", { defaultValue: "The requested product is currently unavailable or has been removed." })}
          </p>
          <Link to="/menu">
            <Button data-testid="browse-menu-button">{t("browseMenu", { defaultValue: "Browse Menu" })}</Button>
          </Link>
        </div>
      </MainLayout>
    );
  }

  const isProductActive = product.is_active ?? true;
  const categoryName = product.category
    ? getText(product.category.name, product.category.name_ar)
    : "";

  const relatedProducts = (rawProduct?.related_products || []) as unknown as Product[];
  const promotionalProducts = (rawProduct?.promotional_products || []) as unknown as Product[];

  return (
    <MainLayout>
      <Head title={getText(product.name, product.name_ar)} />
      <div className="min-h-screen bg-background">
        <main className="container mx-auto px-4 py-8">
          {/* Breadcrumb */}
          <div className="flex items-center gap-2 text-sm text-muted-foreground mb-6">
            <Link to="/" className="hover:text-foreground transition-colors">
              {t("home")}
            </Link>
            <span>/</span>
            {product.category && (
              <>
                <Link
                  to={`/menu?category_ids[]=${product.category.id}`}
                  className="hover:text-foreground transition-colors"
                >
                  {categoryName}
                </Link>
                <span>/</span>
              </>
            )}
            <span className="text-foreground font-medium">{getText(product.name, product.name_ar)}</span>
          </div>

          {/* Product Details Section */}
          <div data-testid="product-details-container" className="grid lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
            {/* Product Image */}
            <ProductImage product={product} />

            {/* Product Info & Options */}
            <div className="space-y-6">
              {!isProductActive && (
                <div className="rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                  {t("unavailable")}
                </div>
              )}

              <ProductInfo product={product} totalPrice={calculateTotalPrice()} />

              <div className={!isProductActive ? "opacity-60 pointer-events-none" : ""}>
                <ProductOptions
                  product={product}
                  selectedVariant={selectedVariant}
                  setSelectedVariant={setSelectedVariant}
                  selectedExtras={selectedExtras}
                  handleExtraToggle={handleExtraToggle}
                  handleExtraQuantityChange={handleExtraQuantityChange}
                  quantity={quantity}
                  handleQuantityChange={handleQuantityChange}
                  selectedWeightValue={selectedWeightValue}
                  setSelectedWeightValue={setSelectedWeightValue}
                  setQuantity={setQuantity}
                  handleAddToCart={() => {
                    if (!isProductActive || !canAddToCart) return;
                    handleAddToCart();
                  }}
                  isAddingToCart={isAddingToCart}
                  isFavorite={isFavorite(product.id)}
                  handleToggleFavorite={handleToggleFavorite}
                  canAddToCart={canAddToCart}
                  missingRequiredSelections={missingRequiredSelections}
                  limitReachedFor={limitReachedFor}
                  totalPrice={calculateTotalPrice()}
                />
              </div>
            </div>
          </div>

          {/* Make it a combo upsell section */}
          {product.combo_packages && product.combo_packages.length > 0 && (
            <ProductComboUpsell packages={product.combo_packages} />
          )}

          {/* Product Extension Slot (e.g. Additional Information / Badges) */}
          <PluginSlot
            name="product.details.after"
            pluginId="catalog-products"
            context={{ productId: product.id }}
          />

          {/* Tabs Section */}
          <Tabs
            defaultValue="reviews"
            className="mb-16"
            dir={isRTL ? "rtl" : "ltr"}
          >
            <TabsList className="grid w-full max-w-md grid-cols-2">
              <TabsTrigger value="reviews">{t("reviews")}</TabsTrigger>
              <TabsTrigger value="details">{t("details")}</TabsTrigger>
            </TabsList>

            {/* Reviews Tab */}
            <TabsContent value="reviews" className="mt-8">
              <PluginSlot
                name="product.reviews"
                pluginId="product-reviews"
                context={{ productId: product.id }}
              />
            </TabsContent>

            {/* Details Tab */}
            <TabsContent value="details" className="space-y-6 mt-8">
              <Card>
                <CardHeader>
                  <h3 className="text-xl font-bold">{t("productDetails")}</h3>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid gap-3">
                    <div className="flex justify-between py-2 border-b">
                      <span className="font-medium">{t("category")}</span>
                      <span className="text-muted-foreground">{categoryName || t("uncategorized")}</span>
                    </div>
                    <div className="flex justify-between py-2 border-b">
                      <span className="font-medium">{t("basePrice")}</span>
                      <span className="text-muted-foreground">
                        {formatMoney(product.base_price || 0)}
                      </span>
                    </div>
                    {product.sell_by_weight && (
                      <div className="flex justify-between py-2 border-b">
                        <span className="font-medium">{t("soldBy")}</span>
                        <span className="text-muted-foreground">{t("weight")}</span>
                      </div>
                    )}
                    <div className="flex justify-between py-2 border-b">
                      <span className="font-medium">{t("availability")}</span>
                      <Badge variant={product.is_active ? "default" : "secondary"}>
                        {product.is_active ? t("available") : t("unavailable")}
                      </Badge>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>

          {/* Related Products */}
          {relatedProducts.length > 0 && (
            <section className="mb-16">
              <div className="flex items-center justify-between mb-8">
                <h2 className="text-3xl font-bold">{t("similarProducts")}</h2>
                <Link to="/menu">
                  <Button variant="outline">{t("viewAll")}</Button>
                </Link>
              </div>
              <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {pushUnavailableToEnd(relatedProducts).map((relatedProduct) => (
                  <ProductCard key={relatedProduct.id} product={relatedProduct} />
                ))}
              </div>
            </section>
          )}

          {/* Promotional Products */}
          {promotionalProducts.length > 0 && (
            <section>
              <div className="flex items-center justify-between mb-8">
                <h2 className="text-3xl font-bold">{t("specialOffers")}</h2>
                <Link to="/menu">
                  <Button variant="outline">{t("viewAll")}</Button>
                </Link>
              </div>
              <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {pushUnavailableToEnd(promotionalProducts).map((promoProduct) => (
                  <ProductCard key={promoProduct.id} product={promoProduct} />
                ))}
              </div>
            </section>
          )}
        </main>
      </div>
    </MainLayout>
  );
}

export default ThemedProductShow;
