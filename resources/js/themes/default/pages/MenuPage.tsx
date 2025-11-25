import React, { useState, useEffect, useRef } from "react";
import { router, InfiniteScroll } from "@inertiajs/react";
import Navigation from "@/themes/default/components/Navigation";
import Footer from "@/themes/default/components/Footer";
import ProductCard from "@/themes/default/components/ProductCard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
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

interface Product {
    id: number;
    name: string;
    description: string;
    image?: string;
    price: number;
    base_price: number;
    price_after_discount?: number;
    category?: {
        id: number;
        name: string;
    };
    sell_by_weight: boolean;
}

interface PaginatedProducts {
    data: Product[];
}

interface Category {
    id: number;
    name: string;
    image?: string;
}

interface Filters {
    search: string;
    category: string | string[];
    min_price?: number;
    max_price?: number;
    sort_by: string;
    sort_order: string;
}

interface MenuPageProps {
    products: PaginatedProducts;
    categories: Category[];
    filters: Filters;
    cartItemsCount?: number;
    searchSuggestions?: Product[];
}

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
    // Sync state with URL params on navigation
    // useEffect(() => {
    //     const params = getUrlParams();
    //     setSearchQuery(params.search);
    //     setSelectedCategories(params.category);
    //     setMinPrice(params.min_price);
    //     setMaxPrice(params.max_price);
    //     setSortBy(params.sort_by);
    //     setSortOrder(params.sort_order);
    // }, [window.location.search]);
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
                router.reload({
                    only: ["searchSuggestions"],
                    data: {
                        search_query: value,
                        get_suggestions: true,
                    },
                    onSuccess: () => {
                        setShowSuggestions(true);
                        syncStateWithUrl();
                    },
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

    const applyFilters = (overrides: { categoryOverride?: string[] } = {}) => {
        const minPriceNum = minPrice ? parseFloat(minPrice) : undefined;
        const maxPriceNum = maxPrice ? parseFloat(maxPrice) : undefined;
        const categoriesToUse =
            overrides.categoryOverride !== undefined
                ? overrides.categoryOverride
                : selectedCategories;
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
            }
        );
    };

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency")}`;
    };

    const FilterPanel = () => (
        <div className="space-y-6">
            {/* Categories */}
            <div className="space-y-3">
                <label className="text-sm font-medium">{t("categories")}</label>
                <div className="space-y-2 max-h-48 overflow-y-auto">
                    {categories.map((category) => (
                        <div
                            key={category.id}
                            className="flex items-center space-x-2 "
                        >
                            <Checkbox
                                id={`category-${category.id}`}
                                checked={selectedCategories.includes(
                                    category.name
                                )}
                                onCheckedChange={() =>
                                    handleCategoryToggle(category.name)
                                }
                            />
                            <label
                                htmlFor={`category-${category.id}`}
                                className="text-sm font-normal cursor-pointer flex-1"
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
            <div className="flex gap-2 pt-4">
                <Button onClick={() => applyFilters()} className="flex-1">
                    {t("applyFilters")}
                </Button>
                <Button onClick={resetFilters} variant="outline">
                    {t("reset")}
                </Button>
            </div>
        </div>
    );

    return (
        <>
            <Navigation
                categories={categories}
                cartItemsCount={cartItemsCount}
            />

            <main className="min-h-screen bg-background py-8">
                <div className="container mx-auto px-4">
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
                                    </h2>
                                    <FilterPanel />
                                </SheetContent>
                            </Sheet>
                        </div>

                        {/* Search Bar with Suggestions */}
                        <div className="relative max-w-2xl">
                            <div className="relative">
                                <Search className="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder={t("searchProducts")}
                                    className="ltr:pl-10 rtl:pr-10 h-12 text-base"
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
                                                        {product.image && (
                                                            <img
                                                                src={
                                                                    product.image
                                                                }
                                                                alt={
                                                                    product.name
                                                                }
                                                                className="w-12 h-12 object-cover rounded"
                                                            />
                                                        )}
                                                        <div className="flex-1 min-w-0">
                                                            <p className="font-medium truncate">
                                                                {product.name}
                                                            </p>
                                                            <div className="flex items-center gap-2">
                                                                <p className="text-sm text-muted-foreground">
                                                                    {formatPrice(
                                                                        product.price
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
                        <div className="flex flex-wrap gap-2 mb-6">
                            {/* {searchQuery && (
                                <Badge variant="secondary" className="gap-2">
                                    {t("search")}: {searchQuery}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => {
                                            setSearchQuery("");
                                            applyFilters();
                                        }}
                                    />
                                </Badge>
                            )} */}
                            {selectedCategories.map((category) => {
                                const categoryName =category;
                                const categoryKey =category;
                                return (
                                    <Badge
                                        key={categoryKey}
                                        variant="secondary"
                                        className="gap-2"
                                    >
                                        {categoryName}
                                        <X
                                            className="h-3 w-3 cursor-pointer"
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
                                );
                            })}
                            {(minPrice || maxPrice) && (
                                <Badge variant="secondary" className="gap-2">
                                    {minPrice
                                        ? formatPrice(parseFloat(minPrice))
                                        : t("min")}{" "}
                                    -{" "}
                                    {maxPrice
                                        ? formatPrice(parseFloat(maxPrice))
                                        : t("max")}
                                    <X
                                        className="h-3 w-3 cursor-pointer"
                                        onClick={() => {
                                            setMinPrice("");
                                            setMaxPrice("");
                                            applyFilters();
                                        }}
                                    />
                                </Badge>
                            )}
                        </div>
                    )}

                    <div className="flex gap-8">
                        {/* Desktop Filters Sidebar */}
                        <aside className="hidden lg:block w-64 shrink-0">
                            <Card>
                                <CardHeader>
                                    <h2 className="text-lg font-semibold">
                                        {t("filters")}
                                    </h2>
                                </CardHeader>
                                <CardContent>
                                    <FilterPanel />
                                </CardContent>
                            </Card>
                        </aside>

                        {/* Products Grid */}
                        <div className="flex-1">
                            <InfiniteScroll data="products">
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
            </main>

            <Footer />
        </>
    );
}
