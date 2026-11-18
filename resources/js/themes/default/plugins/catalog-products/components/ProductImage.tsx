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
            <div className="aspect-[4/3] sm:aspect-square max-h-[260px] sm:max-h-[480px] lg:max-h-none w-full mx-auto rounded-2xl overflow-hidden border border-border bg-muted/70 dark:bg-black/25 relative group">
                <ImageWithFallback
                    src={product.image}
                    alt={getText(product.name, product.name_ar)}
                    priority={true}
                    className="w-full h-full object-contain p-2 sm:p-0 sm:object-cover transition-transform duration-500 group-hover:scale-105"
                />

                {/* Discount Badge */}
                {hasDiscount && (
                    <Badge className="absolute z-10 top-3 start-3 sm:top-4 sm:start-4 text-sm sm:text-lg px-3 py-1 sm:px-4 sm:py-2 shadow-none animate-in fade-in zoom-in duration-300">
                        <span dir="ltr">{discountPercentage}%</span>&nbsp;{t("off")}
                    </Badge>
                )}

                {/* Share Button */}
                <Button
                    size="icon"
                    variant="secondary"
                    aria-label={t("share", "Share")}
                    className="absolute top-3 end-3 sm:top-4 sm:end-4 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:focus-visible:opacity-100 transition-opacity duration-300 border border-border/80 bg-background/90 hover:bg-background dark:bg-card/90 shadow-none"
                    onClick={handleShare}
                >
                    <Share2 className="h-4 w-4 sm:h-5 sm:w-5" />
                </Button>
            </div>
        </div>
    );
}
