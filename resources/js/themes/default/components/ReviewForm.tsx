import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import { Star, Upload, X, Image as ImageIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { useTranslation } from "react-i18next";

interface ReviewFormProps {
    productId: number;
    onSuccess?: () => void;
}

export default function ReviewForm({ productId, onSuccess }: ReviewFormProps) {
    const { t, i18n } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        rating: 0,
        comment: "",
        images: [] as File[],
    });
    const [previewImages, setPreviewImages] = useState<string[]>([]);
    const [hoverRating, setHoverRating] = useState(0);

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            setData("images", [...data.images, ...files]);

            const newPreviews = files.map((file) => URL.createObjectURL(file));
            setPreviewImages([...previewImages, ...newPreviews]);
        }
    };

    const removeImage = (index: number) => {
        const newImages = [...data.images];
        newImages.splice(index, 1);
        setData("images", newImages);

        const newPreviews = [...previewImages];
        URL.revokeObjectURL(newPreviews[index]);
        newPreviews.splice(index, 1);
        setPreviewImages(newPreviews);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/products/${productId}/reviews`, {
            onSuccess: () => {
                reset();
                setPreviewImages([]);
                if (onSuccess) onSuccess();
            },
        });
    };

    const isRtl = i18n.language === 'ar';

    return (
        <form onSubmit={submit} className="space-y-8" dir={isRtl ? 'rtl' : 'ltr'}>
            {/* Rating */}
            <div className="space-y-4 flex flex-col items-center">
                <Label className="text-lg font-medium text-foreground">{t("rating")}</Label>
                <div className="flex items-center gap-3">
                    {[1, 2, 3, 4, 5].map((star) => (
                        <button
                            key={star}
                            type="button"
                            onClick={() => setData("rating", star)}
                            onMouseEnter={() => setHoverRating(star)}
                            onMouseLeave={() => setHoverRating(0)}
                            className="focus:outline-none transition-transform hover:scale-110 p-1"
                        >
                            <Star
                                className={`h-10 w-10 transition-all duration-200 ${star <= (hoverRating || data.rating)
                                    ? "fill-yellow-400 text-yellow-400 drop-shadow-sm"
                                    : "text-muted-foreground/30 hover:text-yellow-200"
                                    }`}
                                strokeWidth={1.5}
                            />
                        </button>
                    ))}
                </div>
                {errors.rating && <p className="text-sm text-destructive font-medium animate-in fade-in slide-in-from-top-1">{errors.rating}</p>}
            </div>

            {/* Comment */}
            <div className="space-y-3">
                <Label htmlFor="comment" className="text-base font-medium">{t("comment")}</Label>
                <Textarea
                    id="comment"
                    value={data.comment}
                    onChange={(e) => setData("comment", e.target.value)}
                    placeholder={t("shareExperience")}
                    className="min-h-[120px] resize-none text-base bg-background border-input focus:ring-primary/20 transition-shadow"
                />
                {errors.comment && <p className="text-sm text-destructive font-medium animate-in fade-in slide-in-from-top-1">{errors.comment}</p>}
            </div>

            {/* Images */}
            <div className="space-y-3">
                <Label className="text-base font-medium">{t("addPhotos")}</Label>
                <div className="flex flex-wrap gap-4">
                    {previewImages.map((src, index) => (
                        <div key={index} className="relative w-24 h-24 border rounded-xl overflow-hidden group shadow-sm">
                            <img src={src} alt={t("preview")} className="w-full h-full object-cover transition-transform group-hover:scale-105" />
                            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-[1px]">
                                <button
                                    type="button"
                                    onClick={() => removeImage(index)}
                                    className="bg-destructive text-destructive-foreground p-1.5 rounded-full hover:bg-destructive/90 transition-colors shadow-sm"
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
                {errors.images && <p className="text-sm text-destructive font-medium animate-in fade-in slide-in-from-top-1">{errors.images}</p>}
            </div>

            <div className="pt-4">
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full h-12 text-base font-medium shadow-md hover:shadow-lg transition-all active:scale-[0.99]"
                    size="lg"
                >
                    {processing ? (
                        <span className="flex items-center gap-2">
                            <span className="animate-spin">⏳</span> {t("submitting")}
                        </span>
                    ) : t("submitReview")}
                </Button>
            </div>
        </form>
    );
}
