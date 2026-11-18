import React, { useState, useEffect, useRef, useMemo, useCallback } from "react";
import { useSearchParams, useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import MainLayout from "@/themes/default/layouts/MainLayout";
import ProductCard from "./components/ProductCard";
import MenuItemRow from "./components/MenuItemRow";
import CategoryScrollBar from "./components/CategoryScrollBar";
import ProductOptionsModal from "./components/ProductOptionsModal";
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
import { ProductCardSkeleton } from "@/components/ui/page-skeletons";
import { Skeleton } from "@/components/ui/skeleton";
import { Head } from "@/components/Head";
import { emitCommerceEvent } from "@/core/analytics";
import {
    useInfiniteCatalogProducts,
    useCatalogCategories,
    ProductSummary,
    Category,
} from "@/plugins/catalog-products";
import { useFormatMoney } from "@/lib/money";
import { formatItemsCount } from "@/lib/arabicPlural";
import { fetchCatalogProduct } from "@/plugins/catalog-products/api/useCatalogProduct";
import { normalizeProduct } from "@/plugins/catalog-products/utils/normalizeProduct";
import { NormalizedProduct } from "@/plugins/catalog-products/types";
import {
    Filter,
    X,
    RotateCcw,
    SlidersHorizontal,
    ShoppingBag,
} from "lucide-react";

export default function ThemedMenuPage() {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const navigate = useNavigate();
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
        const catIdsArray = searchParams.getAll("category_ids[]");
        const singleCatIds = searchParams.getAll("category_ids");
        const combined = [...catArray, ...singleCat, ...catIdsArray, ...singleCatIds];
        return Array.from(new Set(combined.filter(Boolean)));
    }, [searchParams]);

    const urlMinPrice = searchParams.get("min_price") || "";
    const urlMaxPrice = searchParams.get("max_price") || "";
    const urlSortBy = searchParams.get("sort_by") || "";
    const urlSortOrder = searchParams.get("sort_order") || "";

    // Local form states synced with URL
    const [selectedCategories, setSelectedCategories] = useState<string[]>(urlCategories);
    const [minPriceInput, setMinPriceInput] = useState(urlMinPrice);
    const [maxPriceInput, setMaxPriceInput] = useState(urlMaxPrice);
    const [sortBy, setSortBy] = useState(urlSortBy);
    const [sortOrder, setSortOrder] = useState(urlSortOrder);
    const [mobileFilterOpen, setMobileFilterOpen] = useState(false);

    // Modal state for products with options
    const [modalProduct, setModalProduct] = useState<NormalizedProduct | null>(null);
    const [isOptionsModalOpen, setIsOptionsModalOpen] = useState(false);

    // Keep local state in sync when URL changes externally (e.g. Back/Forward button)
    useEffect(() => {
        setSelectedCategories(urlCategories);
        setMinPriceInput(urlMinPrice);
        setMaxPriceInput(urlMaxPrice);
        setSortBy(urlSortBy);
        setSortOrder(urlSortOrder);
    }, [urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder]);

    // Track search query committed in URL (debounced ~800ms, non-empty, once per distinct query)
    const lastSearchRef = useRef<string>("");
    useEffect(() => {
        const trimmed = urlSearch.trim();
        if (!trimmed || trimmed === lastSearchRef.current) return;

        const timer = setTimeout(() => {
            if (trimmed && trimmed !== lastSearchRef.current) {
                lastSearchRef.current = trimmed;
                emitCommerceEvent('Search', {
                    search_string: trimmed,
                });
            }
        }, 800);

        return () => clearTimeout(timer);
    }, [urlSearch]);

    // Fetch categories for sidebar filters and grouping
    const { data: categoriesResponse } = useCatalogCategories();
    const categories: Category[] = categoriesResponse || [];

    // Query parameters for products
    const queryParams = useMemo(() => {
        const params: Record<string, any> = {
            per_page: 50,
        };
        if (urlSortBy) {
            params.sort_by = urlSortBy;
            params.sort_order = urlSortOrder || "asc";
        }
        if (urlSearch.trim()) params.search = urlSearch.trim();

        if (urlCategories.length > 0) {
            const resolvedIds = new Set<number>();
            urlCategories.forEach((catVal) => {
                const numeric = Number(catVal);
                if (!Number.isNaN(numeric) && numeric > 0) {
                    resolvedIds.add(numeric);
                } else {
                    const matched = categories.find((c) => c.name === catVal || c.name_ar === catVal);
                    if (matched) resolvedIds.add(matched.id);
                }
            });
            if (resolvedIds.size > 0) {
                params.category_ids = Array.from(resolvedIds);
            }
        }

        if (urlMinPrice) params.min_price = urlMinPrice;
        if (urlMaxPrice) params.max_price = urlMaxPrice;
        return params;
    }, [urlSearch, urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder, categories]);

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

    // Sort categories client-side by admin sort_order, then id
    const sortedCategories = useMemo(() => {
        return [...categories].sort((a, b) => {
            const orderA = a.sort_order ?? 0;
            const orderB = b.sort_order ?? 0;
            if (orderA !== orderB) return orderA - orderB;
            return a.id - b.id;
        });
    }, [categories]);

    // Flatten all pages
    const rawProducts: ProductSummary[] = useMemo(() => {
        return productsData?.pages.flatMap((page) => page.data) || [];
    }, [productsData]);

    const totalProducts = productsData?.pages[0]?.meta?.total ?? rawProducts.length;

    // Helper for product availability
    const isAvailable = useCallback((p: ProductSummary) => {
        return (p.is_active ?? true) && (p as any).is_available !== false;
    }, []);

    // Push unavailable products to the end of a list and sort by sort_order
    const sortProductList = useCallback((list: ProductSummary[]) => {
        const available = list.filter(isAvailable);
        const unavailable = list.filter((p) => !isAvailable(p));

        const sortByOrder = (a: ProductSummary, b: ProductSummary) => {
            const orderA = (a as any).sort_order;
            const orderB = (b as any).sort_order;
            if (typeof orderA === "number" && typeof orderB === "number" && orderA !== orderB) {
                return orderA - orderB;
            }
            return 0;
        };

        available.sort(sortByOrder);
        unavailable.sort(sortByOrder);

        return [...available, ...unavailable];
    }, [isAvailable]);

    // Group products by category for mobile view (sorted by category sort_order)
    const categoryGroups = useMemo(() => {
        if (!rawProducts.length) return [];

        const groups: Array<{
            category: {
                id: number;
                name: string;
                name_ar?: string;
                description?: string;
                sort_order?: number;
            };
            products: ProductSummary[];
        }> = [];

        const assignedProductIds = new Set<number>();

        sortedCategories.forEach((cat) => {
            const matching = rawProducts.filter(
                (p) => p.category?.id === cat.id || p.category?.name === cat.name
            );
            if (matching.length > 0) {
                matching.forEach((p) => assignedProductIds.add(p.id));
                groups.push({
                    category: {
                        id: cat.id,
                        name: cat.name,
                        name_ar: cat.name_ar,
                        description: cat.description,
                        sort_order: cat.sort_order,
                    },
                    products: sortProductList(matching),
                });
            }
        });

        // Collect any uncategorized products
        const uncategorized = rawProducts.filter((p) => !assignedProductIds.has(p.id));
        if (uncategorized.length > 0) {
            groups.push({
                category: {
                    id: -1,
                    name: "Other",
                    name_ar: "أخرى",
                    sort_order: 9999,
                },
                products: sortProductList(uncategorized),
            });
        }

        return groups;
    }, [rawProducts, sortedCategories, sortProductList]);

    // Categories list for horizontal scrollbar (only categories with matching products)
    const scrollBarCategories = useMemo(() => {
        return categoryGroups.map((g) => ({
            id: g.category.id,
            name: g.category.name,
            name_ar: g.category.name_ar,
            count: g.products.length,
            sort_order: g.category.sort_order,
        }));
    }, [categoryGroups]);

    // Desktop products: preserve grid and push unavailable products to the end
    const desktopProducts = useMemo(() => {
        return sortProductList(rawProducts);
    }, [rawProducts, sortProductList]);

    // Active category tracking for IntersectionObserver
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null);
    const isManualScrolling = useRef(false);
    const manualScrollTimer = useRef<NodeJS.Timeout | null>(null);
    const sectionRefs = useRef<Record<number, HTMLElement | null>>({});

    // Set first category active by default
    useEffect(() => {
        if (scrollBarCategories.length > 0) {
            if (activeCategoryId === null || !scrollBarCategories.some((c) => c.id === activeCategoryId)) {
                setActiveCategoryId(scrollBarCategories[0].id);
            }
        }
    }, [scrollBarCategories, activeCategoryId]);

    // IntersectionObserver to highlight category currently in view on mobile
    useEffect(() => {
        if (categoryGroups.length === 0) return;

        const visibleMap = new Map<number, boolean>();

        const observer = new IntersectionObserver(
            (entries) => {
                if (isManualScrolling.current) return;

                entries.forEach((entry) => {
                    const catId = Number(entry.target.getAttribute("data-category-id"));
                    if (!Number.isNaN(catId)) {
                        visibleMap.set(catId, entry.isIntersecting);
                    }
                });

                // Pick the first visible category in DOM order
                const firstVisible = categoryGroups.find((g) => visibleMap.get(g.category.id));
                if (firstVisible) {
                    setActiveCategoryId(firstVisible.category.id);
                }
            },
            {
                rootMargin: "-100px 0px -50% 0px",
                threshold: [0, 0.1, 0.5],
            }
        );

        categoryGroups.forEach((group) => {
            const el = sectionRefs.current[group.category.id];
            if (el) observer.observe(el);
        });

        return () => {
            observer.disconnect();
        };
    }, [categoryGroups]);

    // Scroll to category on tap
    const handleSelectCategoryFromBar = (catId: number) => {
        setActiveCategoryId(catId);
        isManualScrolling.current = true;
        if (manualScrollTimer.current) clearTimeout(manualScrollTimer.current);
        manualScrollTimer.current = setTimeout(() => {
            isManualScrolling.current = false;
        }, 800);

        const targetEl = document.getElementById(`category-section-${catId}`);
        if (targetEl) {
            targetEl.scrollIntoView({ behavior: "smooth" });
        }
    };

    // Open product options modal for products with options
    const handleOpenOptions = async (productSummary: ProductSummary) => {
        try {
            const productData = await fetchCatalogProduct(productSummary.id);
            const full = normalizeProduct(productData);
            setModalProduct(full);
            setIsOptionsModalOpen(true);
        } catch (error) {
            console.error("Failed to load product details", error);
            navigate(`/products/${productSummary.id}`);
        }
    };

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
            if (sortVal) {
                nextParams.set("sort_by", sortVal);
                nextParams.set("sort_order", orderVal || "asc");
            }

            setSearchParams(nextParams, { replace: true });
        },
        [urlSearch, urlCategories, urlMinPrice, urlMaxPrice, urlSortBy, urlSortOrder, setSearchParams]
    );

    // Category toggle in filter panel
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
        setSortBy("");
        setSortOrder("");
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

    const formatMoney = useFormatMoney();

    const formatPrice = (price: number) => {
        return formatMoney(price);
    };

    const hasActiveFilters = Boolean(
        urlSearch ||
        urlCategories.length > 0 ||
        urlMinPrice ||
        urlMaxPrice ||
        urlSortBy ||
        urlSortOrder
    );

    // Filter Panel Component: Hides price-range on mobile (< md) per item B8
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
                <div className="space-y-1.5 max-h-60 overflow-y-auto pe-1">
                    {sortedCategories.length === 0 ? (
                        <div className="space-y-2 py-2">
                            {[1, 2, 3, 4].map((i) => (
                                <div key={i} className="flex items-center gap-3 p-2">
                                    <Skeleton className="h-4 w-4 rounded" />
                                    <Skeleton className="h-4 w-28 rounded" />
                                </div>
                            ))}
                        </div>
                    ) : (
                        sortedCategories.map((category) => {
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
                                    <div className="flex items-center gap-3 pointer-events-none">
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

            {/* Price Range: Hidden on mobile (< md) per requirement */}
            {panelId === "desktop" && (
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
                                data-testid={`${panelId}-max-price`}
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* Sorting */}
            <div className="space-y-3 pt-4 border-t border-border/50">
                <label className="text-sm font-semibold tracking-tight">{t("sortBy", "Sort By")}</label>
                <div className="space-y-2">
                    <Select
                        value={sortBy && sortOrder ? `${sortBy}_${sortOrder}` : "default"}
                        onValueChange={(val) => {
                            if (val === "default") {
                                setSortBy("");
                                setSortOrder("");
                                updateUrlParams({ sortBy: "", sortOrder: "" });
                                return;
                            }
                            const lastUnderscore = val.lastIndexOf("_");
                            const by = val.substring(0, lastUnderscore);
                            const order = val.substring(lastUnderscore + 1);
                            setSortBy(by);
                            setSortOrder(order);
                            updateUrlParams({ sortBy: by, sortOrder: order });
                        }}
                    >
                        <SelectTrigger className="w-full" data-testid={`${panelId}-sort-select`}>
                            <SelectValue placeholder={t("sortBy", "Sort by")} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="default">{t("defaultOrder", "Default (Menu Order)")}</SelectItem>
                            <SelectItem value="name_asc" data-testid="sort-name-asc">{t("nameAsc", "Name: A to Z")}</SelectItem>
                            <SelectItem value="name_desc" data-testid="sort-name-desc">{t("nameDesc", "Name: Z to A")}</SelectItem>
                            <SelectItem value="price_asc" data-testid="sort-price-asc">{t("priceAsc", "Price: Low to High")}</SelectItem>
                            <SelectItem value="price_desc" data-testid="sort-price-desc">{t("priceDesc", "Price: High to Low")}</SelectItem>
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
                    data-testid={`${panelId}-apply-filters`}
                >
                    {t("applyFilters", "Apply")}
                </Button>
                <Button
                    onClick={handleResetFilters}
                    variant="outline"
                    className="hover:text-destructive hover:border-destructive/40"
                    data-testid={`${panelId}-reset-filters`}
                >
                    <RotateCcw className="h-4 w-4 me-1.5" />
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
                <div className="mb-6">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                        <div>
                            <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight">
                                {t("menu", "Store Menu")}
                            </h1>
                            <p className="text-muted-foreground mt-1 text-sm md:text-base">
                                {t("browseOurDeliciousMenu", "Browse our delicious menu and freshly roasted coffee selections")}
                                {totalProducts > 0 && (
                                    <span className="font-semibold text-foreground ms-2">
                                        ({formatItemsCount(totalProducts, isRTL)})
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
                                            <span className="w-2 h-2 rounded-full bg-primary absolute -top-1 -end-1" />
                                        )}
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side={isRTL ? "right" : "left"}
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
                        <span className="text-xs font-medium text-muted-foreground me-1">
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
                                    {urlMinPrice ? formatMoney(urlMinPrice) : "0"} - {urlMaxPrice ? formatMoney(urlMaxPrice) : "∞"}
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

                {/* Main Content Layout: Sidebar + Products Content */}
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
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {renderFilterPanel("desktop")}
                                </CardContent>
                            </Card>
                        </div>
                    </aside>

                    {/* Products Display Area */}
                    <div className="flex-1 min-w-0">
                        {isLoading ? (
                            <>
                                {/* Mobile loading skeleton */}
                                <div className="block md:hidden space-y-3">
                                    {[...Array(6)].map((_, i) => (
                                        <div key={i} className="flex items-center justify-between gap-3 p-3 rounded-2xl border border-border/40 bg-card">
                                            <div className="flex-1 space-y-2">
                                                <Skeleton className="h-4 w-32 rounded" />
                                                <Skeleton className="h-3 w-48 rounded" />
                                                <Skeleton className="h-4 w-16 rounded mt-2" />
                                            </div>
                                            <Skeleton className="w-20 h-20 rounded-xl" />
                                        </div>
                                    ))}
                                </div>
                                {/* Desktop loading skeleton */}
                                <div className="hidden md:grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {[...Array(6)].map((_, i) => (
                                        <ProductCardSkeleton key={i} />
                                    ))}
                                </div>
                            </>
                        ) : isError ? (
                            <div className="py-16 text-center space-y-4">
                                <p className="text-destructive font-medium">
                                    {t("errorLoadingProducts", "Failed to load products. Please check your connection.")}
                                </p>
                                <Button variant="outline" onClick={() => refetch()}>
                                    {t("tryAgain", "Try Again")}
                                </Button>
                            </div>
                        ) : rawProducts.length === 0 ? (
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
                                {/* Mobile View (< md): Sticky Category Bar + Compact MenuItemRows Grouped by Category */}
                                <div className="block md:hidden">
                                    {scrollBarCategories.length > 0 && (
                                        <div className="sticky top-[var(--header-h,4rem)] z-30 -mx-4 px-4 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 border-b border-border/40 py-2 mb-6">
                                            <CategoryScrollBar
                                                categories={scrollBarCategories}
                                                activeCategoryId={activeCategoryId}
                                                onSelectCategory={handleSelectCategoryFromBar}
                                            />
                                        </div>
                                    )}

                                    <div className="space-y-8">
                                        {categoryGroups.map((group) => (
                                            <section
                                                key={group.category.id}
                                                id={`category-section-${group.category.id}`}
                                                data-category-id={group.category.id}
                                                ref={(el) => {
                                                    sectionRefs.current[group.category.id] = el;
                                                }}
                                                className="scroll-mt-[calc(var(--header-h,4rem)+3.5rem)] space-y-3"
                                            >
                                                <div className="flex items-center justify-between border-b border-border/40 pb-2">
                                                    <h2 className="text-base font-bold tracking-tight text-foreground">
                                                        {getText(group.category.name, group.category.name_ar)}
                                                    </h2>
                                                    <Badge variant="secondary" className="text-xs font-normal">
                                                        {group.products.length} {t("items", "items")}
                                                    </Badge>
                                                </div>
                                                {group.category.description && (
                                                    <p className="text-xs text-muted-foreground -mt-1">
                                                        {group.category.description}
                                                    </p>
                                                )}
                                                <div className="space-y-2.5">
                                                    {group.products.map((product) => (
                                                        <MenuItemRow
                                                            key={product.id}
                                                            product={product}
                                                            onOpenOptions={handleOpenOptions}
                                                            formatPrice={formatPrice}
                                                        />
                                                    ))}
                                                </div>
                                            </section>
                                        ))}
                                    </div>
                                </div>

                                {/* Desktop View (>= md): Existing Card Grid */}
                                <div className="hidden md:block">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                        {desktopProducts.map((product) => (
                                            <div key={product.id} data-testid="menu-product-card">
                                                <ProductCard
                                                    product={product as any}
                                                    showFavorite={true}
                                                />
                                            </div>
                                        ))}
                                    </div>
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

            {/* Modal for Products with Options / Variants / Extras */}
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
        </MainLayout>
    );
}
