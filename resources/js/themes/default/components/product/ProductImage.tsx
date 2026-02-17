import React from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ImageWithFallback } from "@/components/ui/image";
import { Share2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product } from "@/types";

interface ProductImageProps {
    product: Product;
}

export default function ProductImage({ product }: ProductImageProps) {
    const { t, i18n } = useTranslation();

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const basePrice = product.base_price || 0;
    const hasDiscount =
        product.price_after_discount &&
        product.price_after_discount < basePrice;

    const discountPercentage = hasDiscount
        ? Math.round(
            ((basePrice - product.price_after_discount!) /
                basePrice) *
            100
        )
        : 0;

    return (
        <div className="space-y-4">
            <div className="aspect-square rounded-2xl overflow-hidden bg-gradient-to-br from-primary/5 to-secondary/5 relative group">
                <ImageWithFallback
                    src={product.image}
                    alt={getText(product.name, product.name_ar)}
                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                />

                {/* Discount Badge */}
                {hasDiscount && (
                    <Badge className="absolute z-10 top-4 ltr:left-4 rtl:right-4 text-lg px-4 py-2 shadow-lg animate-in fade-in zoom-in duration-300">
                        {discountPercentage}% {t("off")}
                    </Badge>
                )}

                {/* Share Button */}
                <Button
                    size="icon"
                    variant="secondary"
                    className="absolute top-4 ltr:right-4 rtl:left-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow-md"
                    onClick={() => {
                        if (navigator.share) {
                            navigator.share({
                                title: getText(product.name, product.name_ar),
                                text: product.description,
                                url: window.location.href,
                            });
                        }
                    }}
                >
                    <Share2 className="h-5 w-5" />
                </Button>
            </div>
        </div>
    );
}
