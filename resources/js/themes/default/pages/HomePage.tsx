import React from 'react';
import MainLayout from '@/themes/default/layouts/MainLayout';
import HeroSlider from '@/themes/default/components/HeroSlider';
import CategoriesSection from '@/themes/default/components/CategoriesSection';
import PromotionalSection from '@/themes/default/components/PromotionalSection';
import ProductsSection from '@/themes/default/components/ProductsSection';
import { Separator } from '@/components/ui/separator';



interface Product {
    id: number;
    name: string;
    name_ar?: string;
    description: string;
    price: number;
    image?: string;
    category: string;
    rating?: number;
    is_featured?: boolean;
    // Add other fields as needed
    base_price?: number;
    price_after_discount?: number;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    image?: string;
}

interface Section {
    id: number;
    title: string;
    products: Product[];
    // Add other fields from Section model
}



interface HomePageProps {
    categories?: Category[];
    sections: Section[];
    cartItemsCount?: number;
    heroSlides?: any[]; // TODO: Define strict type
}

export default function HomePage({
    categories = [],
    sections,
    cartItemsCount = 0,
    heroSlides = []
}: HomePageProps) {


    // Transform categories
    const transformedCategories = categories.map(cat => ({
        ...cat,
        nameAr: cat.name, // TODO: Add actual Arabic translations
        descriptionAr: cat.description,
    }));

    return (
        <MainLayout categories={categories} cartItemsCount={cartItemsCount}>
            {/* Hero Section with Slider */}
            <HeroSlider slides={heroSlides} />

            {/* Categories Section */}
            {transformedCategories.length > 0 && (
                <CategoriesSection categories={transformedCategories} />
            )}

            {/* Promotional Section */}
            {/* <PromotionalSection /> */}

            <Separator />

            {/* Products Sections */}
            {sections.length > 0 && (
                <ProductsSection sections={sections.map(s => ({
                    id: s.id.toString(),
                    title: s.title,
                    products: s.products.map(p => ({
                        ...p,
                        descriptionAr: p.description,
                        // Map product fields if necessary to match ProductsSection expectations
                        // For now assuming they match or are compatible
                        // category: typeof p.category === 'string' ? p.category : (p.category as any)?.name,
                        // categoryAr: typeof p.category === 'string' ? p.category : (p.category as any)?.name,
                    })),
                    icon: 'star' // Default icon, or map based on title
                }))} />
            )}


        </MainLayout>
    );
}

