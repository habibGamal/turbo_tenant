import React from "react";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Star, Truck } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product } from "@/types";
import { NormalizedProduct } from "@/plugins/catalog-products/types";
import { ImageWithFallback } from "@/components/ui/image";
import { useTenant } from "@/core/context/TenantContext";
import { hasActiveDiscount, getEffectivePrice } from "@/lib/pricing";
import { useFormatMoney } from "@/lib/money";
import { formatReviewsCount } from "@/lib/arabicPlural";

interface ProductInfoProps {
    product: NormalizedProduct | Product;
    totalPrice: number;
}

export default function ProductInfo({ product, totalPrice }: ProductInfoProps) {
    const { t, i18n } = useTranslation();
    const { settings = {} } = useTenant();
    const formatMoney = useFormatMoney();

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const basePrice = Number(product.base_price || 0);
    const hasDiscount = hasActiveDiscount(product);

    const categoryName = product.category ? getText(product.category.name, product.category.name_ar) : "";
    const rating = Number(product.rating || 0);
    const reviewsCount = Number(product.reviewsCount ?? (product as any).reviews_count ?? 0);
    const hasReviewsOrRating = rating > 0 || reviewsCount > 0;
    const freeShippingThreshold = Number(settings?.free_shipping_threshold || 0);
    const showNationwideShipping = Boolean(settings?.simplified_address_enabled);
    const shippingNotice =
        showNationwideShipping && freeShippingThreshold > 0
            ? `${t("nationwideShippingAvailable")} · ${t("freeShippingOver", { amount: formatMoney(freeShippingThreshold) })}`
            : freeShippingThreshold > 0
                ? t("freeShippingOver", { amount: formatMoney(freeShippingThreshold) })
                : showNationwideShipping
                    ? t("nationwideShippingAvailable")
                    : null;

    return (
        <div className="space-y-4 sm:space-y-6">
            {/* Category Badge */}
            {categoryName && (
                <Badge variant="outline" className="text-xs sm:text-sm">
                    {categoryName}
                </Badge>
            )}

            {/* Product Name */}
            <h1 className="text-2xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground break-words">
                {getText(product.name, product.name_ar)}
            </h1>

            {/* Rating */}
            {!hasReviewsOrRating ? (
                <p className="text-sm text-muted-foreground">
                    {t("noReviewsYet", "No reviews yet")}
                </p>
            ) : (
                <div className="flex items-center gap-3 sm:gap-4 flex-wrap">
                    <div className="flex items-center gap-1">
                        {[...Array(5)].map((_, i) => (
                            <Star
                                key={i}
                                className={`h-4 w-4 sm:h-5 sm:w-5 ${i < Math.floor(product.rating || 0)
                                    ? "fill-amber-400 text-amber-400"
                                    : "text-muted-foreground/30"
                                    }`}
                            />
                        ))}
                    </div>
                    <span className="text-base sm:text-lg font-medium">
                        {product.rating}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        ({formatReviewsCount(reviewsCount, i18n.language === "ar")})
                    </span>
                </div>
            )}

            {/* Price */}
            <div className="flex items-baseline gap-3 sm:gap-4 flex-wrap">
                <div>
                    <div data-testid="product-total-price" className="text-3xl sm:text-4xl font-bold text-primary">
                        {formatMoney(totalPrice)}
                    </div>
                    {product.sell_by_weight && (
                        <div className="text-xs sm:text-sm text-muted-foreground mt-1">
                            {formatMoney(getEffectivePrice(product))} {t("perKg")}
                        </div>
                    )}
                </div>
                {hasDiscount && (
                    <div className="text-xl sm:text-2xl text-muted-foreground line-through decoration-destructive/50">
                        {formatMoney(basePrice)}
                    </div>
                )}
            </div>

            {shippingNotice && (
                <div
                    data-testid="product-shipping-notice"
                    className="inline-flex items-center gap-2 rounded-lg border border-border bg-muted/70 dark:bg-black/25 px-3 py-1.5 text-xs sm:text-sm text-muted-foreground"
                >
                    <Truck className="h-4 w-4 shrink-0 text-primary" />
                    <span>{shippingNotice}</span>
                </div>
            )}

            {/* Description */}
            <p dir="auto" className="text-base sm:text-lg text-muted-foreground leading-relaxed break-words">
                {getText(product.description || "", product.descriptionAr || (product as any).description_ar)}
            </p>

            {/* Features */}
            {settings.product_show_cards && settings.product_show_cards.length > 0 && (
                <>
                    <Separator />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-4">
                        {settings.product_show_cards.map((card: any, index: number) => (
                            <div key={index} className="flex items-center gap-3 p-3 sm:p-4 bg-muted/70 dark:bg-black/25 rounded-xl border border-border hover:border-primary/40 transition-colors">
                                <div className="h-8 w-8 relative shrink-0">
                                    <ImageWithFallback
                                        src={card.icon}
                                        alt={card.title}
                                        className="object-contain"
                                    />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="font-semibold text-sm truncate">
                                        {card.title}
                                    </div>
                                    <div className="text-xs text-muted-foreground line-clamp-2">
                                        {card.description}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
