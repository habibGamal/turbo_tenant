import React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { UtensilsCrossed, Pizza, Coffee, Salad, IceCream, Soup } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Category {
    id: number;
    name: string;
    nameAr?: string;
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
        { id: 1, name: t('categoryPizza'), nameAr: t('categoryPizza'), slug: 'pizza', icon: 'pizza', productsCount: 24 },
        { id: 2, name: t('categoryBurgers'), nameAr: t('categoryBurgers'), slug: 'burgers', icon: 'utensils', productsCount: 18 },
        { id: 3, name: t('categorySalads'), nameAr: t('categorySalads'), slug: 'salads', icon: 'salad', productsCount: 15 },
        { id: 4, name: t('categoryDesserts'), nameAr: t('categoryDesserts'), slug: 'desserts', icon: 'icecream', productsCount: 22 },
        { id: 5, name: t('categoryDrinks'), nameAr: t('categoryDrinks'), slug: 'drinks', icon: 'coffee', productsCount: 30 },
        { id: 6, name: t('categorySoups'), nameAr: t('categorySoups'), slug: 'soups', icon: 'soup', productsCount: 12 },
    ];

    const categoriesToUse = categories || defaultCategories;
    const titleToUse = title || t('browseCategories');
    const titleArToUse = titleAr || t('browseCategories');
    const subtitleToUse = subtitle || t('exploreDiverseMenu');
    const subtitleArToUse = subtitleAr || t('exploreDiverseMenu');

    const getIcon = (iconName?: string) => {
        const iconClass = "h-8 w-8 md:h-10 md:w-10";
        switch (iconName) {
            case 'pizza':
                return <Pizza className={iconClass} />;
            case 'salad':
                return <Salad className={iconClass} />;
            case 'icecream':
                return <IceCream className={iconClass} />;
            case 'coffee':
                return <Coffee className={iconClass} />;
            case 'soup':
                return <Soup className={iconClass} />;
            case 'utensils':
            default:
                return <UtensilsCrossed className={iconClass} />;
        }
    };

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
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 md:gap-6">
                    {categoriesToUse.map((category) => (
                        <Link key={category.id} href={`/menu/${category.slug}`}>
                            <Card className="group cursor-pointer overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 border-2 hover:border-primary/50">
                                <CardContent className="p-6 md:p-8 flex flex-col items-center text-center space-y-3">
                                    {/* Icon Container */}
                                    <div className="relative">
                                        <div className="p-4 md:p-5 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 group-hover:from-primary/20 group-hover:to-primary/10 transition-all duration-300 group-hover:scale-110">
                                            <div className="text-primary">
                                                {getIcon(category.icon)}
                                            </div>
                                        </div>
                                        {category.productsCount && (
                                            <div className="absolute -top-1 -right-1 bg-primary text-primary-foreground text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center shadow-lg">
                                                {category.productsCount}
                                            </div>
                                        )}
                                    </div>

                                    {/* Category Name */}
                                    <div className="space-y-1">
                                        <h3 className="font-semibold text-base md:text-lg group-hover:text-primary transition-colors">
                                            {getText(category.name, category.nameAr)}
                                        </h3>
                                        {category.description && (
                                            <p className="text-xs text-muted-foreground line-clamp-2 hidden md:block">
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
                <div className="text-center mt-10">
                    <Link href="/menu">
                        <span className="text-primary hover:text-primary/80 font-semibold inline-flex items-center gap-2 group">
                            {t('View All')}
                            <span className="group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-transform">→</span>
                        </span>
                    </Link>
                </div>
            </div>
        </section>
    );
}
