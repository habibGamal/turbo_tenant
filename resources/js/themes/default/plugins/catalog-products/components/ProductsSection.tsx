import React, { useState } from "react";
import { Link } from "react-router-dom";
import ProductCard from "./ProductCard";
import { Button } from "@/components/ui/button";
import { Star, TrendingUp, Flame } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { useAddToCart } from "@/plugins/cart-engine/api";

import { Product } from "@/types";

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

export interface ProductSection {
    id: string;
    title: string;
    titleAr?: string;
    subtitle?: string;
    subtitleAr?: string;
    products: Product[];
    icon?: "trending" | "flame" | "star";
}

export interface ProductsSectionProps {
    sections?: ProductSection[];
}

export default function ProductsSection({ sections }: ProductsSectionProps) {
    const { t, i18n } = useTranslation();
    const { mutate: addToCartMutation } = useAddToCart();
    const [addingToCart, setAddingToCart] = useState<{
        [key: number]: boolean;
    }>({});

    const handleAddToCart = (e: React.MouseEvent, productId: number) => {
        e.preventDefault();
        e.stopPropagation();

        setAddingToCart((prev) => ({ ...prev, [productId]: true }));

        addToCartMutation(
            {
                product_id: productId,
                quantity: "1",
            },
            {
                onSettled: () => {
                    setAddingToCart((prev) => ({
                        ...prev,
                        [productId]: false,
                    }));
                },
                onError: () => {
                    toast.error(t("failedToAddToCart", "Failed to add to cart"));
                },
            },
        );
    };

    const defaultSections: ProductSection[] = [];

    const sectionsToUse = sections || defaultSections;

    const getSectionIcon = (iconName?: string) => {
        const iconClass = "h-5 w-5";
        switch (iconName) {
            case "trending":
                return <TrendingUp className={iconClass} />;
            case "flame":
                return <Flame className={iconClass} />;
            case "star":
            default:
                return <Star className={iconClass} />;
        }
    };

    return (
        <>
            {sectionsToUse.map((section, sectionIndex) => (
                <section
                    key={section.id}
                    className={`py-12 md:py-20 ${sectionIndex % 2 === 0 ? "bg-background" : "bg-muted/30"}`}
                >
                    <div className="container mx-auto px-4">
                        {/* Section Header */}
                        <div className="flex flex-row items-end justify-between mb-10 md:mb-14 gap-4">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <div className="p-2 rounded-lg bg-primary/10">
                                        <div className="text-primary">
                                            {getSectionIcon(section.icon)}
                                        </div>
                                    </div>
                                    <h2 className="text-3xl md:text-4xl font-bold tracking-tight">
                                        {i18n.language === "ar" &&
                                        section.titleAr
                                            ? section.titleAr
                                            : section.title}
                                    </h2>
                                </div>
                                {section.subtitle && (
                                    <p className="text-lg text-muted-foreground md:ms-12">
                                        {i18n.language === "ar" &&
                                        section.subtitleAr
                                            ? section.subtitleAr
                                            : section.subtitle}
                                    </p>
                                )}
                            </div>
                            <Button asChild variant="outline">
                                <Link to="/menu">
                                    {t("viewAll")}
                                </Link>
                            </Button>
                        </div>

                        {/* Products Grid */}
                        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {pushUnavailableToEnd(section.products).map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    onAddToCart={handleAddToCart}
                                    addingToCart={addingToCart[product.id]}
                                    showFavorite={true}
                                />
                            ))}
                        </div>
                    </div>
                </section>
            ))}
        </>
    );
}
