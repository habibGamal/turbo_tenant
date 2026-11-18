import React from "react";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { ImageWithFallback } from "@/components/ui/image";
import { Star, User } from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { useTranslation } from "react-i18next";
import { ReviewItem } from "@/plugins/product-reviews/types";

interface ReviewListProps {
    reviews: ReviewItem[];
}

export default function ReviewList({ reviews }: ReviewListProps) {
    const { t, i18n } = useTranslation();
    const dateLocale = i18n.language === "ar" ? "ar-EG" : "en-US";
    if (reviews.length === 0) {
        return (
            <div className="text-center py-12 text-muted-foreground">
                {t("noReviewsYet", "No reviews yet. Be the first to review!")}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {reviews.map((review) => {
                const displayName = review.user?.name || review.guest_name || t("anonymous", "Customer");
                return (
                    <Card key={review.id} data-testid="review-card" className="overflow-hidden">
                        <CardHeader className="bg-muted/30 pb-4">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-3">
                                    <Avatar className="h-10 w-10 border">
                                        <AvatarImage src={`https://api.dicebear.com/7.x/initials/svg?seed=${displayName}`} />
                                        <AvatarFallback>
                                            <User className="h-5 w-5 text-muted-foreground" />
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div className="font-semibold">
                                            {displayName}
                                        </div>
                                        <div className="flex items-center gap-1 mt-0.5">
                                            {[...Array(5)].map(
                                                (_, i) => (
                                                    <Star
                                                        key={i}
                                                        className={`h-3.5 w-3.5 ${i < review.rating
                                                            ? "fill-amber-400 text-amber-400"
                                                            : "text-muted-foreground/30"
                                                            }`}
                                                    />
                                                )
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <span className="text-sm text-muted-foreground shrink-0">
                                    {new Date(review.created_at).toLocaleDateString(dateLocale, {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <p dir="auto" className="text-foreground/80 leading-relaxed">
                                {review.comment}
                            </p>
                            {review.images && review.images.length > 0 && (
                                <div className="flex gap-2 mt-4 overflow-x-auto pb-2">
                                    {review.images.map((image, index) => (
                                        <div key={index} className="w-24 h-24 rounded-lg overflow-hidden border shrink-0 cursor-pointer hover:opacity-90 transition-opacity">
                                            <ImageWithFallback
                                                src={image}
                                                alt={`${t("reviewImage", "Review image")} ${index + 1}`}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
