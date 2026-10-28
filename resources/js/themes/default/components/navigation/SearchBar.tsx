import React, { useState, useEffect } from "react";
import { useNavigate, useLocation, useSearchParams } from "react-router-dom";
import axios from "axios";
import { Product } from "@/types";
import { getEffectivePrice } from "@/lib/pricing";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { Search, X } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useFormatMoney } from "@/lib/money";

interface SearchBarProps {
    className?: string;
    autoFocus?: boolean;
    onClose?: () => void;
}

export function SearchBar({ className, autoFocus, onClose }: SearchBarProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams] = useSearchParams();

    const isMenuPage = location.pathname === "/menu";
    const urlSearch = isMenuPage ? searchParams.get("search") || "" : "";
    const [searchQuery, setSearchQuery] = useState(urlSearch);
    const [suggestions, setSuggestions] = useState<Product[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);

    // Keep search input synced with URL when on /menu (e.g. Back/Forward or filter reset)
    useEffect(() => {
        if (location.pathname === "/menu") {
            setSearchQuery(searchParams.get("search") || "");
        }
    }, [location.pathname, searchParams]);

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = searchQuery.trim();
        if (location.pathname === "/menu") {
            const nextParams = new URLSearchParams(searchParams);
            if (trimmed) {
                nextParams.set("search", trimmed);
            } else {
                nextParams.delete("search");
            }
            const qs = nextParams.toString();
            navigate(qs ? `/menu?${qs}` : "/menu");
        } else {
            if (trimmed) {
                navigate(`/menu?search=${encodeURIComponent(trimmed)}`);
            } else {
                navigate("/menu");
            }
        }
        onClose?.();
        setShowSuggestions(false);
    };

    const handleClear = () => {
        setSearchQuery("");
        if (location.pathname === "/menu") {
            const nextParams = new URLSearchParams(searchParams);
            nextParams.delete("search");
            const qs = nextParams.toString();
            navigate(qs ? `/menu?${qs}` : "/menu");
        }
    };

    const handleSearchChange = async (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const query = e.target.value;
        setSearchQuery(query);

        if (query.length >= 2) {
            try {
                const response = await axios.get("/api/catalog-products/suggestions", {
                    params: { query },
                });
                const resData = response.data;
                setSuggestions(Array.isArray(resData) ? resData : (resData.data || []));
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
        navigate(`/products/${product.id}`);
    };

    const placeholderText = isMenuPage
        ? t("searchProductsPlaceholder", "Search coffee, drinks, snacks...")
        : t("search", "Search");

    return (
        <div className={className}>
            <form onSubmit={handleSearch} className="relative w-full">
                <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors pointer-events-none" />
                <Input
                    data-testid="search-input"
                    type="search"
                    placeholder={placeholderText}
                    className="ps-9 pe-9 w-full transition-all focus-visible:ring-primary/20"
                    value={searchQuery}
                    onChange={handleSearchChange}
                    onFocus={() =>
                        suggestions.length > 0 && setShowSuggestions(true)
                    }
                    onBlur={() =>
                        setTimeout(() => setShowSuggestions(false), 300)
                    }
                    autoFocus={autoFocus}
                />
                {searchQuery && (
                    <button
                        type="button"
                        onClick={handleClear}
                        aria-label={t("clearAll", "Clear")}
                        className="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors p-1"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </form>
            {showSuggestions && suggestions.length > 0 && (
                <Card data-testid="search-suggestions-container" className="absolute top-full inset-x-0 mt-2 z-50 shadow-lg animate-in fade-in zoom-in-95 duration-200">
                    {suggestions.map((product) => (
                        <CardContent
                            key={product.id}
                            data-testid="search-suggestion-item"
                            className="p-2"
                            onClick={() => {
                                handleProductClick(product);
                            }}
                        >
                            <div className="flex items-center gap-3 p-2 hover:bg-accent rounded-md cursor-pointer transition-colors">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">
                                        {getText(product.name, product.name_ar)}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <p className="text-xs text-muted-foreground">
                                            {formatMoney(
                                                getEffectivePrice(product),
                                            )}
                                        </p>
                                        {product.category &&
                                            typeof product.category ===
                                                "object" && (
                                                <span className="text-[10px] text-muted-foreground bg-secondary px-1.5 py-0.5 rounded">
                                                    {getText(
                                                        product.category.name,
                                                        product.category
                                                            .name_ar,
                                                    )}
                                                </span>
                                            )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    ))}
                </Card>
            )}
        </div>
    );
}
