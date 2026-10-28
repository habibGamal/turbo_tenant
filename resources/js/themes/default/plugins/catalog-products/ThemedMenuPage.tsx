import React, { useState, useEffect, useRef, useMemo, useCallback } from "react";
import { useSearchParams } from "react-router-dom";
import { useTranslation } from "react-i18next";
import MainLayout from "@/themes/default/layouts/MainLayout";
import ProductCard from "./components/ProductCard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { ImageWithFallback } from "@/components/ui/image";
import { ProductCardSkeleton } from "@/components/ui/page-skeletons";
import { Skeleton } from "@/components/ui/skeleton";
import { Head } from "@/components/Head";
import {
    useInfiniteCatalogProducts,
    useCatalogCategories,
    ProductSummary,
    Category,
} from "@/plugins/catalog-products";
import {
    Search,
    Filter,
    X,
    RotateCcw,
    SlidersHorizontal,
    ShoppingBag,
} from "lucide-react";

export default function ThemedMenuPage() {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const [searchParams, setSearchParams] = useSearchParams();

    const getText = useCallback(
        (text?: string, textAr?: string) => {
            if (isRTL && textAr) return textAr;
            return text || "";
        },
        [isRTL]
    );

    // Parse URL query parameters
    const urlSearch = searchParams.get("search") || "";
    const urlCategories = useMemo(() => {
        const catArray = searchParams.getAll("category[]");
        const singleCat = searchParams.getAll("category");
        const combined = [...catArray, ...singleCat];
        return Array.from(new Set(combined.filter(Boolean)));
    }, [searchParams]);

    const urlMinPrice = searchParams.get("min_price") || "";
    const urlMaxPrice = searchParams.get("max_price") || "";
    const urlSortBy = searchParams.get("sort_by") || "name";
    const urlSortOrder = searchParams.get("sort_order") || "asc";

    // Local form states synced with URL
    const [selectedCategories, setSelectedCategories] = useState<string[]>(urlCategories);
    const [minPriceInput, setMinPriceInput] = useState(urlMinPrice);
    const [maxPriceInput, setMaxPriceInput] = useState(urlMaxPrice);
    const [sortBy, setSortBy] = useState(urlSortBy);
    const [sortOrder, setSortOrder] = useState(urlSortOrder);
    const [mobileFilterOpen, setMobileFilterOpen] = useState(false);

    // Keep local state in sync when URL changes externally (e.g. Back/Forward button)
    useEffect(() => {
        setSelectedCategories(urlCategories);
        setMinPriceInput(urlMinPrice);
        setMaxPriceInput(urlMaxPrice);
        setSortBy(urlSortBy);
        setSortOrder(urlSortOrder);
    }, [urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder]);

    // Query parameters for products
    const queryParams = useMemo(() => {
        const params: Record<string, any> = {
            sort_by: urlSortBy,
            sort_order: urlSortOrder,
        };
        if (urlSearch.trim()) params.search = urlSearch.trim();
        if (urlCategories.length > 0) params.category = urlCategories;
        if (urlMinPrice) params.min_price = urlMinPrice;
        if (urlMaxPrice) params.max_price = urlMaxPrice;
        return params;
    }, [urlSearch, urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder]);

    // Fetch products with infinite scroll
    const {
        data: productsData,
        isLoading,
        isError,
        fetchNextPage,
        hasNextPage,
        isFetchingNextPage,
        refetch,
    } = useInfiniteCatalogProducts(queryParams);

    // Fetch categories for sidebar filters
    const { data: categoriesResponse } = useCatalogCategories();
    const categories: Category[] = categoriesResponse?.data || [];

    // Flatten all pages
    const products: ProductSummary[] = useMemo(() => {
        return productsData?.pages.flatMap((page) => page.data) || [];
    }, [productsData]);

    const totalProducts = productsData?.pages[0]?.meta?.total ?? products.length;

    // Push new params to URL
    const updateUrlParams = useCallback(
        (overrides: {
            search?: string;
            categories?: string[];
            minPrice?: string;
            maxPrice?: string;
            sortBy?: string;
            sortOrder?: string;
        }) => {
            const nextParams = new URLSearchParams();

            const searchVal = overrides.search !== undefined ? overrides.search : urlSearch;
            const catVal = overrides.categories !== undefined ? overrides.categories : urlCategories;
            const minVal = overrides.minPrice !== undefined ? overrides.minPrice : urlMinPrice;
            const maxVal = overrides.maxPrice !== undefined ? overrides.maxPrice : urlMaxPrice;
            const sortVal = overrides.sortBy !== undefined ? overrides.sortBy : urlSortBy;
            const orderVal = overrides.sortOrder !== undefined ? overrides.sortOrder : urlSortOrder;

            if (searchVal.trim()) nextParams.set("search", searchVal.trim());
            if (catVal.length > 0) {
                catVal.forEach((c) => nextParams.append("category[]", c));
            }
            if (minVal) nextParams.set("min_price", minVal);
            if (maxVal) nextParams.set("max_price", maxVal);
            if (sortVal && (sortVal !== "name" || (orderVal && orderVal !== "asc"))) {
                nextParams.set("sort_by", sortVal);
                nextParams.set("sort_order", orderVal || "asc");
            }

            setSearchParams(nextParams, { replace: true });
        },
        [urlSearch, urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder, setSearchParams]
    );

    // Category toggle
    const handleCategoryToggle = (categoryName: string) => {
        const nextCategories = selectedCategories.includes(categoryName)
            ? selectedCategories.filter((c) => c !== categoryName)
            : [...selectedCategories, categoryName];

        setSelectedCategories(nextCategories);
        updateUrlParams({ categories: nextCategories });
    };

    // Apply button click
    const handleApplyFilters = () => {
        let minVal = minPriceInput.trim();
        let maxVal = maxPriceInput.trim();
        if (minVal && maxVal && parseFloat(minVal) > parseFloat(maxVal)) {
            const temp = minVal;
            minVal = maxVal;
            maxVal = temp;
            setMinPriceInput(minVal);
            setMaxPriceInput(maxVal);
        }

        updateUrlParams({
            categories: selectedCategories,
            minPrice: minVal,
            maxPrice: maxVal,
            sortBy,
            sortOrder,
        });
        setMobileFilterOpen(false);
    };

    // Reset all filters
    const handleResetFilters = () => {
        setSelectedCategories([]);
        setMinPriceInput("");
        setMaxPriceInput("");
        setSortBy("name");
        setSortOrder("asc");
        setSearchParams(new URLSearchParams(), { replace: true });
        setMobileFilterOpen(false);
    };

    // Remove single filter chip
    const handleRemoveCategory = (catName: string) => {
        const next = selectedCategories.filter((c) => c !== catName);
        setSelectedCategories(next);
        updateUrlParams({ categories: next });
    };

    const handleClearPrice = () => {
        setMinPriceInput("");
        setMaxPriceInput("");
        updateUrlParams({ minPrice: "", maxPrice: "" });
    };

    const handleClearSearch = () => {
        updateUrlParams({ search: "" });
    };

    // Infinite scroll observer
    const sentinelRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!hasNextPage || isFetchingNextPage) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasNextPage && !isFetchingNextPage) {
                    fetchNextPage();
                }
            },
            { threshold: 0.1, rootMargin: "300px" }
        );

        const current = sentinelRef.current;
        if (current) observer.observe(current);

        return () => {
            if (current) observer.unobserve(current);
        };
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency", "EGP")}`;
    };

    const hasActiveFilters = Boolean(
        urlSearch ||
        urlCategories.length > 0 ||
        urlMinPrice ||
        urlMaxPrice ||
        urlSortBy !== "name" ||
        urlSortOrder !== "asc"
    );

    // Shared Filter Panel Component
    const renderFilterPanel = (panelId: "desktop" | "mobile") => (
        <div className="space-y-6" data-testid={`${panelId}-filter-panel`}>
            {/* Categories */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <label className="text-sm font-semibold tracking-tight">{t("categories", "Categories")}</label>
                    {selectedCategories.length > 0 && (
                        <span className="text-xs text-primary font-medium">
                            {selectedCategories.length} {t("selected", "selected")}
                        </span>
                    )}
                </div>
                <div className="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                    {categories.length === 0 ? (
                        <div className="space-y-2 py-2">
                            {[1, 2, 3, 4].map((i) => (
                                <div key={i} className="flex items-center space-x-3 rtl:space-x-reverse p-2">
                                    <Skeleton className="h-4 w-4 rounded" />
                                    <Skeleton className="h-4 w-28 rounded" />
                                </div>
                            ))}
                        </div>
                    ) : (
                        categories.map((category) => {
                            const isChecked = selectedCategories.includes(category.name);
                            return (
                                <div
                                    key={`${panelId}-${category.id}`}
                                    className={`flex items-center justify-between p-2 rounded-lg transition-colors cursor-pointer select-none ${
                                        isChecked ? "bg-primary/10 font-medium" : "hover:bg-accent/60"
                                    }`}
                                    onClick={() => handleCategoryToggle(category.name)}
                                    data-testid="menu-category-filter"
                                    data-category-name={category.name}
                                >
                                    <div className="flex items-center space-x-3 rtl:space-x-reverse pointer-events-none">
                                        <Checkbox
                                            id={`${panelId}-cat-${category.id}`}
                                            checked={isChecked}
                                            tabIndex={-1}
                                        />
                                        <span className="text-sm">
                                            {getText(category.name, category.name_ar)}
                                        </span>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Price Range */}
            <div className="space-y-3 pt-4 border-t border-border/50">
                <label className="text-sm font-semibold tracking-tight">{t("priceRange", "Price Range")}</label>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <span className="text-xs text-muted-foreground">{t("min", "Min")}</span>
                        <Input
                            type="number"
                            placeholder="0"
                            value={minPriceInput}
                            onChange={(e) => setMinPriceInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    handleApplyFilters();
                                }
                            }}
                            min="0"
                            step="1"
                            className="h-9"
                            data-testid={`${panelId}-min-price`}
                        />
                    </div>
                    <div className="space-y-1">
                        <span className="text-xs text-muted-foreground">{t("max", "Max")}</span>
                        <Input
                            type="number"
                            placeholder="1000"
                            value={maxPriceInput}
                            onChange={(e) => setMaxPriceInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    handleApplyFilters();
                                }
                            }}
                            min="0"
                            step="1"
                            className="h-9"
                            data-testid={`${panelId}-max-price`}
                        />
                    </div>
                </div>
            </div>

            {/* Sorting */}
            <div className="space-y-3 pt-4 border-t border-border/50">
                <label className="text-sm font-semibold tracking-tight">{t("sortBy", "Sort By")}</label>
                <div className="space-y-2">
                    <Select
                        value={`${sortBy}_${sortOrder}`}
                        onValueChange={(val) => {
                            const lastUnderscore = val.lastIndexOf("_");
                            const by = val.substring(0, lastUnderscore);
                            const order = val.substring(lastUnderscore + 1);
                            setSortBy(by);
                            setSortOrder(order);
                            updateUrlParams({ sortBy: by, sortOrder: order });
                        }}
                    >
                        <SelectTrigger className="w-full h-9" data-testid={`${panelId}-sort-select`}>
                            <SelectValue placeholder={t("sortBy", "Sort by")} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="name_asc" data-testid="sort-name-asc">{t("nameAsc", "Name: A to Z")}</SelectItem>
                            <SelectItem value="name_desc" data-testid="sort-name-desc">{t("nameDesc", "Name: Z to A")}</SelectItem>
                            <SelectItem value="base_price_asc" data-testid="sort-price-asc">{t("priceAsc", "Price: Low to High")}</SelectItem>
                            <SelectItem value="base_price_desc" data-testid="sort-price-desc">{t("priceDesc", "Price: High to Low")}</SelectItem>
                            <SelectItem value="created_at_desc" data-testid="sort-newest">{t("newest", "Newest Arrivals")}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {/* Actions */}
            <div className="flex gap-2 pt-4 border-t border-border/50">
                <Button
                    onClick={handleApplyFilters}
                    className="flex-1 shadow-sm font-medium"
                    size="sm"
                    data-testid={`${panelId}-apply-filters`}
                >
                    {t("applyFilters", "Apply")}
                </Button>
                <Button
                    onClick={handleResetFilters}
                    variant="outline"
                    size="sm"
                    className="hover:text-destructive hover:border-destructive/40"
                    data-testid={`${panelId}-reset-filters`}
                >
                    <RotateCcw className="h-4 w-4 mr-1.5 rtl:ml-1.5 rtl:mr-0" />
                    {t("reset", "Reset")}
                </Button>
            </div>
        </div>
    );

    return (
        <MainLayout categories={categories}>
            <Head
                title={t("storeMenu", "Store Menu")}
                description={t("browseOurDeliciousMenu", "Browse our delicious menu and order online")}
            />

            <div className="container mx-auto px-4 py-8" data-testid="menu-page">
                {/* Header Section */}
                <div className="mb-8">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight">
                                {t("menu", "Store Menu")}
                            </h1>
                            <p className="text-muted-foreground mt-1 text-sm md:text-base">
                                {t("browseOurDeliciousMenu", "Browse our delicious menu and freshly roasted coffee selections")}
                                {totalProducts > 0 && (
                                    <span className="font-semibold text-foreground ml-2 rtl:mr-2">
                                        ({totalProducts} {t("items", "items")})
                                    </span>
                                )}
                            </p>
                        </div>

                        {/* Mobile Filter Sheet Trigger */}
                        <div className="flex items-center gap-2 lg:hidden">
                            <Sheet open={mobileFilterOpen} onOpenChange={setMobileFilterOpen}>
                                <SheetTrigger asChild>
                                    <Button variant="outline" className="gap-2 relative" data-testid="mobile-filter-trigger">
                                        <SlidersHorizontal className="h-4 w-4 text-primary" />
                                        <span>{t("filters", "Filters")}</span>
                                        {hasActiveFilters && (
                                            <span className="w-2 h-2 rounded-full bg-primary absolute -top-1 -right-1" />
                                        )}
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side={isRTL ? "left" : "right"}
                                    className="w-[85vw] sm:max-w-md overflow-y-auto"
                                    data-testid="mobile-filter-sheet"
                                >
                                    <SheetHeader className="mb-4">
                                        <SheetTitle className="flex items-center gap-2 text-lg">
                                            <Filter className="h-5 w-5 text-primary" />
                                            {t("filters", "Filter & Sort")}
                                        </SheetTitle>
                                    </SheetHeader>
                                    {renderFilterPanel("mobile")}
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>
                </div>

                {/* Active Filter Chips */}
                {hasActiveFilters && (
                    <div className="flex flex-wrap items-center gap-2 mb-6 p-3 bg-muted/40 rounded-xl border border-border/40" data-testid="active-filters-bar">
                        <span className="text-xs font-medium text-muted-foreground mr-1 rtl:ml-1">
                            {t("activeFilters", "Active Filters:")}
                        </span>

                        {urlSearch && (
                            <Badge variant="secondary" className="gap-1.5 px-3 py-1 text-xs" data-testid="active-filter-search">
                                <span>{t("search", "Search")}: &ldquo;{urlSearch}&rdquo;</span>
                                <X
                                    className="h-3 w-3 cursor-pointer hover:text-destructive"
                                    data-testid="remove-search-chip"
                                    onClick={handleClearSearch}
                                />
                            </Badge>
                        )}

                        {urlCategories.map((cat) => {
                            const matched = categories.find((c) => c.name === cat || String(c.id) === cat);
                            const label = matched ? getText(matched.name, matched.name_ar) : cat;
                            return (
                                <Badge key={cat} variant="secondary" className="gap-1.5 px-3 py-1 text-xs" data-testid="active-filter-category">
                                    <span>{label}</span>
                                    <X
                                        className="h-3 w-3 cursor-pointer hover:text-destructive"
                                        data-testid={`remove-cat-chip-${cat}`}
                                        onClick={() => handleRemoveCategory(cat)}
                                    />
                                </Badge>
                            );
                        })}

                        {(urlMinPrice || urlMaxPrice) && (
                            <Badge variant="secondary" className="gap-1.5 px-3 py-1 text-xs" data-testid="active-filter-price">
                                <span>
                                    {urlMinPrice || "0"} - {urlMaxPrice || "∞"} {t("currency", "EGP")}
                                </span>
                                <X
                                    className="h-3 w-3 cursor-pointer hover:text-destructive"
                                    data-testid="remove-price-chip"
                                    onClick={handleClearPrice}
                                />
                            </Badge>
                        )}

                        <Button
                            variant="ghost"
                            size="sm"
                            data-testid="clear-all-filters"
                            onClick={handleResetFilters}
                            className="h-7 px-2 text-xs text-muted-foreground hover:text-destructive"
                        >
                            {t("clearAll", "Clear All")}
                        </Button>
                    </div>
                )}

                {/* Main Content Layout: Sidebar + Grid */}
                <div className="flex gap-8 items-start">
                    {/* Desktop Filters Sidebar */}
                    <aside className="hidden lg:block w-72 shrink-0">
                        <div className="sticky top-24">
                            <Card className="border border-border/60 shadow-md bg-card/90 backdrop-blur-sm rounded-2xl">
                                <CardHeader className="pb-3 border-b border-border/40">
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-base font-bold flex items-center gap-2">
                                            <Filter className="w-4 h-4 text-primary" />
                                            {t("filters", "Filter Products")}
                                        </h2>
                                        {hasActiveFilters && (
                                            <button
                                                onClick={handleResetFilters}
                                                className="text-xs text-muted-foreground hover:text-primary transition-colors"
                                            >
                                                {t("reset", "Reset")}
                                            </button>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {renderFilterPanel("desktop")}
                                </CardContent>
                            </Card>
                        </div>
                    </aside>

                    {/* Products Grid */}
                    <div className="flex-1 min-w-0">
                        {isLoading ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                {[...Array(6)].map((_, i) => (
                                    <ProductCardSkeleton key={i} />
                                ))}
                            </div>
                        ) : isError ? (
                            <div className="py-16 text-center space-y-4">
                                <p className="text-destructive font-medium">
                                    {t("errorLoadingProducts", "Failed to load products. Please check your connection.")}
                                </p>
                                <Button variant="outline" onClick={() => refetch()}>
                                    {t("tryAgain", "Try Again")}
                                </Button>
                            </div>
                        ) : products.length === 0 ? (
                            <div className="py-16 text-center rounded-2xl border border-dashed border-border p-8 bg-muted/10 space-y-4">
                                <div className="w-14 h-14 mx-auto rounded-full bg-muted flex items-center justify-center text-muted-foreground">
                                    <ShoppingBag className="w-7 h-7" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="text-lg font-bold">
                                        {t("noProductsFound", "No products found")}
                                    </h3>
                                    <p className="text-sm text-muted-foreground max-w-sm mx-auto">
                                        {t("noProductsDescription", "We couldn't find any items matching your filters or search criteria.")}
                                    </p>
                                </div>
                                {hasActiveFilters && (
                                    <Button variant="outline" onClick={handleResetFilters} className="gap-2">
                                        <RotateCcw className="h-4 w-4" />
                                        {t("clearAllFilters", "Clear All Filters")}
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {products.map((product) => (
                                        <div key={product.id} data-testid="menu-product-card">
                                            <ProductCard
                                                product={product as any}
                                                showFavorite={true}
                                            />
                                        </div>
                                    ))}
                                </div>

                                {/* Infinite Scroll Sentinel */}
                                <div ref={sentinelRef} className="h-10 mt-6" />

                                {/* Next page skeleton loading & fallback button */}
                                {hasNextPage && (
                                    <div className="py-6">
                                        {isFetchingNextPage ? (
                                            <div
                                                className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
                                                role="status"
                                                aria-busy="true"
                                                aria-label={t("loadingMore", "Loading more products...")}
                                            >
                                                {[...Array(3)].map((_, i) => (
                                                    <ProductCardSkeleton key={`more-skeleton-${i}`} />
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="text-center">
                                                <Button
                                                    variant="outline"
                                                    onClick={() => fetchNextPage()}
                                                    data-testid="load-more-button"
                                                    className="shadow-sm"
                                                >
                                                    {t("loadMore", "Load More")}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
