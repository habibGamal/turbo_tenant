import React from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader } from "@/components/ui/card";

/**
 * Reusable Product Card Skeleton matching Theme's ProductCard dimensions
 */
export function ProductCardSkeleton() {
    return (
        <div className="rounded-2xl border border-border/60 p-4 space-y-4 bg-card">
            <Skeleton className="aspect-square w-full rounded-xl" />
            <div className="space-y-2">
                <Skeleton className="h-5 w-3/4" />
                <Skeleton className="h-4 w-1/2" />
            </div>
            <div className="flex items-center justify-between pt-2">
                <Skeleton className="h-6 w-20" />
                <Skeleton className="h-9 w-9 rounded-full" />
            </div>
        </div>
    );
}

/**
 * Checkout Page Skeleton
 */
export function CheckoutSkeleton() {
    return (
        <div
            className="container mx-auto px-4 py-8"
            role="status"
            aria-busy="true"
            aria-label="Loading checkout"
        >
            <div className="mb-8 space-y-2">
                <Skeleton className="h-8 w-44" />
                <Skeleton className="h-4 w-72" />
            </div>

            <div className="grid lg:grid-cols-3 gap-8">
                {/* Main forms column */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Order Type Tabs */}
                    <div className="flex gap-2 max-w-md">
                        <Skeleton className="h-11 w-1/2 rounded-xl" />
                        <Skeleton className="h-11 w-1/2 rounded-xl" />
                    </div>

                    {/* Delivery / Branch Card */}
                    <Card variant="recessed" className="p-6 space-y-4">
                        <Skeleton className="h-6 w-40" />
                        <div className="grid sm:grid-cols-2 gap-4">
                            <Skeleton className="h-12 w-full rounded-lg" />
                            <Skeleton className="h-12 w-full rounded-lg" />
                        </div>
                        <Skeleton className="h-12 w-full rounded-lg" />
                    </Card>

                    {/* Guest / User Info Card */}
                    <Card variant="recessed" className="p-6 space-y-4">
                        <Skeleton className="h-6 w-36" />
                        <div className="grid sm:grid-cols-2 gap-4">
                            <Skeleton className="h-11 w-full rounded-lg" />
                            <Skeleton className="h-11 w-full rounded-lg" />
                        </div>
                    </Card>

                    {/* Payment Method Card */}
                    <Card variant="recessed" className="p-6 space-y-4">
                        <Skeleton className="h-6 w-36" />
                        <div className="grid sm:grid-cols-3 gap-3">
                            <Skeleton className="h-20 w-full rounded-xl" />
                            <Skeleton className="h-20 w-full rounded-xl" />
                            <Skeleton className="h-20 w-full rounded-xl" />
                        </div>
                    </Card>

                    {/* Order Notes */}
                    <Card variant="recessed" className="p-6 space-y-3">
                        <Skeleton className="h-5 w-28" />
                        <Skeleton className="h-20 w-full rounded-lg" />
                    </Card>
                </div>

                {/* Summary sidebar column */}
                <div className="lg:col-span-1 space-y-6">
                    <Card variant="recessed" className="p-6 space-y-5 sticky top-24">
                        <Skeleton className="h-6 w-36" />

                        <div className="space-y-3 border-y border-border/50 py-4">
                            {[1, 2].map((i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <Skeleton className="h-14 w-14 rounded-lg shrink-0" />
                                    <div className="flex-1 space-y-1">
                                        <Skeleton className="h-4 w-3/4" />
                                        <Skeleton className="h-3 w-1/2" />
                                    </div>
                                    <Skeleton className="h-4 w-14" />
                                </div>
                            ))}
                        </div>

                        {/* Pricing lines */}
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between pt-2 border-t">
                                <Skeleton className="h-6 w-24" />
                                <Skeleton className="h-6 w-24" />
                            </div>
                        </div>

                        {/* Coupon input & Submit button */}
                        <div className="space-y-3 pt-2">
                            <Skeleton className="h-11 w-full rounded-lg" />
                            <Skeleton className="h-12 w-full rounded-xl" />
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    );
}

/**
 * Order Details Page Skeleton (OrderShow)
 */
