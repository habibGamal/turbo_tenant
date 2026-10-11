import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import { addToCart } from "@/utils/cartUtils";
import ProductCard from "@/themes/default/components/ProductCard";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Star, TrendingUp, Flame } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Product } from "@/types";

interface ProductSection {
    id: string;
    title: string;
    titleAr?: string;
    subtitle?: string;
    subtitleAr?: string;
    products: Product[];
    icon?: "trending" | "flame" | "star";
}

interface ProductsSectionProps {
    sections?: ProductSection[];
}

export default function ProductsSection({ sections }: ProductsSectionProps) {
    const { t, i18n } = useTranslation();
    const [addingToCart, setAddingToCart] = useState<{
        [key: number]: boolean;
    }>({});

    const handleAddToCart = async (e: React.MouseEvent, productId: number) => {
        e.preventDefault();
        e.stopPropagation();

        setAddingToCart((prev) => ({ ...prev, [productId]: true }));

        await addToCart(
            {
                product_id: productId,
                variant_id: null,
                quantity: "1",
                extras: [],
            },
            {
                onFinally: () => {
                    setAddingToCart((prev) => ({
                        ...prev,
                        [productId]: false,
                    }));
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
                return <Star className={iconClass} />;
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
                                    <p className="text-lg text-muted-foreground ltr:md:ml-12 rtl:md:mr-12">
                                        {i18n.language === "ar" &&
                                        section.subtitleAr
                                            ? section.subtitleAr
                                            : section.subtitle}
                                    </p>
                                )}
                            </div>
                            <Link href={route("sections.show", section.id)}>
                                <Button variant="outline" className="gap-2">
                                    {t("View All")}
                                </Button>
                            </Link>
                        </div>

                        {/* Products Grid */}
                        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {section.products.map((product) => (
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
