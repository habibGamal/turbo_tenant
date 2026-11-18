import React from "react";
import { Skeleton } from "@/components/ui/skeleton";

export function ProductPageSkeleton() {
    return (
        <div
            className="min-h-screen bg-background"
            role="status"
            aria-busy="true"
            aria-label="Loading product details"
        >
            <main className="container mx-auto px-4 py-8">
                {/* Breadcrumb Skeleton */}
                <div className="flex items-center gap-2 mb-6">
                    <Skeleton className="h-4 w-12" />
                    <span className="text-muted-foreground/40">/</span>
                    <Skeleton className="h-4 w-24" />
                    <span className="text-muted-foreground/40">/</span>
                    <Skeleton className="h-4 w-36" />
                </div>

                {/* Product Details Section Skeleton */}
                <div className="grid lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
                    {/* Left: Product Image & Gallery Skeleton */}
                    <div className="space-y-4">
                        <Skeleton className="aspect-square w-full rounded-2xl" />
                        <div className="flex gap-3">
                            <Skeleton className="h-16 w-16 rounded-xl" />
                            <Skeleton className="h-16 w-16 rounded-xl" />
                            <Skeleton className="h-16 w-16 rounded-xl" />
                        </div>
                    </div>

                    {/* Right: Product Info & Options Skeleton */}
                    <div className="space-y-6">
                        {/* Category badge */}
                        <Skeleton className="h-6 w-24 rounded-full" />

                        {/* Title */}
                        <div className="space-y-2">
                            <Skeleton className="h-10 w-4/5" />
                            <Skeleton className="h-10 w-2/3" />
                        </div>

                        {/* Rating & Stock */}
                        <div className="flex items-center gap-4">
                            <Skeleton className="h-5 w-28 rounded-md" />
                            <Skeleton className="h-5 w-20 rounded-md" />
                        </div>

                        {/* Price */}
                        <div className="flex items-baseline gap-3">
                            <Skeleton className="h-9 w-32" />
                            <Skeleton className="h-6 w-20" />
                        </div>

                        {/* Description paragraphs */}
                        <div className="space-y-2 pt-2">
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-5/6" />
                            <Skeleton className="h-4 w-4/6" />
                        </div>

                        {/* Variants / Weight selector placeholder */}
                        <div className="space-y-3 pt-4 border-t border-border/50">
                            <Skeleton className="h-5 w-28" />
                            <div className="flex flex-wrap gap-2">
                                <Skeleton className="h-10 w-24 rounded-lg" />
                                <Skeleton className="h-10 w-24 rounded-lg" />
                                <Skeleton className="h-10 w-24 rounded-lg" />
                            </div>
                        </div>

                        {/* Extras placeholder */}
                        <div className="space-y-2 pt-3">
                            <Skeleton className="h-5 w-20" />
                            <div className="grid grid-cols-2 gap-2">
                                <Skeleton className="h-11 rounded-lg" />
                                <Skeleton className="h-11 rounded-lg" />
                            </div>
                        </div>

                        {/* Action buttons (Quantity + Add to Cart + Favorite) */}
                        <div className="flex items-center gap-3 pt-4">
                            <Skeleton className="h-12 w-32 rounded-xl" />
                            <Skeleton className="h-12 flex-1 rounded-xl" />
                            <Skeleton className="h-12 w-12 rounded-xl" />
                        </div>
                    </div>
                </div>

                {/* Tabs Section Skeleton */}
                <div className="space-y-6 mb-16">
                    <div className="flex gap-2 max-w-md">
                        <Skeleton className="h-10 w-1/2 rounded-lg" />
                        <Skeleton className="h-10 w-1/2 rounded-lg" />
                    </div>
                    <Skeleton className="h-32 w-full rounded-2xl" />
                </div>

                {/* Related Products Grid Skeleton */}
                <section className="mb-16">
                    <div className="flex items-center justify-between mb-8">
                        <Skeleton className="h-8 w-44" />
                        <Skeleton className="h-9 w-24 rounded-lg" />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {[...Array(4)].map((_, idx) => (
                            <div
                                key={idx}
                                className="rounded-2xl border border-border p-4 space-y-4 bg-muted/70 dark:bg-black/25"
                            >
                                <Skeleton className="aspect-square w-full rounded-xl" />
                                <Skeleton className="h-5 w-3/4" />
                                <Skeleton className="h-4 w-1/2" />
                                <div className="flex items-center justify-between pt-2">
                                    <Skeleton className="h-6 w-20" />
                                    <Skeleton className="h-9 w-9 rounded-full" />
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </main>
        </div>
    );
}

export default ProductPageSkeleton;
