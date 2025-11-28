import React from "react";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { ImageWithFallback } from "@/components/ui/image";
import { Star, User } from "lucide-react";
import { Review } from "@/types";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

interface ReviewListProps {
    reviews: Review[];
}

export default function ReviewList({ reviews }: ReviewListProps) {
    if (reviews.length === 0) {
        return (
            <div className="text-center py-12 text-muted-foreground">
                No reviews yet. Be the first to review!
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {reviews.map((review) => (
                <Card key={review.id} className="overflow-hidden">
                    <CardHeader className="bg-muted/30 pb-4">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <Avatar className="h-10 w-10 border">
                                    <AvatarImage src={`https://api.dicebear.com/7.x/initials/svg?seed=${review.user_name}`} />
                                    <AvatarFallback>
                                        <User className="h-5 w-5 text-muted-foreground" />
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-semibold">
                                        {review.user_name}
                                    </div>
                                    <div className="flex items-center gap-1 mt-0.5">
                                        {[...Array(5)].map(
                                            (_, i) => (
                                                <Star
                                                    key={i}
                                                    className={`h-3.5 w-3.5 ${i < review.rating
                                                        ? "fill-yellow-400 text-yellow-400"
                                                        : "text-gray-300"
                                                        }`}
                                                />
                                            )
                                        )}
                                    </div>
                                </div>
                            </div>
                            <span className="text-sm text-muted-foreground">
                                {new Date(review.created_at).toLocaleDateString(undefined, {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <p className="text-foreground/80 leading-relaxed">
                            {review.comment}
                        </p>
                        {review.images && review.images.length > 0 && (
                            <div className="flex gap-2 mt-4 overflow-x-auto pb-2">
                                {review.images.map((image, index) => (
                                    <div key={index} className="w-24 h-24 rounded-lg overflow-hidden border shrink-0 cursor-pointer hover:opacity-90 transition-opacity">
                                        <ImageWithFallback
                                            src={image}
                                            alt={`Review image ${index + 1}`}
                                            className="w-full h-full object-cover"
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
