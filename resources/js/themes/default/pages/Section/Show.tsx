import React, { useState } from 'react';
import { Head, Link, usePage, WhenVisible } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Product } from '@/types';
import ProductCard from '@/themes/default/plugins/catalog-products/components/ProductCard';
import ProductOptionsModal from '@/themes/default/plugins/catalog-products/components/ProductOptionsModal';
import { useAddToCart } from '@/plugins/cart-engine/api';
import { apiClient } from '@/core/api/client';
import { toast } from 'sonner';
import { ProductCardSkeleton } from '@/components/ui/page-skeletons';
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
    const [modalProduct, setModalProduct] = useState<any | null>(null);
    const [isOptionsModalOpen, setIsOptionsModalOpen] = useState(false);
    const { mutate: addToCart, isPending } = useAddToCart();

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

        const product = products.find(p => p.id === productId);
        if (!product) return;

        const hasOptions = (product as any).has_options !== false && Boolean(
            (product as any).has_options ||
            (product as any).has_variants ||
            (product as any).has_extras ||
            product.sell_by_weight ||
            (product.variants && product.variants.length > 0) ||
            (product as any).extra_option_id ||
            (product as any).extra_option ||
            (product as any).extraOption
        );

        if (!hasOptions) {
            addToCart(
                { product_id: product.id, quantity: '1' },
                {
                    onError: () => {
                        toast.error(t('failedToAddToCart', 'Failed to add to cart'));
                    },
                }
            );
        } else {
            try {
                const response = await apiClient.get<any>(`/api/catalog-products/${product.id}`);
                const full = response.data.product || response.data.data;
                setModalProduct(full);
                setIsOptionsModalOpen(true);
            } catch (error) {
                console.error('Failed to load product details', error);
            }
        }
    };

    return (
        <>
            <Head title={i18n.language === 'ar' && section.titleAr ? section.titleAr : section.title} />

            <div className="container mx-auto px-4 py-8">
                <div className="mb-8">
                    <Link href={route('menu')}>
                        <Button variant="ghost" className="gap-2 ps-0 hover:ps-2 transition-all">
                            <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
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
                            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                                {[...Array(4)].map((_, i) => (
                                    <ProductCardSkeleton key={i} />
                                ))}
                            </div>
                        }
                    >
                        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                            {[...Array(4)].map((_, i) => (
                                <ProductCardSkeleton key={i} />
                            ))}
                        </div>
                    </WhenVisible>
                )}

                {modalProduct && (
                    <ProductOptionsModal
                        product={modalProduct}
                        isOpen={isOptionsModalOpen}
                        onClose={() => {
                            setIsOptionsModalOpen(false);
                            setModalProduct(null);
                        }}
                    />
                )}
            </div>
        </>
    );
}
