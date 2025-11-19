import React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Percent, Gift, Clock, Star, ShoppingBag, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface PackageProduct {
    id: number;
    name: string;
    nameAr?: string;
    quantity: number;
}

interface Package {
    id: number;
    name: string;
    nameAr?: string;
    description: string;
    descriptionAr?: string;
    price: number;
    originalPrice?: number;
    discountPercentage?: number;
    badge?: string;
    badgeAr?: string;
    icon: 'percent' | 'gift' | 'clock' | 'star';
    gradient: string;
    isFeatured?: boolean;
    products?: PackageProduct[];
}

interface PromotionalSectionProps {
    packages?: Package[];
}

const defaultPackages: Package[] = [
    {
        id: 1,
        name: 'Family Feast Package',
        nameAr: 'باقة العائلة',
        description: 'Perfect for family gatherings with a variety of dishes',
        descriptionAr: 'مثالية للتجمعات العائلية مع مجموعة متنوعة من الأطباق',
        price: 49.99,
        originalPrice: 65.99,
        discountPercentage: 25,
        badge: 'Best Value',
        badgeAr: 'أفضل قيمة',
        icon: 'gift',
        gradient: 'from-orange-500/10 via-red-500/5 to-pink-500/10',
        isFeatured: true,
        products: [
            { id: 1, name: '2 Large Pizzas', nameAr: '2 بيتزا كبيرة', quantity: 2 },
            { id: 2, name: '1 Family Salad', nameAr: '1 سلطة عائلية', quantity: 1 },
            { id: 3, name: '4 Soft Drinks', nameAr: '4 مشروبات غازية', quantity: 4 },
        ],
    },
    {
        id: 2,
        name: 'Lunch Special',
        nameAr: 'عرض الغداء',
        description: 'Quick and delicious lunch combo for busy days',
        descriptionAr: 'كومبو غداء سريع ولذيذ للأيام المزدحمة',
        price: 15.99,
        originalPrice: 22.99,
        discountPercentage: 30,
        badge: 'Popular',
        badgeAr: 'شائع',
        icon: 'clock',
        gradient: 'from-blue-500/10 via-cyan-500/5 to-teal-500/10',
        products: [
            { id: 4, name: 'Main Dish', nameAr: 'طبق رئيسي', quantity: 1 },
            { id: 5, name: 'Side Salad', nameAr: 'سلطة جانبية', quantity: 1 },
            { id: 6, name: 'Drink', nameAr: 'مشروب', quantity: 1 },
        ],
    },
    {
        id: 3,
        name: 'Weekend Brunch',
        nameAr: 'برانش نهاية الأسبوع',
        description: 'Start your weekend right with this delightful brunch package',
        descriptionAr: 'ابدأ عطلة نهاية الأسبوع بشكل صحيح مع هذه الباقة الرائعة',
        price: 29.99,
        originalPrice: 39.99,
        discountPercentage: 25,
        badge: 'Weekend Only',
        badgeAr: 'نهاية الأسبوع فقط',
        icon: 'star',
        gradient: 'from-purple-500/10 via-violet-500/5 to-fuchsia-500/10',
        products: [
            { id: 7, name: 'Breakfast Platter', nameAr: 'طبق إفطار', quantity: 1 },
            { id: 8, name: 'Fresh Juice', nameAr: 'عصير طازج', quantity: 2 },
            { id: 9, name: 'Pastries', nameAr: 'معجنات', quantity: 4 },
        ],
    },
];

