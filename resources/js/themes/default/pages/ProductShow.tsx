import React, { useState, useEffect } from "react";
import ReactPixel from 'react-facebook-pixel';
import { Head, Link, usePage } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "react-i18next";
import { Product, Review, ExtraOptionItem, ProductVariant, PageProps } from "@/types";
import { addToCart, ExtraWithQuantity } from "@/utils/cartUtils";
import { useProductOptions } from "@/hooks/useProductOptions";
import ProductImage from "@/themes/default/components/product/ProductImage";
import ProductInfo from "@/themes/default/components/product/ProductInfo";
import ProductOptions from "@/themes/default/components/product/ProductOptions";
import ReviewsSection from "@/themes/default/components/product/ReviewsSection";
import ProductCard from "@/themes/default/components/ProductCard";
import { useFavorites } from "@/hooks/useFavorites";

interface RelatedProduct {
    id: number;
    name: string;
    description: string;
    image?: string;
    price: number;
    base_price: number;
    price_after_discount?: number;
    category?: string;
    rating?: number;
}

interface ProductShowProps {
    product: Product & {
        variants?: ProductVariant[];
        extraOption?: {
            id: number;
            name: string;
            description?: string;
            items: ExtraOptionItem[];
        };
    };
    reviews: Review[];
    relatedProducts: Product[]; // Updated to match ProductCard expectation
    promotionalProducts: Product[]; // Updated to match ProductCard expectation
}

export default function ProductShow({
    product,
    reviews,
    relatedProducts,
    promotionalProducts,
}: ProductShowProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const isProductActive = product.is_active ?? true;
    const { settings } = usePage<PageProps>().props;

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

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
    } = useProductOptions({ product });

    const { isFavorite, toggleFavorite } = useFavorites();

    const handleToggleFavorite = () => {
        toggleFavorite(product.id);
    };

    useEffect(() => {
        ReactPixel.track("ViewContent", {
            content_ids: [product.id],
            contents: [{ id: product.id, quantity: 1 }],
            content_type: "product",
            value: product.price_after_discount || product.base_price || product.price,
            currency: "EGP",
        });
    }, [selectedVariant, product]);

    console.log("Product Data:", product);
    const categoryName = getText(product.category!.name,product.category!.name_ar);

    return (
        <MainLayout categories={[]} cartItemsCount={0}>
            <Head title={getText(product.name, product.name_ar)} />
            <div className="min-h-screen bg-background">
                <main className="container mx-auto px-4 py-8">
                    {/* Breadcrumb */}
                    <div className="flex items-center gap-2 text-sm text-muted-foreground mb-6">
                        <Link
                            href="/"
                            className="hover:text-foreground transition-colors"
                        >
                            {t("home")}
                        </Link>
                        <span>/</span>
                        {product.category && (
                            <>
                                <Link
                                    href={`/menu?category[]=${categoryName}`}
                                    className="hover:text-foreground transition-colors"
                                >
                                    {categoryName}
                                </Link>
                                <span>/</span>
                            </>
                        )}
                        <span className="text-foreground">{getText(product.name, product.name_ar)}</span>
                    </div>

                    {/* Product Details Section */}
                    <div className="grid lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
                        {/* Product Image */}
                        <ProductImage product={product} />

                        {/* Product Info & Options */}
                        <div className="space-y-6">
                            {!isProductActive && (
                                <div className="rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                                    {t('unavailable')}
                                </div>
                            )}

                            <ProductInfo product={product} totalPrice={calculateTotalPrice()} />

                            <div className={!isProductActive ? 'opacity-60 pointer-events-none' : ''}>
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
                                        if (!isProductActive) {
                                            return;
                                        }

                                        handleAddToCart(() => {
                                            let price = product.price_after_discount || product.base_price || product.price;
                                            let contentId = product.id;

                                            if (selectedVariant && product.variants) {
                                                const variant = product.variants.find(v => v.id === selectedVariant);
                                                if (variant) {
                                                    price = variant.price || price;
                                                    contentId = variant.id;
                                                }
                                            }

                                            ReactPixel.track("AddToCart", {
                                                content_ids: [contentId],
                                                content_type: "product",
                                                contents: [{ id: contentId, quantity: quantity }],
                                                value: price,
                                                currency: "EGP",
                                            });
                                        });
                                    }}
                                    isAddingToCart={isAddingToCart }
                                    isFavorite={isFavorite(product.id)}
                                    handleToggleFavorite={handleToggleFavorite}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Tabs Section */}
                    <Tabs
                        defaultValue="reviews"
                        className="mb-16"
                        dir={isRTL ? "rtl" : "ltr"}
                    >
                        <TabsList className="grid w-full max-w-md grid-cols-2">
                            <TabsTrigger value="reviews">
                                {t("reviews")}
                            </TabsTrigger>
                            <TabsTrigger value="details">
                                {t("details")}
                            </TabsTrigger>
                        </TabsList>

                        {/* Reviews Tab */}
                        <TabsContent value="reviews" className="mt-8">
                            <ReviewsSection
                                productId={product.id}
                                reviews={reviews}
                                averageRating={product.rating || 0}
                            />
                        </TabsContent>

                        {/* Details Tab */}
                        <TabsContent value="details" className="space-y-6 mt-8">
                            <Card>
                                <CardHeader>
                                    <h3 className="text-xl font-bold">
                                        {t("productDetails")}
                                    </h3>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-3">
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("category")}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {categoryName || t("uncategorized")}
                                            </span>
                                        </div>
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("basePrice")}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {(product.base_price || 0).toFixed(2)}{" "}
                                                {t("currency")}
                                            </span>
                                        </div>
                                        {product.sell_by_weight && (
                                            <div className="flex justify-between py-2 border-b">
                                                <span className="font-medium">
                                                    {t("soldBy")}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t("weight")}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("availability")}
                                            </span>
                                            <Badge
                                                variant={
                                                    product.is_active
                                                        ? "default"
                                                        : "secondary"
                                                }
                                            >
                                                {product.is_active
                                                    ? t("available")
                                                    : t("unavailable")}
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
                                <h2 className="text-3xl font-bold">
                                    {t("similarProducts")}
                                </h2>
                                <Link
                                    href={`/categories/${typeof product.category === 'object' ? product.category?.id : '#'}`}
                                >
                                    <Button variant="outline">
                                        {t("viewAll")}
                                    </Button>
                                </Link>
                            </div>
                            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                {relatedProducts.map((relatedProduct) => (
                                    <ProductCard
                                        key={relatedProduct.id}
                                        product={relatedProduct}
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    {/* Promotional Products */}
                    {promotionalProducts.length > 0 && (
                        <section>
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-3xl font-bold">
                                    {t("specialOffers")}
                                </h2>
                                <Link href="/promotions">
                                    <Button variant="outline">
                                        {t("viewAll")}
                                    </Button>
                                </Link>
                            </div>
                            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                {promotionalProducts.map((promoProduct) => (
                                    <ProductCard
                                        key={promoProduct.id}
                                        product={promoProduct}
                                    />
                                ))}
                            </div>
                        </section>
                    )}
                </main>
            </div>
        </MainLayout>
    );
}
