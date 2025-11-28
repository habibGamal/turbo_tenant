import React from "react";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Star, Package, Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product, PageProps } from "@/types";
import { ImageWithFallback } from "@/components/ui/image";
import { usePage } from "@inertiajs/react";

interface ProductInfoProps {
    product: Product;
    totalPrice: number;
}

export default function ProductInfo({ product, totalPrice }: ProductInfoProps) {
    const { t } = useTranslation();
    const { settings } = usePage<PageProps>().props;

    const basePrice = product.base_price || 0;
    const hasDiscount =
        product.price_after_discount &&
        product.price_after_discount < basePrice;

    const categoryName = typeof product.category === 'object' ? product.category?.name : product.category;

    return (
        <div className="space-y-6">
            {/* Category Badge */}
            {categoryName && (
                <Badge variant="outline" className="text-sm">
                    {categoryName}
                </Badge>
            )}

            {/* Product Name */}
            <h1 className="text-4xl md:text-5xl font-bold tracking-tight text-foreground">
                {product.name}
            </h1>

            {/* Rating */}
            <div className="flex items-center gap-4">
                <div className="flex items-center gap-1">
                    {[...Array(5)].map((_, i) => (
                        <Star
                            key={i}
                            className={`h-5 w-5 ${i < Math.floor(product.rating || 0)
                                ? "fill-yellow-400 text-yellow-400"
                                : "text-gray-300"
                                }`}
                        />
                    ))}
                </div>
                <span className="text-lg font-medium">
                    {product.rating}
                </span>
                <span className="text-muted-foreground">
                    ({product.reviewsCount} {t("reviews")})
                </span>
            </div>

            {/* Price */}
            <div className="flex items-center gap-4">
                <div>
                    <div className="text-4xl font-bold text-primary">
                        {totalPrice.toFixed(2)}{" "}
                        {t("currency")}
                    </div>
                    {product.sell_by_weight && (
                        <div className="text-sm text-muted-foreground mt-1">
                            {(product.price_after_discount ?? basePrice).toFixed(2)} {t("currency")} {t("perKg")}
                        </div>
                    )}
                </div>
                {hasDiscount && (
                    <div className="text-2xl text-muted-foreground line-through decoration-red-500/50">
                        {basePrice.toFixed(2)}{" "}
                        {t("currency")}
                    </div>
                )}
            </div>

            {/* Description */}
            <p className="text-lg text-muted-foreground leading-relaxed">
                {product.description}
            </p>

            <Separator />

            {/* Features */}
            <div className="grid grid-cols-2 gap-4 pt-4">
                {settings.product_show_cards && settings.product_show_cards.length > 0 ? (
                    settings.product_show_cards.map((card, index) => (
                        <div key={index} className="flex items-center gap-3 p-4 bg-muted/50 rounded-xl border border-border/50 hover:bg-muted transition-colors">
                            <div className="h-8 w-8 relative shrink-0">
                                <ImageWithFallback
                                    src={card.icon}
                                    alt={card.title}
                                    className="object-contain"
                                />
                            </div>
                            <div>
                                <div className="font-semibold text-sm">
                                    {card.title}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {card.description}
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <>
                        <div className="flex items-center gap-3 p-4 bg-muted/50 rounded-xl border border-border/50 hover:bg-muted transition-colors">
                            <Package className="h-6 w-6 text-primary" />
                            <div>
                                <div className="font-semibold text-sm">
                                    {t("freshIngredients")}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {t("qualityGuaranteed")}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 p-4 bg-muted/50 rounded-xl border border-border/50 hover:bg-muted transition-colors">
                            <Clock className="h-6 w-6 text-primary" />
                            <div>
                                <div className="font-semibold text-sm">
                                    {t("fastDelivery")}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {t("30MinOrLess")}
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
