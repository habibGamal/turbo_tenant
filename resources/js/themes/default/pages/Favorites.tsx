import { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { useFavorites } from "@/hooks/useFavorites";
import ProductCard from "@/themes/default/components/ProductCard";
import { useTranslation } from "react-i18next";
import axios from "axios";
import { Loader2, HeartOff } from "lucide-react";

interface Product {
    id: number;
    name: string;
    description: string;
    image: string;
    price: number;
    base_price: number;
    price_after_discount: number | undefined;
    category: string;
    rating: number;
}

export default function Favorites() {
    const { t } = useTranslation();
    const { favorites } = useFavorites();
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchFavorites = async () => {
            if (favorites.length === 0) {
                setProducts([]);
                setLoading(false);
                return;
            }

            try {
                const response = await axios.get(route("api.products.by-ids"), {
                    params: { ids: favorites },
                });
                setProducts(response.data);
            } catch (error) {
                console.error("Failed to fetch favorite products", error);
            } finally {
                setLoading(false);
            }
        };

        fetchFavorites();
    }, [favorites]);

    return (
        <MainLayout>
            <Head title={t("myFavorites")} />

            <div className="container mx-auto py-10 px-4 md:px-6">
                <h1 className="text-3xl font-bold mb-8">{t("myFavorites")}</h1>

                {loading ? (
                    <div className="flex justify-center items-center h-64">
                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                ) : products.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center h-64 text-center">
                        <HeartOff className="h-16 w-16 text-muted-foreground mb-4" />
                        <h2 className="text-xl font-semibold mb-2">
                            {t("noFavoritesYet")}
                        </h2>
                        <p className="text-muted-foreground">
                            {t("startAddingFavorites")}
                        </p>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
