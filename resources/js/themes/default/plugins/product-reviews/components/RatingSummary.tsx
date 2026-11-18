import React from "react";
import { Star } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Progress } from "@/components/ui/progress";
import { ReviewItem } from "@/plugins/product-reviews/types";
import { formatReviewsCount } from "@/lib/arabicPlural";

interface RatingSummaryProps {
    reviews: ReviewItem[];
    averageRating: number;
}

export default function RatingSummary({ reviews, averageRating }: RatingSummaryProps) {
    const { t, i18n } = useTranslation();

    const totalReviews = reviews.length;
    const ratingCounts = [0, 0, 0, 0, 0]; // 1 to 5 stars

    reviews.forEach((review) => {
        if (review.rating >= 1 && review.rating <= 5) {
            ratingCounts[review.rating - 1]++;
        }
    });

    return (
        <div className="bg-card rounded-xl border p-6 space-y-6">
            <div className="flex items-center gap-4">
                <div className="text-center">
                    <div className="text-5xl font-bold text-foreground">
                        {averageRating.toFixed(1)}
                    </div>
                    <div className="flex items-center justify-center gap-1 mt-2">
                        {[...Array(5)].map((_, i) => (
                            <Star
                                key={i}
                                className={`h-4 w-4 ${i < Math.round(averageRating)
                                    ? "fill-amber-400 text-amber-400"
                                    : "text-muted-foreground/30"
                                    }`}
                            />
                        ))}
                    </div>
                    <div className="text-sm text-muted-foreground mt-1">
                        {formatReviewsCount(totalReviews, i18n.language === "ar")}
                    </div>
                </div>
                <div className="flex-1 space-y-2">
                    {[5, 4, 3, 2, 1].map((star) => {
                        const count = ratingCounts[star - 1];
                        const percentage = totalReviews > 0 ? (count / totalReviews) * 100 : 0;
                        return (
                            <div key={star} className="flex items-center gap-3 text-sm">
                                <span className="w-3 font-medium">{star}</span>
                                <Star className="h-4 w-4 text-muted-foreground" />
                                <Progress value={percentage} className="h-2" />
                                <span className="w-8 text-end text-muted-foreground">
                                    {count}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