export default function PromotionalSection({ packages = defaultPackages }: PromotionalSectionProps) {
    const { t, i18n } = useTranslation();

    const getIcon = (iconName: string) => {
        const iconClass = "h-6 w-6";
        switch (iconName) {
            case 'percent':
                return <Percent className={iconClass} />;
            case 'gift':
                return <Gift className={iconClass} />;
            case 'clock':
                return <Clock className={iconClass} />;
            case 'star':
                return <Star className={iconClass} />;
            default:
                return <Gift className={iconClass} />;
        }
    };

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    return (
        <section className="py-12 md:py-20">
            <div className="container mx-auto px-4">
                {/* Section Header */}
                <div className="text-center mb-10 md:mb-14 space-y-3">
                    <Badge variant="secondary" className="mb-2 rounded-full">
                        <ShoppingBag className="h-3 w-3 ltr:mr-1 rtl:ml-1" />
                        {t('specialPackages')}
                    </Badge>
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight">
                        {t('exclusivePackagesSaveMore')}
                    </h2>
                    <p className="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto">
                        {t('chooseCuratedPackages')}
                    </p>
                </div>

                {/* Packages Grid */}
                <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {packages.map((pkg) => (
                        <Card
                            key={pkg.id}
                            className={`group overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-2 rounded-2xl ${
                                pkg.isFeatured ? 'md:col-span-2 lg:col-span-1 ring-2 ring-primary/20' : ''
                            }`}
                        >
                            <div className={`bg-gradient-to-br ${pkg.gradient} p-6 md:p-8 relative overflow-hidden rounded-t-2xl`}>
                                {/* Decorative Elements */}
                                <div className="absolute top-0 ltr:right-0 rtl:left-0 w-32 h-32 bg-white/5 rounded-full blur-2xl" />
                                <div className="absolute bottom-0 ltr:left-0 rtl:right-0 w-40 h-40 bg-white/5 rounded-full blur-3xl" />

                                <CardContent className="p-0 relative z-10">
                                    {/* Header */}
                                    <div className="flex items-start justify-between mb-6">
                                        {pkg.badge && (
                                            <Badge variant="secondary" className="backdrop-blur-sm bg-background/80 rounded-full">
                                                {getText(pkg.badge, pkg.badgeAr)}
                                            </Badge>
                                        )}
                                        <div className="p-3 rounded-2xl bg-primary/10 backdrop-blur-sm group-hover:scale-110 transition-transform ltr:ml-auto rtl:mr-auto">
                                            <div className="text-primary">
                                                {getIcon(pkg.icon)}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Package Name */}
                                    <h3 className="text-2xl md:text-3xl font-bold mb-2">
                                        {getText(pkg.name, pkg.nameAr)}
                                    </h3>

                                    <p className="text-muted-foreground mb-6">
                                        {getText(pkg.description, pkg.descriptionAr)}
                                    </p>

                                    {/* Products List */}
                                    {pkg.products && pkg.products.length > 0 && (
                                        <div className="space-y-2 mb-6">
                                            {pkg.products.map((product) => (
                                                <div key={product.id} className="flex items-center gap-2 text-sm">
                                                    <Check className="h-4 w-4 text-primary shrink-0" />
                                                    <span className="text-foreground/80">
                                                        {product.quantity > 1 && `${product.quantity}x `}
                                                        {getText(product.name, product.nameAr)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Price */}
                                    <div className="flex items-end gap-3 mb-6">
                                        <div className="text-4xl md:text-5xl font-bold text-primary">
                                            ${pkg.price.toFixed(2)}
                                        </div>
                                        {pkg.originalPrice && (
                                            <div className="flex flex-col items-start">
                                                <span className="text-sm line-through text-muted-foreground">
                                                    ${pkg.originalPrice.toFixed(2)}
                                                </span>
                                                {pkg.discountPercentage && (
                                                    <Badge variant="destructive" className="text-xs rounded-full">
                                                        -{pkg.discountPercentage}%
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {/* CTA Button */}
                                    <Link href={`/packages/${pkg.id}`}>
                                        <Button
                                            className="w-full group-hover:scale-105 transition-transform shadow-lg rounded-xl"
                                            size="lg"
                                        >
                                            <ShoppingBag className="h-4 w-4 ltr:mr-2 rtl:ml-2" />
                                            {t('orderPackage')}
                                        </Button>
                                    </Link>
                                </CardContent>
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Bottom CTA */}
                <div className="mt-12 text-center">
                    <p className="text-muted-foreground mb-4">
                        {t('lookingForCustomPackages')}
                    </p>
                    <Link href="/contact">
                        <Button variant="outline" size="lg" className="rounded-xl">
                            {t('contactUs')}
                        </Button>
                    </Link>
                </div>
            </div>
        </section>
    );
}
