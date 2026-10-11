import React, { useState } from "react";
import { Review } from "@/types";
import { useTranslation } from "react-i18next";
import ReviewForm from "@/themes/default/components/ReviewForm";
import RatingSummary from "./RatingSummary";
import ReviewList from "./ReviewList";
import { Button } from "@/components/ui/button";
import { PenLine } from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

interface ReviewsSectionProps {
    productId: number;
    reviews: Review[];
    averageRating: number;
}

export default function ReviewsSection({ productId, reviews, averageRating }: ReviewsSectionProps) {
    const { t } = useTranslation();
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    return (
        <div className="space-y-8">
            <div className="flex items-center justify-between">
                <h3 className="text-2xl font-bold">
                    {t("customerReviews")} ({reviews.length})
                </h3>
                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogTrigger asChild>
                        <Button className="gap-2">
                            <PenLine className="h-4 w-4" />
                            {t("writeReview")}
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-[500px]">
                        <DialogHeader>
                            <DialogTitle>{t("writeReview")}</DialogTitle>
                        </DialogHeader>
                        <ReviewForm
                            productId={productId}
                            onSuccess={() => setIsDialogOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            </div>

            <div className="grid md:grid-cols-3 gap-8">
                <div className="md:col-span-1">
                    <RatingSummary reviews={reviews} averageRating={averageRating} />
                </div>
                <div className="md:col-span-2">
                    <ReviewList reviews={reviews} />
                </div>
            </div>
        </div>
    );
}