export function OrderShowSkeleton() {
    return (
        <div
            className="container mx-auto px-4 py-8 max-w-5xl"
            role="status"
            aria-busy="true"
            aria-label="Loading order details"
        >
            {/* Top Navigation */}
            <div className="flex items-center justify-between mb-6">
                <Skeleton className="h-9 w-32 rounded-lg" />
                <Skeleton className="h-7 w-28 rounded-full" />
            </div>

            {/* Header Status Card */}
            <Card className="p-6 mb-8 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="space-y-2">
                        <Skeleton className="h-7 w-48" />
                        <Skeleton className="h-4 w-64" />
                    </div>
                    <Skeleton className="h-10 w-36 rounded-lg" />
                </div>
                {/* Progress bar tracker */}
                <div className="pt-4">
                    <Skeleton className="h-2 w-full rounded-full" />
                </div>
            </Card>

            <div className="grid lg:grid-cols-3 gap-8">
                {/* Items column */}
                <div className="lg:col-span-2 space-y-6">
                    <Card className="p-6 space-y-4">
                        <Skeleton className="h-6 w-32" />
                        <div className="space-y-4 divide-y divide-border/50">
                            {[1, 2, 3].map((i) => (
                                <div key={i} className="flex items-center gap-4 pt-3 first:pt-0">
                                    <Skeleton className="h-16 w-16 rounded-xl shrink-0" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-5 w-48" />
                                        <Skeleton className="h-4 w-24" />
                                    </div>
                                    <Skeleton className="h-5 w-20" />
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* Details sidebar column */}
                <div className="space-y-6">
                    <Card className="p-6 space-y-4">
                        <Skeleton className="h-6 w-36" />
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between pt-2 border-t">
                                <Skeleton className="h-6 w-20" />
                                <Skeleton className="h-6 w-24" />
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6 space-y-3">
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-3/4" />
                    </Card>
                </div>
            </div>
        </div>
    );
}

/**
 * Cart Page Skeleton
 */
export function CartSkeleton() {
    return (
        <div
            className="container mx-auto px-4 py-8 md:py-12"
            role="status"
            aria-busy="true"
            aria-label="Loading shopping cart"
        >
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div className="space-y-2">
                    <Skeleton className="h-9 w-32" />
                    <Skeleton className="h-4 w-24" />
                </div>
                <Skeleton className="h-9 w-28 rounded-lg" />
            </div>

            <div className="grid lg:grid-cols-3 gap-8">
                {/* Cart items list */}
                <div className="lg:col-span-2 space-y-4">
                    {[1, 2, 3].map((i) => (
                        <Card key={i} variant="recessed" className="p-4 sm:p-6">
                            <div className="flex items-center gap-4">
                                <Skeleton className="h-20 w-20 sm:h-24 sm:w-24 rounded-xl shrink-0" />
                                <div className="flex-1 space-y-2">
                                    <Skeleton className="h-5 w-48" />
                                    <Skeleton className="h-4 w-28" />
                                    <div className="flex items-center gap-3 pt-2">
                                        <Skeleton className="h-8 w-24 rounded-lg" />
                                        <Skeleton className="h-8 w-8 rounded-lg" />
                                    </div>
                                </div>
                                <Skeleton className="h-6 w-20" />
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Cart summary */}
                <div className="lg:col-span-1">
                    <Card variant="recessed" className="p-6 space-y-4 sticky top-24">
                        <Skeleton className="h-6 w-36" />
                        <div className="space-y-3 pt-2 border-t">
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex justify-between pt-2 border-t">
                                <Skeleton className="h-6 w-24" />
                                <Skeleton className="h-6 w-24" />
                            </div>
                        </div>
                        <Skeleton className="h-12 w-full rounded-xl mt-4" />
                    </Card>
                </div>
            </div>
        </div>
    );
}

/**
 * Orders List Page Skeleton
 */
export function OrdersListSkeleton() {
    return (
        <div
            className="space-y-4"
            role="status"
            aria-busy="true"
            aria-label="Loading your orders"
        >
            {[1, 2, 3].map((i) => (
                <Card key={i} className="p-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 pb-4 border-b">
                        <div className="space-y-1">
                            <Skeleton className="h-6 w-40" />
                            <Skeleton className="h-4 w-32" />
                        </div>
                        <Skeleton className="h-7 w-28 rounded-full" />
                    </div>
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <Skeleton className="h-4 w-48" />
                            <Skeleton className="h-5 w-24" />
                        </div>
                        <Skeleton className="h-10 w-32 rounded-lg" />
                    </div>
                </Card>
            ))}
        </div>
    );
}

/**
 * Customer Favorites Page Skeleton
 */
export function FavoritesSkeleton() {
    return (
        <div
            className="container mx-auto py-10 px-4 md:px-6"
            role="status"
            aria-busy="true"
            aria-label="Loading favorites"
        >
            <div className="mb-8 space-y-2">
                <Skeleton className="h-9 w-44" />
                <Skeleton className="h-4 w-24" />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {[1, 2, 3, 4].map((i) => (
                    <ProductCardSkeleton key={i} />
                ))}
            </div>
        </div>
    );
}

/**
 * Catalog Sections Skeleton (For Home Page & Catalog)
 */
export function CatalogSectionsSkeleton() {
    return (
        <div
            role="status"
            aria-busy="true"
            aria-label="Loading catalog sections"
        >
            {/* Category tabs pills */}
            <div className="py-6 bg-background border-b">
                <div className="container mx-auto px-4">
                    <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                        {[1, 2, 3, 4, 5, 6].map((i) => (
                            <Skeleton key={i} className="h-10 w-28 rounded-full shrink-0" />
                        ))}
                    </div>
                </div>
            </div>

            {/* Sections grid */}
            <div className="py-12 bg-background">
                <div className="container mx-auto px-4 space-y-12">
                    <div>
                        <div className="flex items-center justify-between mb-8">
                            <Skeleton className="h-8 w-48" />
                            <Skeleton className="h-9 w-24 rounded-lg" />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {[1, 2, 3, 4].map((i) => (
                                <ProductCardSkeleton key={i} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Hero Banner Skeleton (Prevents CLS above the fold)
 */
export function HeroBannerSkeleton() {
    return (
        <div
            className="relative w-full overflow-hidden bg-muted/30 aspect-[21/9] sm:aspect-[24/9] min-h-[260px] md:min-h-[420px] rounded-2xl md:rounded-none"
            role="status"
            aria-busy="true"
            aria-label="Loading banner"
        >
            <div className="container mx-auto px-4 h-full flex flex-col justify-center max-w-xl space-y-4">
                <Skeleton className="h-8 sm:h-12 w-3/4 rounded-xl" />
                <Skeleton className="h-4 sm:h-6 w-1/2 rounded-lg" />
                <Skeleton className="h-10 sm:h-12 w-36 rounded-xl mt-4" />
            </div>
        </div>
    );
}

/**
 * CMS Page Skeleton
 */
export function CmsPageSkeleton() {
    return (
        <div
            className="themed-cms-page"
            role="status"
            aria-busy="true"
            aria-label="Loading page content"
        >
            <div className="relative overflow-hidden bg-gradient-to-br from-primary/5 via-background to-background border-b">
                <div className="container relative mx-auto px-4 py-16 md:py-24 text-center space-y-4">
                    <Skeleton className="h-10 md:h-12 w-64 md:w-96 mx-auto rounded-xl" />
                    <Skeleton className="h-1.5 w-24 mx-auto rounded-full" />
                </div>
            </div>

            <div className="container mx-auto px-4 py-12 md:py-16">
                <div className="max-w-4xl mx-auto">
                    <Card className="p-6 md:p-10 space-y-4">
                        <Skeleton className="h-5 w-full" />
                        <Skeleton className="h-5 w-11/12" />
                        <Skeleton className="h-5 w-4/5" />
                        <Skeleton className="h-5 w-3/4" />
                        <div className="pt-4 space-y-3">
                            <Skeleton className="h-6 w-48 rounded-lg" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-5/6" />
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    );
}

/**
 * Payment Gateway Iframe Skeleton (Kashier / Hosted Checkout)
 */
export function PaymentGatewaySkeleton() {
    return (
        <div
            className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 py-8 px-4"
            role="status"
            aria-busy="true"
            aria-label="Loading secure payment gateway"
        >
            <div className="max-w-2xl mx-auto space-y-6">
                <div className="text-center space-y-3">
                    <Skeleton className="w-16 h-16 rounded-full mx-auto" />
                    <Skeleton className="h-8 w-64 mx-auto rounded-lg" />
                    <Skeleton className="h-4 w-36 mx-auto" />
                </div>

                <Card className="p-6 space-y-6">
                    <div className="flex items-center justify-between p-4 bg-muted/40 rounded-lg">
                        <div className="space-y-1">
                            <Skeleton className="h-4 w-28" />
                            <Skeleton className="h-7 w-36" />
                        </div>
                        <Skeleton className="h-6 w-20 rounded-md" />
                    </div>

                    <Skeleton className="min-h-[400px] w-full rounded-xl" />
                </Card>
            </div>
        </div>
    );
}

/**
 * Reviews Tab Skeleton
 */
export function ReviewsSkeleton() {
    return (
        <div
            className="space-y-6 py-4"
            role="status"
            aria-busy="true"
            aria-label="Loading customer reviews"
        >
            <Card className="p-6">
                <div className="flex items-center gap-6">
                    <div className="space-y-2 text-center">
                        <Skeleton className="h-10 w-16 mx-auto" />
                        <Skeleton className="h-4 w-24" />
                    </div>
                    <div className="flex-1 space-y-2">
                        {[1, 2, 3].map((i) => (
                            <Skeleton key={i} className="h-3 w-full rounded-full" />
                        ))}
                    </div>
                </div>
            </Card>

            <div className="space-y-4">
                {[1, 2].map((i) => (
                    <Card key={i} className="p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <Skeleton className="h-9 w-9 rounded-full" />
                                <div className="space-y-1">
                                    <Skeleton className="h-4 w-28" />
                                    <Skeleton className="h-3 w-20" />
                                </div>
                            </div>
                            <Skeleton className="h-4 w-24" />
                        </div>
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-2/3" />
                    </Card>
                ))}
            </div>
        </div>
    );
}
