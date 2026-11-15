import React from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ImageWithFallback } from "@/components/ui/image";
import { Share2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Product } from "@/types";
import { NormalizedProduct } from "@/plugins/catalog-products/types";
import { hasActiveDiscount, getDiscountPercent } from "@/lib/pricing";

interface ProductImageProps {
    product: Product | NormalizedProduct;
}

export default function ProductImage({ product }: ProductImageProps) {
    const { t, i18n } = useTranslation();

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const hasDiscount = hasActiveDiscount(product);
    const discountPercentage = getDiscountPercent(product);

    const handleShare = async () => {
        const title = getText(product.name, product.name_ar);
        const description = getText(
            product.description || "",
            product.descriptionAr || (product as any).description_ar
        );
        const url = window.location.href;

        if (navigator.share) {
            try {
                await navigator.share({
                    title,
                    text: description,
                    url,
                });
            } catch (error: any) {
                if (error?.name !== "AbortError") {
                    console.error("Error sharing product:", error);
                }
            }
        } else {
            try {
                await navigator.clipboard.writeText(url);
                toast.success(t("linkCopied", "Link copied"));
            } catch {
                toast.error(t("couldNotCopyLink", "Could not copy the link"));
            }
        }
    };

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
                    <Badge className="absolute z-10 top-4 start-4 text-lg px-4 py-2 shadow-lg animate-in fade-in zoom-in duration-300">
                        {discountPercentage}% {t("off")}
                    </Badge>
                )}

                {/* Share Button */}
                <Button
                    size="icon"
                    variant="secondary"
                    aria-label={t("share", "Share")}
                    className="absolute top-4 end-4 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:focus-visible:opacity-100 transition-opacity duration-300 shadow-md"
                    onClick={handleShare}
                >
                    <Share2 className="h-5 w-5" />
                </Button>
            </div>
        </div>
    );
}
