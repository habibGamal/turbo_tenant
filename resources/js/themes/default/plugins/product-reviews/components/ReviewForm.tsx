import { LoaderIcon } from "lucide-react";
import React, { useState } from "react";
import { Star, Upload, X, Image as ImageIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { useTranslation } from "react-i18next";
import { useSubmitReview } from "@/plugins/product-reviews/api/useSubmitReview";

interface ReviewFormProps {
    productId: number;
    onSuccess?: () => void;
}

export default function ReviewForm({ productId, onSuccess }: ReviewFormProps) {
    const { t, i18n } = useTranslation();
    const [rating, setRating] = useState(0);
    const [comment, setComment] = useState("");
    const [images, setImages] = useState<File[]>([]);
    const [previewImages, setPreviewImages] = useState<string[]>([]);
    const [hoverRating, setHoverRating] = useState(0);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { mutate, isPending } = useSubmitReview(productId);

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            setImages((prev) => [...prev, ...files]);

            const newPreviews = files.map((file) => URL.createObjectURL(file));
            setPreviewImages((prev) => [...prev, ...newPreviews]);
        }
    };

    const removeImage = (index: number) => {
        setImages((prev) => prev.filter((_, i) => i !== index));
        URL.revokeObjectURL(previewImages[index]);
        setPreviewImages((prev) => prev.filter((_, i) => i !== index));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (rating === 0) {
            setErrorMessage(t("ratingRequired", "Please select a rating"));
            return;
        }
        if (!comment.trim()) {
            setErrorMessage(t("commentRequired", "Please provide a comment"));
            return;
        }
        setErrorMessage(null);

        mutate(
            {
                rating,
                comment,
                images,
            },
            {
                onSuccess: () => {
                    setRating(0);
                    setComment("");
                    setImages([]);
                    setPreviewImages([]);
                    if (onSuccess) onSuccess();
                },
                onError: (err: any) => {
                    setErrorMessage(err?.response?.data?.message || t("failedToSubmitReview", "Failed to submit review"));
                },
            }
        );
    };

    const isRtl = i18n.language === 'ar';

    return (
        <form onSubmit={submit} className="space-y-8" dir={isRtl ? 'rtl' : 'ltr'}>
            {errorMessage && (
                <div className="p-3 text-sm rounded-lg bg-destructive/10 text-destructive border border-destructive/20">
                    {errorMessage}
                </div>
            )}

            {/* Rating */}
            <div className="space-y-4 flex flex-col items-center">
                <Label className="text-lg font-medium text-foreground">{t("rating")}</Label>
                <div data-testid="rating-stars-input" className="flex items-center gap-3">
                    {[1, 2, 3, 4, 5].map((star) => (
                        <button
                            key={star}
                            type="button"
                            aria-label={t("rateStars", { count: star, defaultValue: `Rate ${star} stars` })}
                            onClick={() => setRating(star)}
                            onMouseEnter={() => setHoverRating(star)}
                            onMouseLeave={() => setHoverRating(0)}
                            className="focus:outline-none transition-transform hover:scale-110 p-1"
                        >
                            <Star
                                className={`h-10 w-10 transition-all duration-200 ${
                                    star <= (hoverRating || rating)
                                        ? "fill-amber-400 text-amber-400 drop-shadow-sm"
                                        : "text-muted-foreground/30 hover:text-amber-200"
                                }`}
                                strokeWidth={1.5}
                            />
                        </button>
                    ))}
                </div>
            </div>

            {/* Comment */}
            <div className="space-y-3">
                <Label htmlFor="comment" className="text-base font-medium">{t("comment")}</Label>
                <Textarea
                    id="comment"
                    data-testid="review-comment-input"
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    placeholder={t("shareExperience")}
                    className="min-h-[120px] resize-none text-base bg-background border-input focus:ring-primary/20 transition-shadow"
                />
            </div>

            {/* Images */}
            <div className="space-y-3">
                <Label className="text-base font-medium">{t("addPhotos")}</Label>
                <div className="flex flex-wrap gap-4">
                    {previewImages.map((src, index) => (
                        <div key={index} className="relative w-24 h-24 border border-border rounded-xl overflow-hidden group shadow-none">
                            <img src={src} alt={t("preview")} className="w-full h-full object-cover transition-transform group-hover:scale-105" />
                            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-[1px]">
                                <button
                                    type="button"
                                    onClick={() => removeImage(index)}
                                    className="bg-destructive text-destructive-foreground p-1.5 rounded-full hover:bg-destructive/90 transition-colors shadow-none"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                    <label className="flex flex-col items-center justify-center w-24 h-24 border-2 border-dashed rounded-xl cursor-pointer hover:bg-muted/50 hover:border-primary/50 transition-all group bg-muted/10">
                        <ImageIcon className="h-8 w-8 text-muted-foreground/70 group-hover:text-primary transition-colors mb-1" />
                        <span className="text-[10px] uppercase tracking-wider font-medium text-muted-foreground group-hover:text-primary transition-colors">{t("add")}</span>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            className="hidden"
                            onChange={handleImageChange}
                        />
                    </label>
                </div>
            </div>

            <div className="pt-4">
                <Button
                    data-testid="review-submit-button"
                    type="submit"
                    disabled={isPending}
                    className="w-full h-12 md:h-12 text-base font-medium shadow-none transition-colors active:scale-[0.99]"
                    size="lg"
                >
                    {isPending ? (
                        <span className="flex items-center gap-2">
                            <LoaderIcon className="inline h-4 w-4 animate-spin" /> {t("submitting")}
                        </span>
                    ) : t("submitReview")}
                </Button>
            </div>
        </form>
    );
}
