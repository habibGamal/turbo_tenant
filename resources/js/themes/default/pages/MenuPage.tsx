import React, { useState, useEffect, useRef } from "react";
import axios from "axios";
import { router, InfiniteScroll } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import ProductCard from "@/themes/default/components/ProductCard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ImageWithFallback } from "@/components/ui/image";
import { Checkbox } from "@/components/ui/checkbox";
import { Search, Filter, X, ChevronDown } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";

import { MenuPageProps, Product } from "@/types";

export default function MenuPage({
    products: initialProducts,
    categories,
    filters: initialFilters,
    cartItemsCount = 0,
    searchSuggestions: initialSearchSuggestions = [],
}: MenuPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    // Get URL params
    const getUrlParams = () => {
        const params = new URLSearchParams(window.location.search);
        return {
            search: params.get("search") || "",
            category: [...params.entries()]
                .filter(([key]) => key.startsWith("category"))
                .map(([, value]) => value),
            min_price: params.get("min_price") || "",
            max_price: params.get("max_price") || "",
            sort_by: params.get("sort_by") || "name",
            sort_order: params.get("sort_order") || "asc",
        };
    };

    const urlParams = getUrlParams();

    const [searchQuery, setSearchQuery] = useState(urlParams.search);
    const [searchSuggestions, setSearchSuggestions] = useState<Product[]>(
        initialSearchSuggestions
    );
    const [showSuggestions, setShowSuggestions] = useState(false);
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    const [selectedCategories, setSelectedCategories] = useState<string[]>(
        urlParams.category
    );
    const [minPrice, setMinPrice] = useState<string>(urlParams.min_price);
    const [maxPrice, setMaxPrice] = useState<string>(urlParams.max_price);
    const [sortBy, setSortBy] = useState(urlParams.sort_by);
    const [sortOrder, setSortOrder] = useState(urlParams.sort_order);
    const [filterOpen, setFilterOpen] = useState(false);
    console.log("selectedCategories:", selectedCategories);

    const syncStateWithUrl = () => {
        const params = getUrlParams();
        console.log("Syncing state with URL params:", params);
        setSearchQuery(params.search);
        setSelectedCategories(params.category);
        setMinPrice(params.min_price);
        setMaxPrice(params.max_price);
        setSortBy(params.sort_by);
        setSortOrder(params.sort_order);
    };

    // Update suggestions when prop changes
    useEffect(() => {
        if (initialSearchSuggestions && initialSearchSuggestions.length > 0) {
            setSearchSuggestions(initialSearchSuggestions);
        }
    }, [initialSearchSuggestions]);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchQuery(value);

        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        if (value.length >= 2) {
            searchTimeoutRef.current = setTimeout(() => {
                axios
                    .get("/api/search/suggestions", {
                        params: { query: value },
                    })
                    .then((response) => {
                        setSearchSuggestions(response.data);
                        setShowSuggestions(true);
                    })
                    .catch((error) => {
                        console.error("Error fetching suggestions:", error);
                    });
            }, 300);
        } else {
            setSearchSuggestions([]);
            setShowSuggestions(false);
        }
    };

    const handleCategoryToggle = (categoryName: string) => {
        setSelectedCategories((prev) =>
            prev.includes(categoryName)
                ? prev.filter((c) => c !== categoryName)
                : [...prev, categoryName]
        );
    };

    const applyFilters = (
        overrides: {
            categoryOverride?: string[];
            minPriceOverride?: string;
            maxPriceOverride?: string;
        } = {}
    ) => {
        const categoriesToUse =
            overrides.categoryOverride !== undefined
                ? overrides.categoryOverride
                : selectedCategories;

        const minPriceToUse =
            overrides.minPriceOverride !== undefined
                ? overrides.minPriceOverride
                : minPrice;

        const maxPriceToUse =
            overrides.maxPriceOverride !== undefined
                ? overrides.maxPriceOverride
                : maxPrice;

        const minPriceNum = minPriceToUse
            ? parseFloat(minPriceToUse)
            : undefined;
        const maxPriceNum = maxPriceToUse
            ? parseFloat(maxPriceToUse)
            : undefined;
        router.get(
            "/menu",
            {
                search: searchQuery,
                category:
                    categoriesToUse.length > 0 ? categoriesToUse : undefined,
                min_price: minPriceNum,
                max_price: maxPriceNum,
                sort_by: sortBy,
                sort_order: sortOrder,
            },
            {
                preserveScroll: false,
                onSuccess: () => {
                    setFilterOpen(false);
                    syncStateWithUrl();
                },
                reset: ["products"],
            }
        );
    };

    const resetFilters = () => {
        router.get(
            "/menu",
            {},
            {
                preserveScroll: false,
                onSuccess: () => {
                    setFilterOpen(false);
                },
                reset: ["products"],
            }
        );
    };

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency")}`;
    };

    // Helper to get display price
    const getDisplayPrice = (product: Product) => {
        return product.price_after_discount ?? product.base_price ?? product.price;
    };

    const filterPanel = (
        <div className="space-y-6">
            {/* Categories */}
            <div className="space-y-3">
                <label className="text-sm font-medium">{t("categories")}</label>
                <div className="space-y-2 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                    {categories.map((category) => (
                        <div
                            key={category.id}
                            className="flex items-center space-x-3 p-2 rounded-lg hover:bg-accent/50 transition-colors"
                        >
                            <Checkbox
                                id={`category-${category.id}`}
                                checked={selectedCategories.includes(
                                    category.name
                                )}
                                onCheckedChange={() =>
                                    handleCategoryToggle(category.name)
                                }
                                className="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                            />
                            <label
                                htmlFor={`category-${category.id}`}
                                className="text-sm font-medium cursor-pointer flex-1 leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                            >
                                {category.name}
                            </label>
                        </div>
                    ))}
                </div>
            </div>

            {/* Price Range */}
            <div className="space-y-3">
                <label className="text-sm font-medium">{t("priceRange")}</label>
                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <Input
                            type="number"
                            placeholder={t("min")}
                            value={minPrice}
                            onChange={(e) => setMinPrice(e.target.value)}
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div>
                        <Input
                            type="number"
                            placeholder={t("max")}
                            value={maxPrice}
                            onChange={(e) => setMaxPrice(e.target.value)}
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>
            </div>

            {/* Sort */}
            <div className="space-y-2">
                <label className="text-sm font-medium">{t("sortBy")}</label>
                <div className="space-y-2">
                    <Select value={sortBy} onValueChange={setSortBy}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("sortBy")} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="name">{t("name")}</SelectItem>
                            <SelectItem value="base_price">
                                {t("price")}
                            </SelectItem>
                            <SelectItem value="created_at">
                                {t("newest")}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={sortOrder} onValueChange={setSortOrder}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("order")} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="asc">
                                {t("ascending")}
                            </SelectItem>
                            <SelectItem value="desc">
                                {t("descending")}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {/* Action Buttons */}
            <div className="flex gap-3 pt-6 border-t">
                <Button onClick={() => applyFilters()} className="flex-1 shadow-sm hover:shadow-md transition-all">
                    {t("applyFilters")}
                </Button>
                <Button onClick={resetFilters} variant="outline" className="hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50 transition-colors">
                    {t("reset")}
                </Button>
            </div>
        </div>
    );

    return (
        <MainLayout
            categories={categories}
            cartItemsCount={cartItemsCount}
        >
            <div className="container mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-3xl md:text-4xl font-bold">
                                {t("menu")}
                            </h1>
                            <p className="text-muted-foreground mt-2">
                                {t("browseOurDeliciousMenu")}
                            </p>
                        </div>

                        {/* Mobile Filter Button */}
                        <Sheet
                            open={filterOpen}
                            onOpenChange={setFilterOpen}
                        >
                            <SheetTrigger asChild className="lg:hidden">
                                <Button variant="outline" size="icon">
                                    <Filter className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side={isRTL ? "left" : "right"}>
                                <h2 className="text-lg font-semibold mb-6">
                                    {t("filters")}
                                </h2 >
                                {filterPanel}
                            </SheetContent>
                        </Sheet>
                    </div>

                    {/* Search Bar with Suggestions */}
                    <div className="relative max-w-2xl mx-auto">
                        <div className="relative group">
                            <Search className="absolute ltr:left-4 rtl:right-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground group-focus-within:text-primary transition-colors" />
                            <Input
                                type="search"
                                placeholder={t("searchProducts")}
                                className="ltr:pl-12 rtl:pr-12 h-14 text-base rounded-2xl shadow-sm border-muted-foreground/20 focus-visible:ring-primary/20 focus-visible:border-primary transition-all"
                                value={searchQuery}
                                onChange={handleSearchChange}
                                onFocus={() =>
                                    searchSuggestions.length > 0 &&
                                    setShowSuggestions(true)
                                }
                                onBlur={() =>
                                    setTimeout(
                                        () => setShowSuggestions(false),
                                        200
                                    )
                                }
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        applyFilters();
                                        setShowSuggestions(false);
                                    }
                                }}
                            />
                        </div>
                        {showSuggestions &&
                            searchSuggestions.length > 0 && (
                                <Card className="absolute z-50 w-full mt-2">
                                    <CardContent className="p-2">
                                        {searchSuggestions.map(
                                            (product) => (
                                                <div
                                                    key={product.id}
                                                    className="flex items-center gap-3 p-3 hover:bg-accent rounded-md cursor-pointer transition-colors"
                                                    onClick={() => {
                                                        setSearchQuery(
                                                            product.name
                                                        );
                                                        setShowSuggestions(
                                                            false
                                                        );
                                                        router.get(
                                                            `/menu`,
                                                            {
                                                                search: product.name,
                                                            }
                                                        );
                                                    }}
                                                >
                                                    <ImageWithFallback
                                                        src={
                                                            product.image
                                                        }
                                                        alt={
                                                            product.name
                                                        }
                                                        className="w-12 h-12 object-cover rounded shrink-0"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <p className="font-medium truncate">
                                                            {product.name}
                                                        </p>
                                                        <div className="flex items-center gap-2">
                                                            <p className="text-sm text-muted-foreground">
                                                                {formatPrice(
                                                                    getDisplayPrice(product)
                                                                )}
                                                            </p>
                                                            {product.category &&
                                                                typeof product.category ===
                                                                "object" &&
                                                                product
                                                                    .category
                                                                    .name && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        •{" "}
                                                                        {
                                                                            product
                                                                                .category
                                                                                .name
                                                                        }
                                                                    </span>
                                                                )}
                                                        </div>
                                                    </div>
                                                </div>
                                            )
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                    </div>
                </div>

                {/* Active Filters */}
                {(searchQuery ||
                    selectedCategories.length > 0 ||
                    minPrice ||
                    maxPrice) && (
                        <div className="flex flex-wrap gap-2 mb-8">
                            {selectedCategories.map((category) => {
                                const categoryName = category;
                                const categoryKey = category;
                                return (
                                    <div
                                        key={categoryKey}
                                        className="animate-in fade-in zoom-in duration-300"
                                    >
                                        <Badge
                                            variant="secondary"
                                            className="gap-2 px-3 py-1.5 text-sm hover:bg-secondary/80 transition-colors"
                                        >
                                            {categoryName}
                                            <X
                                                className="h-3.5 w-3.5 cursor-pointer hover:text-destructive transition-colors"
                                                onClick={() => {
                                                    const updatedCategories =
                                                        selectedCategories.filter(
                                                            (c) => c !== category
                                                        );
                                                    setSelectedCategories(
                                                        updatedCategories
                                                    );
                                                    applyFilters({
                                                        categoryOverride:
                                                            updatedCategories,
                                                    });
                                                }}
                                            />
                                        </Badge>
                                    </div>
                                );
                            })}
                            {(minPrice || maxPrice) && (
                                <div className="animate-in fade-in zoom-in duration-300">
                                    <Badge variant="secondary" className="gap-2 px-3 py-1.5 text-sm">
                                        {minPrice
                                            ? formatPrice(parseFloat(minPrice))
                                            : t("min")}{" "}
                                        -{" "}
                                        {maxPrice
                                            ? formatPrice(parseFloat(maxPrice))
                                            : t("max")}
                                        <X
                                            className="h-3.5 w-3.5 cursor-pointer hover:text-destructive transition-colors"
                                            onClick={() => {
                                                setMinPrice("");
                                                setMaxPrice("");
                                                applyFilters({
                                                    minPriceOverride: "",
                                                    maxPriceOverride: "",
                                                });
                                            }}
                                        />
                                    </Badge>
                                </div>
                            )}
                        </div>
                    )}

                <div className="flex gap-8">
                    {/* Desktop Filters Sidebar */}
                    <aside className="hidden lg:block w-72 shrink-0">
                        <div className="sticky top-24">
                            <Card className="border-none shadow-lg bg-card/50 backdrop-blur-sm">
                                <CardHeader className="pb-4 border-b">
                                    <h2 className="text-xl font-bold flex items-center gap-2">
                                        <Filter className="w-5 h-5 text-primary" />
                                        {t("filters")}
                                    </h2>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    {filterPanel}
                                </CardContent>
                            </Card>
                        </div>
                    </aside>

                    {/* Products Grid */}
                    <div className="flex-1">
                        <InfiniteScroll
                            data="products"
                            preserveUrl
                            buffer={300}
                            loading={
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                                    {[...Array(3)].map((_, i) => (
                                        <div key={i} className="space-y-4">
                                            <Skeleton className="h-48 w-full rounded-lg" />
                                            <div className="space-y-2">
                                                <Skeleton className="h-4 w-3/4" />
                                                <Skeleton className="h-4 w-1/2" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            }
                        >
                            {initialProducts.data.length === 0 ? (
                                <div className="text-center py-12">
                                    <p className="text-xl text-muted-foreground">
                                        {t("noProductsFound")}
                                    </p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {initialProducts.data.map(
                                        (product: Product) => (
                                            <ProductCard
                                                key={product.id}
                                                product={product}
                                            />
                                        )
                                    )}
                                </div>
                            )}
                        </InfiniteScroll>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
