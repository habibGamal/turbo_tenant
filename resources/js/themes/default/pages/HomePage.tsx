import React from 'react';
import Navigation from '@/themes/default/components/Navigation';
import HeroSlider from '@/themes/default/components/HeroSlider';
import CategoriesSection from '@/themes/default/components/CategoriesSection';
import PromotionalSection from '@/themes/default/components/PromotionalSection';
import ProductsSection from '@/themes/default/components/ProductsSection';
import Footer from '@/themes/default/components/Footer';
import { Separator } from '@/components/ui/separator';

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    image?: string;
    category: string;
    rating?: number;
    is_featured?: boolean;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    image?: string;
}

interface HomePageProps {
    categories?: Category[];
    featuredProducts?: Product[];
    popularProducts?: Product[];
    cartItemsCount?: number;
}

export default function HomePage({
    categories = [],
    featuredProducts = [],
    popularProducts = [],
    cartItemsCount = 0
}: HomePageProps) {
    // Transform products for ProductsSection
    const productSections = [
        {
            id: 'featured',
            title: 'Featured Dishes',
            titleAr: 'أطباق مميزة',
            subtitle: "Chef's special recommendations",
            subtitleAr: 'توصيات الشيف الخاصة',
            icon: 'star' as const,
            products: featuredProducts.map(p => ({
                ...p,
                nameAr: p.name, // TODO: Add actual Arabic translations
                descriptionAr: p.description,
                categoryAr: p.category,
            })),
        },
        {
            id: 'popular',
            title: 'Customer Favorites',
            titleAr: 'المفضلة لدى العملاء',
            subtitle: 'Most loved dishes by our customers',
            subtitleAr: 'الأطباق الأكثر حباً من عملائنا',
            icon: 'trending' as const,
            products: popularProducts.map(p => ({
                ...p,
                nameAr: p.name, // TODO: Add actual Arabic translations
                descriptionAr: p.description,
                categoryAr: p.category,
                isTrending: true,
            })),
        },
    ].filter(section => section.products.length > 0);

    // Transform categories
    const transformedCategories = categories.map(cat => ({
        ...cat,
        nameAr: cat.name, // TODO: Add actual Arabic translations
        descriptionAr: cat.description,
    }));

    return (
        <div className="min-h-screen bg-background">
            {/* Navigation */}
            <Navigation categories={categories} cartItemsCount={cartItemsCount} />

            {/* Main Content */}
            <main className="pb-0 md:pb-0">
                {/* Hero Section with Slider */}
                <HeroSlider />

                {/* Categories Section */}
                {transformedCategories.length > 0 && (
                    <CategoriesSection categories={transformedCategories} />
                )}

                {/* Promotional Section */}
                <PromotionalSection />

                <Separator />

                {/* Products Sections */}
                {productSections.length > 0 && (
                    <ProductsSection sections={productSections} />
                )}
            </main>

            {/* Footer */}
            <Footer />
        </div>
    );
}

