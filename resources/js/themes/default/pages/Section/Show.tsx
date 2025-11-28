import React, { useState } from 'react';
import { Head, Link, usePage, WhenVisible } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Product } from '@/types';
import ProductCard from '@/themes/default/components/ProductCard';
import { addToCart } from '@/utils/cartUtils';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';

interface Section {
    id: number;
    title: string;
    titleAr?: string;
    subtitle?: string;
    subtitleAr?: string;
}

interface Props {
    section: Section;
    products: {
        data: Product[];
        next_page_url: string | null;
    };
}

export default function Show({ section, products: initialProducts }: Props) {
    const { t, i18n } = useTranslation();
    const [products, setProducts] = useState<Product[]>(initialProducts.data);
    const [nextPageUrl, setNextPageUrl] = useState<string | null>(initialProducts.next_page_url);
    const [addingToCart, setAddingToCart] = useState<{ [key: number]: boolean }>({});

    React.useEffect(() => {
        if (initialProducts.data.length > 0) {
            setProducts(prev => {
                const newItems = initialProducts.data;
                const lastItem = prev[prev.length - 1];
                const firstNewItem = newItems[0];

                if (lastItem && firstNewItem && lastItem.id === firstNewItem.id) {
                    return prev;
                }

                // Check if we already have these items to avoid duplicates
                const existingIds = new Set(prev.map(p => p.id));
                const uniqueNewItems = newItems.filter(p => !existingIds.has(p.id));

                if (uniqueNewItems.length === 0) return prev;

                return [...prev, ...uniqueNewItems];
            });
            setNextPageUrl(initialProducts.next_page_url);
        }
    }, [initialProducts]);

    const handleAddToCart = async (e: React.MouseEvent, productId: number) => {
        e.preventDefault();
        e.stopPropagation();

        setAddingToCart(prev => ({ ...prev, [productId]: true }));

        await addToCart(
            {
                product_id: productId,
                variant_id: null,
                quantity: '1',
                extras: [],
            },
            {
                onFinally: () => {
                    setAddingToCart(prev => ({ ...prev, [productId]: false }));
                },
            }
        );
    };

    return (
        <>
            <Head title={i18n.language === 'ar' && section.titleAr ? section.titleAr : section.title} />

            <div className="container mx-auto px-4 py-8">
                <div className="mb-8">
                    <Link href={route('menu')}>
                        <Button variant="ghost" className="gap-2 pl-0 hover:pl-2 transition-all">
                            <ArrowLeft className="h-4 w-4" />
                            {t('Back to Menu')}
                        </Button>
                    </Link>
                </div>

                <div className="mb-10">
                    <h1 className="text-3xl md:text-4xl font-bold tracking-tight mb-2">
                        {i18n.language === 'ar' && section.titleAr ? section.titleAr : section.title}
                    </h1>
                    {(section.subtitle || section.subtitleAr) && (
                        <p className="text-lg text-muted-foreground">
                            {i18n.language === 'ar' && section.subtitleAr ? section.subtitleAr : section.subtitle}
                        </p>
                    )}
                </div>

                <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    {products.map((product) => (
                        <ProductCard
                            key={product.id}
                            product={product}
                            onAddToCart={handleAddToCart}
                            addingToCart={addingToCart[product.id]}
                            showFavorite={true}
                        />
                    ))}
                </div>

                {nextPageUrl && (
                    <WhenVisible
                        always
                        params={{
                            data: {
                                cursor: new URL(nextPageUrl).searchParams.get('cursor'),
                            },
                            only: ['products'],
                            preserveUrl: true,
                        }}
                        fallback={
                            <div className="py-8 text-center text-muted-foreground">
                                {t('Loading more products...')}
                            </div>
                        }
                    >
                        <div className="py-8 text-center text-muted-foreground">
                            {t('Loading more products...')}
                        </div>
                    </WhenVisible>
                )}
            </div>
        </>
    );
}
