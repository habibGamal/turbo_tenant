import React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { ScrollText, Pizza, Coffee, Salad, IceCream, Soup } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ImageWithFallback } from '@/components/ui/image';

interface Category {
    id: number;
    name: string;
    name_ar?: string;
    slug: string;
    description?: string;
    descriptionAr?: string;
    image?: string;
    icon?: string;
    productsCount?: number;
}

interface CategoriesSectionProps {
    categories?: Category[];
    title?: string;
    titleAr?: string;
    subtitle?: string;
    subtitleAr?: string;
}

export default function CategoriesSection({
    categories,
    title,
    titleAr,
    subtitle,
    subtitleAr,
}: CategoriesSectionProps) {
    const { t, i18n } = useTranslation();

    const defaultCategories: Category[] = [
        { id: 1, name: t('categoryPizza'), name_ar: t('categoryPizza'), slug: 'pizza', icon: 'pizza', productsCount: 24, image: '/images/categories/pizza.jpg' },
        { id: 2, name: t('categoryBurgers'), name_ar: t('categoryBurgers'), slug: 'burgers', icon: 'utensils', productsCount: 18, image: '/images/categories/burger.jpg' },
        { id: 3, name: t('categorySalads'), name_ar: t('categorySalads'), slug: 'salads', icon: 'salad', productsCount: 15, image: '/images/categories/salad.jpg' },
        { id: 4, name: t('categoryDesserts'), name_ar: t('categoryDesserts'), slug: 'desserts', icon: 'icecream', productsCount: 22, image: '/images/categories/dessert.jpg' },
        { id: 5, name: t('categoryDrinks'), name_ar: t('categoryDrinks'), slug: 'drinks', icon: 'coffee', productsCount: 30, image: '/images/categories/drink.jpg' },
        { id: 6, name: t('categorySoups'), name_ar: t('categorySoups'), slug: 'soups', icon: 'soup', productsCount: 12, image: '/images/categories/soup.jpg' },
    ];

    const categoriesToUse = categories || defaultCategories;
    const titleToUse = title || t('browseCategories');
    const titleArToUse = titleAr || t('browseCategories');
    const subtitleToUse = subtitle || t('exploreDiverseMenu');
    const subtitleArToUse = subtitleAr || t('exploreDiverseMenu');

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    return (
        <section className="py-12 md:py-20 bg-muted/30">
            <div className="container mx-auto px-4">
                {/* Section Header */}
                <div className="text-center mb-10 md:mb-16 space-y-3">
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight">
                        {getText(titleToUse, titleArToUse)}
                    </h2>
                    <p className="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto">
                        {getText(subtitleToUse, subtitleArToUse)}
                    </p>
                </div>

                {/* Categories Grid */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                    {categoriesToUse.map((category) => (
                        <Link key={category.id} href={`/menu?category[]=${category.name}`} className="group block h-full">
                            <Card className="h-full overflow-hidden border-0 shadow-md transition-all duration-300 hover:shadow-xl hover:-translate-y-1 bg-card">
                                <CardContent className="p-0 h-full flex flex-col">
                                    {/* Image Container */}
                                    <div className="relative aspect-square overflow-hidden">
                                        <ImageWithFallback
                                            src={category.image}
                                            alt={getText(category.name, category.name_ar)}
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                        />
                                        {/* Overlay Gradient */}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60 group-hover:opacity-40 transition-opacity duration-300" />

                                        {/* Products Count Badge */}
                                        {category.productsCount !== undefined && (
                                            <div className="absolute top-2 right-2 bg-white/90 backdrop-blur-sm text-foreground text-xs font-bold px-2 py-1 rounded-full shadow-sm">
                                                {category.productsCount}
                                            </div>
                                        )}
                                    </div>

                                    {/* Category Info */}
                                    <div className="p-4 text-center flex-grow flex flex-col justify-center bg-card z-10 relative">
                                        <h3 className="font-bold text-lg group-hover:text-primary transition-colors line-clamp-1">
                                            {getText(category.name, category.name_ar)}
                                        </h3>
                                        {category.description && (
                                            <p className="text-xs text-muted-foreground line-clamp-2 mt-1 hidden md:block">
                                                {getText(category.description, category.descriptionAr)}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                {/* View All Link */}
                <div className="text-center mt-12">
                    <Link href="/menu">
                        <span className="inline-flex items-center justify-center rounded-full bg-primary px-8 py-3 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50">
                            {t('viewAll')}
                        </span>
                    </Link>
                </div>
            </div>
        </section>
    );
}
