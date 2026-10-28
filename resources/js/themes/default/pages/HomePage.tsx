import React from 'react';
import MainLayout from '@/themes/default/layouts/MainLayout';
import { PluginSlot } from '@/core/slots/PluginSlot';
import CategoriesSection from '@/themes/default/plugins/catalog-products/components/CategoriesSection';
import ProductsSection from '@/themes/default/plugins/catalog-products/components/ProductsSection';
import { Separator } from '@/components/ui/separator';
import { Product } from '@/types';

interface Category {
    id: number;
    name: string;
    name_ar?: string;
    nameAr?: string;
    slug: string;
    description?: string;
    description_ar?: string;
    descriptionAr?: string;
    image?: string;
}

interface Section {
    id: number;
    title: string;
    title_ar: string;
    products: Product[];
}

interface HomePageProps {
    categories?: Category[];
    sections: Section[];
    cartItemsCount?: number;
}

export default function HomePage({
    categories = [],
    sections,
    cartItemsCount = 0,
}: HomePageProps) {
    // Transform categories
    const transformedCategories = categories.map(cat => ({
        ...cat,
        nameAr: cat.name_ar || cat.nameAr || cat.name,
        descriptionAr: cat.description_ar || cat.descriptionAr || cat.description,
    }));

    return (
        <MainLayout categories={categories} cartItemsCount={cartItemsCount}>
            {/* Hero Section Extension Slot */}
            <PluginSlot name="home.hero" pluginId="hero-banners" />

            {/* Categories Section */}
            {transformedCategories.length > 0 && (
                <CategoriesSection categories={transformedCategories} />
            )}

            {/* Combo Packages Dynamic Plugin Slot */}
            <PluginSlot name="home.sections.combos" pluginId="combo-packages" />

            <Separator />

            {/* Products Sections Dynamic Plugin Slot */}
            <PluginSlot
                name="home.sections.after"
                pluginId="catalog-products"
                fallback={
                    sections && sections.length > 0 ? (
                        <ProductsSection
                            sections={sections.map((s) => ({
                                id: s.id.toString(),
                                title: s.title,
                                titleAr: s.title_ar,
                                products: s.products.map((p) => ({
                                    ...p,
                                    descriptionAr: p.description,
                                })),
                                icon: 'star',
                            }))}
                        />
                    ) : null
                }
            />
        </MainLayout>
    );
}
