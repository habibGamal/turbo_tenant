import React, { useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";
import { Product } from "@/types";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { ImageWithFallback } from "@/components/ui/image";
import { Search } from "lucide-react";
import { useTranslation } from "react-i18next";

interface SearchBarProps {
    className?: string;
    autoFocus?: boolean;
    onClose?: () => void;
}

export function SearchBar({ className, autoFocus, onClose }: SearchBarProps) {
    const { t, i18n } = useTranslation();
    const [searchQuery, setSearchQuery] = useState("");
    const [suggestions, setSuggestions] = useState<Product[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency")}`;
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get("/menu", { search: searchQuery });
            onClose?.();
            setShowSuggestions(false);
        }
    };

    const handleSearchChange = async (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const query = e.target.value;
        setSearchQuery(query);

        if (query.length >= 2) {
            try {
                const response = await axios.get("/api/search/suggestions", {
                    params: { query },
                });
                setSuggestions(response.data);
                setShowSuggestions(true);
            } catch (error) {
                console.error("Failed to fetch suggestions:", error);
            }
        } else {
            setSuggestions([]);
            setShowSuggestions(false);
        }
    };

    const handleProductClick = (product: Product) => {
        setSearchQuery(getText(product.name, product.name_ar));
        setShowSuggestions(false);
        onClose?.();
        router.visit(`/products/${product.id}`);
    };

    return (
        <div className={className}>
            <form onSubmit={handleSearch} className="relative w-full">
                <Search className="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                <Input
                    type="search"
                    placeholder={t("search")}
                    className="ltr:pl-10 rtl:pr-10 w-full transition-all focus-visible:ring-primary/20"
                    value={searchQuery}
                    onChange={handleSearchChange}
                    onFocus={() =>
                        suggestions.length > 0 && setShowSuggestions(true)
                    }
                    onBlur={() =>
                        setTimeout(() => setShowSuggestions(false), 200)
                    }
                    autoFocus={autoFocus}
                />
            </form>
            {showSuggestions && suggestions.length > 0 && (
                <Card className="absolute top-full left-0 right-0 mt-2 z-50 shadow-lg animate-in fade-in zoom-in-95 duration-200">
                    <CardContent className="p-2">
                        {suggestions.map((product) => (
                            <div
                                key={product.id}
                                className="flex items-center gap-3 p-2 hover:bg-accent rounded-md cursor-pointer transition-colors"
                                onClick={() => handleProductClick(product)}
                            >
                                <ImageWithFallback
                                    src={product.image}
                                    alt={getText(product.name, product.name_ar)}
                                    className="w-10 h-10 object-cover rounded shrink-0"
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">
                                        {getText(product.name, product.name_ar)}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <p className="text-xs text-muted-foreground">
                                            {formatPrice(
                                                product.price_after_discount ??
                                                    product.base_price ??
                                                    product.price,
                                            )}
                                        </p>
                                        {product.category &&
                                            typeof product.category ===
                                                "object" && (
                                                <span className="text-[10px] text-muted-foreground bg-secondary px-1.5 py-0.5 rounded">
                                                    {getText(
                                                        product.category.name,
                                                        product.category.name_ar,
                                                    )}
                                                </span>
                                            )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
