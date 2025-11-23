import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import Navigation from "@/themes/default/components/Navigation";
import Footer from "@/themes/default/components/Footer";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";
import {
    Star,
    Heart,
    ShoppingCart,
    Minus,
    Plus,
    Share2,
    Check,
    ChevronLeft,
    Package,
    Clock,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product, Review, ExtraOptionItem, ProductVariant, WeightOptionValue } from "@/types";
import { addToCart, ExtraWithQuantity } from "@/utils/cartUtils";

interface RelatedProduct {
    id: number;
    name: string;
    description: string;
    image?: string;
    price: number;
    base_price: number;
    price_after_discount?: number;
    category?: string;
    rating?: number;
}

interface ProductShowProps {
    product: Product & {
        variants?: ProductVariant[];
        extraOption?: {
            id: number;
            name: string;
            description?: string;
            items: ExtraOptionItem[];
        };
    };
    reviews: Review[];
    relatedProducts: RelatedProduct[];
    promotionalProducts: RelatedProduct[];
}

export default function ProductShow({
    product,
    reviews,
    relatedProducts,
    promotionalProducts,
}: ProductShowProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    // For weight-based products, use first weight option value or default, otherwise use 1
    const defaultQuantity = product.sell_by_weight && product.weight_option?.values?.[0]
        ? parseFloat(product.weight_option.values[0].value)
        : 1;
    const [quantity, setQuantity] = useState(defaultQuantity);
    const [selectedWeightValue, setSelectedWeightValue] = useState<number | null>(
        product.weight_option?.values?.[0]?.id || null
    );
    const [selectedVariant, setSelectedVariant] = useState<number | null>(
        product.variants?.[0]?.id || null
    );
    const [selectedExtras, setSelectedExtras] = useState<Map<number, number>>(
        new Map(
            product.extraOption?.items
                .filter((item) => item.is_default)
                .map((item) => [item.id, 1]) || []
        )
    );
    const [isFavorite, setIsFavorite] = useState(false);

    // Calculate total price
    const calculateTotalPrice = () => {
        let basePrice = product.price_after_discount ?? product.base_price;

        // Add variant price if selected
        if (selectedVariant && product.variants) {
            const variant = product.variants.find(
                (v) => v.id === selectedVariant
            );
            if (variant) {
                basePrice = variant.price;
            }
        }

        // Calculate extras total
        let extrasTotal = 0;
        if (product.extraOption?.items) {
            selectedExtras.forEach((qty, extraId) => {
                const extra = product.extraOption!.items.find(
                    (item) => item.id === extraId
                );
                if (extra) {
                    extrasTotal += extra.price * qty;
                }
            });
        }

        // For weight-based products: (price * quantity) + (extrasTotal * 1)
        // For regular products: (price + extrasTotal) * quantity
        if (product.sell_by_weight) {
            return (basePrice * quantity) + extrasTotal;
        } else {
            return (basePrice + extrasTotal) * quantity;
        }
    };

    const handleQuantityChange = (delta: number) => {
        if (product.sell_by_weight && product.weight_option?.values) {
            // For weight-based products with discrete values
            const currentIndex = product.weight_option.values.findIndex(
                (v) => v.id === selectedWeightValue
            );
            const newIndex = currentIndex + delta;
            if (newIndex >= 0 && newIndex < product.weight_option.values.length) {
                const newValue = product.weight_option.values[newIndex];
                setSelectedWeightValue(newValue.id);
                setQuantity(parseFloat(newValue.value));
            }
        } else {
            // For regular products, increment/decrement by 1
            const newQty = quantity + delta;
            setQuantity(Math.max(1, newQty));
        }
    };

    const handleExtraToggle = (extraId: number) => {
        if (!product.extraOption) return;

        const newExtras = new Map(selectedExtras);

        if (newExtras.has(extraId)) {
            newExtras.delete(extraId);
        } else {
            // Check if max selections is reached
            if (product.extraOption.max_selections && newExtras.size >= product.extraOption.max_selections) {
                // If not allow_multiple, replace the existing selection
                if (!product.extraOption.allow_multiple) {
                    newExtras.clear();
                    newExtras.set(extraId, 1);
                }
                return;
            }
            newExtras.set(extraId, 1);
        }

        setSelectedExtras(newExtras);
    };

    const handleExtraQuantityChange = (extraId: number, delta: number) => {
        const extraItem = product.extraOption?.items.find(item => item.id === extraId);
        if (!extraItem || !extraItem.allow_quantity) return;

        const newExtras = new Map(selectedExtras);
        const currentQty = newExtras.get(extraId) || 0;
        const newQty = Math.max(1, currentQty + delta);

        newExtras.set(extraId, newQty);
        setSelectedExtras(newExtras);
    };

    const [isAddingToCart, setIsAddingToCart] = useState(false);

    const handleAddToCart = async () => {
        setIsAddingToCart(true);

        const extrasArray: ExtraWithQuantity[] = Array.from(selectedExtras.entries()).map(
            ([id, quantity]) => ({ id, quantity })
        );

        await addToCart(
            {
                product_id: product.id,
                variant_id: selectedVariant,
                weight_option_value_id: selectedWeightValue,
                quantity: quantity.toString(),
                extras: extrasArray,
            },
            {
                onSuccess: () => {
                    // Reset form to default state
                    const defaultQty = product.sell_by_weight && product.weight_option?.values?.[0]
                        ? parseFloat(product.weight_option.values[0].value)
                        : 1;
                    setQuantity(defaultQty);
                    setSelectedWeightValue(product.weight_option?.values?.[0]?.id || null);
                    setSelectedExtras(
                        new Map(
                            product.extraOption?.items
                                .filter((item) => item.is_default)
                                .map((item) => [item.id, 1]) || []
                        )
                    );
                },
                onFinally: () => {
                    setIsAddingToCart(false);
                },
            }
        );
    };

    const handleToggleFavorite = () => {
        // TODO: Implement favorite toggle API call
        setIsFavorite(!isFavorite);
    };

    const hasDiscount =
        product.price_after_discount &&
        product.price_after_discount < product.base_price;
    const discountPercentage = hasDiscount
        ? Math.round(
              ((product.base_price - product.price_after_discount!) /
                  product.base_price) *
                  100
          )
        : 0;

    return (
        <>
            <Head title={product.name} />
            <div className="min-h-screen bg-background">
                <Navigation categories={[]} cartItemsCount={0} />

                <main className="container mx-auto px-4 py-8">
                    {/* Breadcrumb */}
                    <div className="flex items-center gap-2 text-sm text-muted-foreground mb-6">
                        <Link
                            href="/"
                            className="hover:text-foreground transition-colors"
                        >
                            {t("home")}
                        </Link>
                        <span>/</span>
                        {product.category && (
                            <>
                                <Link
                                    href={`/categories/${product.category.id}`}
                                    className="hover:text-foreground transition-colors"
                                >
                                    {product.category.name}
                                </Link>
                                <span>/</span>
                            </>
                        )}
                        <span className="text-foreground">{product.name}</span>
                    </div>

                    {/* Product Details Section */}
                    <div className="grid lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
                        {/* Product Image */}
                        <div className="space-y-4">
                            <div className="aspect-square rounded-2xl overflow-hidden bg-gradient-to-br from-primary/5 to-secondary/5 relative">
                                {product.image ? (
                                    <img
                                        src={product.image}
                                        alt={product.name}
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <div className="text-9xl">🍽️</div>
                                    </div>
                                )}

                                {/* Discount Badge */}
                                {hasDiscount && (
                                    <Badge className="absolute top-4 ltr:left-4 rtl:right-4 text-lg px-4 py-2">
                                        {discountPercentage}% {t("off")}
                                    </Badge>
                                )}

                                {/* Share Button */}
                                <Button
                                    size="icon"
                                    variant="secondary"
                                    className="absolute top-4 ltr:right-4 rtl:left-4"
                                >
                                    <Share2 className="h-5 w-5" />
                                </Button>
                            </div>
                        </div>

                        {/* Product Info */}
                        <div className="space-y-6">
                            {/* Category Badge */}
                            {product.category && (
                                <Badge variant="outline" className="text-sm">
                                    {product.category.name}
                                </Badge>
                            )}

                            {/* Product Name */}
                            <h1 className="text-4xl md:text-5xl font-bold tracking-tight">
                                {product.name}
                            </h1>

                            {/* Rating */}
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-1">
                                    {[...Array(5)].map((_, i) => (
                                        <Star
                                            key={i}
                                            className={`h-5 w-5 ${
                                                i <
                                                Math.floor(product.rating || 0)
                                                    ? "fill-yellow-400 text-yellow-400"
                                                    : "text-gray-300"
                                            }`}
                                        />
                                    ))}
                                </div>
                                <span className="text-lg font-medium">
                                    {product.rating}
                                </span>
                                <span className="text-muted-foreground">
                                    ({product.reviewsCount} {t("reviews")})
                                </span>
                            </div>

                            {/* Price */}
                            <div className="flex items-center gap-4">
                                <div>
                                    <div className="text-4xl font-bold">
                                        {calculateTotalPrice().toFixed(2)}{" "}
                                        {t("currency")}
                                    </div>
                                    {product.sell_by_weight && (
                                        <div className="text-sm text-muted-foreground mt-1">
                                            {(product.price_after_discount ?? product.base_price).toFixed(2)} {t("currency")} {t("perKg")}
                                        </div>
                                    )}
                                </div>
                                {hasDiscount && (
                                    <div className="text-2xl text-muted-foreground line-through">
                                        {product.base_price.toFixed(2)}{" "}
                                        {t("currency")}
                                    </div>
                                )}
                            </div>

                            {/* Description */}
                            <p className="text-lg text-muted-foreground leading-relaxed">
                                {product.description}
                            </p>

                            <Separator />

                            {/* Variants Selection */}
                            {product.variants &&
                                product.variants.length > 0 && (
                                    <div className="space-y-3">
                                        <label className="text-lg font-semibold">
                                            {t("selectSize")}
                                        </label>
                                        <RadioGroup
                                            value={selectedVariant?.toString()}
                                            onValueChange={(value) =>
                                                setSelectedVariant(
                                                    parseInt(value)
                                                )
                                            }
                                        >
                                            <div className="grid grid-cols-2 gap-3">
                                                {product.variants.map(
                                                    (variant) => (
                                                        <label
                                                            key={variant.id}
                                                            className={`flex items-center justify-between p-4 border-2 rounded-lg cursor-pointer transition-all ${
                                                                selectedVariant ===
                                                                variant.id
                                                                    ? "border-primary bg-primary/5"
                                                                    : "border-border hover:border-primary/50"
                                                            }`}
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                <RadioGroupItem
                                                                    value={variant.id.toString()}
                                                                />
                                                                <div>
                                                                    <div className="font-medium">
                                                                        {
                                                                            variant.name
                                                                        }
                                                                    </div>
                                                                    <div className="text-sm text-muted-foreground">
                                                                        +
                                                                        {variant.price.toFixed(
                                                                            2
                                                                        )}{" "}
                                                                        {t(
                                                                            "currency"
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            {selectedVariant ===
                                                                variant.id && (
                                                                <Check className="h-5 w-5 text-primary" />
                                                            )}
                                                        </label>
                                                    )
                                                )}
                                            </div>
                                        </RadioGroup>
                                    </div>
                                )}

                            {/* Extra Options */}
                            {product.extraOption &&
                                product.extraOption.items.length > 0 && (
                                    <>
                                        <div className="space-y-3">
                                            <div>
                                                <label className="text-lg font-semibold">
                                                    {product.extraOption.name}
                                                </label>
                                                {product.extraOption.description && (
                                                    <p className="text-sm text-muted-foreground mt-1">
                                                        {product.extraOption.description}
                                                    </p>
                                                )}
                                                {(product.extraOption.min_selections > 0 || product.extraOption.max_selections) && (
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {product.extraOption.min_selections > 0 && product.extraOption.max_selections
                                                            ? t("selectBetween", {
                                                                  min: product.extraOption.min_selections,
                                                                  max: product.extraOption.max_selections,
                                                              })
                                                            : product.extraOption.min_selections > 0
                                                            ? t("selectAtLeast", { min: product.extraOption.min_selections })
                                                            : t("selectUpTo", { max: product.extraOption.max_selections })}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                {product.extraOption.items.map((item) => {
                                                    const isSelected = selectedExtras.has(item.id);
                                                    const qty = selectedExtras.get(item.id) || 1;

                                                    return (
                                                        <div
                                                            key={item.id}
                                                            className={`flex items-center justify-between p-4 border-2 rounded-lg transition-all ${
                                                                isSelected
                                                                    ? "border-primary bg-primary/5"
                                                                    : "border-border"
                                                            }`}
                                                        >
                                                            <label className="flex items-center gap-3 flex-1 cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={isSelected}
                                                                    onChange={() => handleExtraToggle(item.id)}
                                                                    className="w-5 h-5 rounded accent-primary"
                                                                />
                                                                <div className="flex-1">
                                                                    <div className="font-medium">
                                                                        {item.name}
                                                                    </div>
                                                                    <div className="text-sm text-muted-foreground">
                                                                        +{item.price.toFixed(2)} {t("currency")}
                                                                        {item.allow_quantity && isSelected && (
                                                                            <span className="ltr:ml-2 rtl:mr-2">
                                                                                × {qty}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </label>
                                                            {isSelected && item.allow_quantity && (
                                                                <div className="flex items-center gap-2">
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="icon"
                                                                        className="h-8 w-8"
                                                                        onClick={() => handleExtraQuantityChange(item.id, -1)}
                                                                        disabled={qty <= 1}
                                                                    >
                                                                        <Minus className="h-3 w-3" />
                                                                    </Button>
                                                                    <span className="w-8 text-center font-medium">{qty}</span>
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="icon"
                                                                        className="h-8 w-8"
                                                                        onClick={() => handleExtraQuantityChange(item.id, 1)}
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                    </Button>
                                                                </div>
                                                            )}
                                                            {isSelected && !item.allow_quantity && (
                                                                <Check className="h-5 w-5 text-primary" />
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                        <Separator />
                                    </>
                                )}

                            {/* Quantity & Add to Cart */}
                            <div className="flex flex-col sm:flex-row gap-4">
                                {/* Quantity Selector */}
                                {product.sell_by_weight && product.weight_option?.values ? (
                                    /* Weight Options Selector */
                                    <RadioGroup
                                        value={selectedWeightValue?.toString()}
                                        onValueChange={(value) => {
                                            const valueId = parseInt(value);
                                            const weightValue = product.weight_option!.values.find(
                                                (v) => v.id === valueId
                                            );
                                            if (weightValue) {
                                                setSelectedWeightValue(valueId);
                                                setQuantity(parseFloat(weightValue.value));
                                            }
                                        }}
                                        className="flex flex-wrap gap-2"
                                    >
                                        {product.weight_option.values.map((value) => (
                                            <label
                                                key={value.id}
                                                htmlFor={`weight-${value.id}`}
                                                className={`flex items-center justify-center px-4 py-2 rounded-lg border-2 cursor-pointer transition-all ${
                                                    selectedWeightValue === value.id
                                                        ? "border-primary bg-primary/10 font-semibold"
                                                        : "border-border hover:border-primary/50"
                                                }`}
                                            >
                                                <RadioGroupItem
                                                    value={value.id.toString()}
                                                    id={`weight-${value.id}`}
                                                    className="sr-only"
                                                />
                                                <span>
                                                    {value.label || `${value.value} ${product.weight_option!.unit}`}
                                                </span>
                                            </label>
                                        ))}
                                    </RadioGroup>
                                ) : (
                                    /* Regular Quantity Selector */
                                    <div className="flex items-center shadow px-2 rounded-lg">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleQuantityChange(-1)}
                                            disabled={quantity <= 1}
                                        >
                                            <Minus className="h-4 w-4" />
                                        </Button>
                                        <div className="px-4 py-2 text-center">
                                            <div className="text-lg font-semibold min-w-[80px]">
                                                {quantity}
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleQuantityChange(1)}
                                        >
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    </div>
                                )}

                                {/* Add to Cart Button */}
                                <Button
                                    size="lg"
                                    className="flex-1 text-lg gap-2"
                                    onClick={handleAddToCart}
                                    disabled={isAddingToCart}
                                >
                                    <ShoppingCart className="h-5 w-5" />
                                    {isAddingToCart ? t("adding") : t("addToCart")}
                                </Button>

                                {/* Favorite Button */}
                                <Button
                                    size="lg"
                                    variant="outline"
                                    onClick={handleToggleFavorite}
                                >
                                    <Heart
                                        className={`h-5 w-5 ${
                                            isFavorite
                                                ? "fill-red-500 text-red-500"
                                                : ""
                                        }`}
                                    />
                                </Button>
                            </div>

                            {/* Features */}
                            <div className="grid grid-cols-2 gap-4 pt-4">
                                <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
                                    <Package className="h-6 w-6 text-primary" />
                                    <div>
                                        <div className="font-semibold text-sm">
                                            {t("freshIngredients")}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {t("qualityGuaranteed")}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
                                    <Clock className="h-6 w-6 text-primary" />
                                    <div>
                                        <div className="font-semibold text-sm">
                                            {t("fastDelivery")}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {t("30MinOrLess")}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tabs Section */}
                    <Tabs
                        defaultValue="reviews"
                        className="mb-16"
                        dir={isRTL ? "rtl" : "ltr"}
                    >
                        <TabsList className="grid w-full max-w-md grid-cols-2">
                            <TabsTrigger value="reviews">
                                {t("reviews")}
                            </TabsTrigger>
                            <TabsTrigger value="details">
                                {t("details")}
                            </TabsTrigger>
                        </TabsList>

                        {/* Reviews Tab */}
                        <TabsContent value="reviews" className="space-y-6 mt-8">
                            <div className="flex items-center justify-between">
                                <h3 className="text-2xl font-bold">
                                    {t("customerReviews")} ({reviews.length})
                                </h3>
                                <Button variant="outline">
                                    {t("writeReview")}
                                </Button>
                            </div>

                            <div className="space-y-4">
                                {reviews.map((review) => (
                                    <Card key={review.id}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="space-y-2">
                                                    <div className="font-semibold">
                                                        {review.user_name}
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        {[...Array(5)].map(
                                                            (_, i) => (
                                                                <Star
                                                                    key={i}
                                                                    className={`h-4 w-4 ${
                                                                        i <
                                                                        review.rating
                                                                            ? "fill-yellow-400 text-yellow-400"
                                                                            : "text-gray-300"
                                                                    }`}
                                                                />
                                                            )
                                                        )}
                                                    </div>
                                                </div>
                                                <span className="text-sm text-muted-foreground">
                                                    {new Date(
                                                        review.created_at
                                                    ).toLocaleDateString()}
                                                </span>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-muted-foreground">
                                                {review.comment}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </TabsContent>

                        {/* Details Tab */}
                        <TabsContent value="details" className="space-y-6 mt-8">
                            <Card>
                                <CardHeader>
                                    <h3 className="text-xl font-bold">
                                        {t("productDetails")}
                                    </h3>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-3">
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("category")}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {product.category?.name ||
                                                    t("uncategorized")}
                                            </span>
                                        </div>
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("basePrice")}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {product.base_price.toFixed(2)}{" "}
                                                {t("currency")}
                                            </span>
                                        </div>
                                        {product.sell_by_weight && (
                                            <div className="flex justify-between py-2 border-b">
                                                <span className="font-medium">
                                                    {t("soldBy")}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t("weight")}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex justify-between py-2 border-b">
                                            <span className="font-medium">
                                                {t("availability")}
                                            </span>
                                            <Badge
                                                variant={
                                                    product.is_active
                                                        ? "default"
                                                        : "secondary"
                                                }
                                            >
                                                {product.is_active
                                                    ? t("available")
                                                    : t("unavailable")}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>

                    {/* Related Products */}
                    {relatedProducts.length > 0 && (
                        <section className="mb-16">
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-3xl font-bold">
                                    {t("similarProducts")}
                                </h2>
                                <Link
                                    href={`/categories/${product.category?.id}`}
                                >
                                    <Button variant="outline">
                                        {t("viewAll")}
                                    </Button>
                                </Link>
                            </div>
                            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                {relatedProducts.map((relatedProduct) => (
                                    <ProductCard
                                        key={relatedProduct.id}
                                        product={relatedProduct}
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    {/* Promotional Products */}
                    {promotionalProducts.length > 0 && (
                        <section>
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-3xl font-bold">
                                    {t("specialOffers")}
                                </h2>
                                <Link href="/promotions">
                                    <Button variant="outline">
                                        {t("viewAll")}
                                    </Button>
                                </Link>
                            </div>
                            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                {promotionalProducts.map((promoProduct) => (
                                    <ProductCard
                                        key={promoProduct.id}
                                        product={promoProduct}
                                    />
                                ))}
                            </div>
                        </section>
                    )}
                </main>

                <Footer />
            </div>
        </>
    );
}

// Product Card Component for Related/Promotional Products
function ProductCard({ product }: { product: RelatedProduct }) {
    const { t } = useTranslation();
    const [isAddingToCart, setIsAddingToCart] = useState(false);
    const hasDiscount =
        product.price_after_discount &&
        product.price_after_discount < product.base_price;

    const handleAddToCart = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsAddingToCart(true);

        await addToCart(
            {
                product_id: product.id,
                variant_id: null,
                quantity: "1",
                extras: [],
            },
            {
                onFinally: () => {
                    setIsAddingToCart(false);
                },
            }
        );
    };

    return (
        <Link href={`/products/${product.id}`}>
            <Card className="group overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                <div className="aspect-square bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden">
                    {product.image ? (
                        <img
                            src={product.image}
                            alt={product.name}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="text-6xl">🍽️</div>
                        </div>
                    )}
                    {hasDiscount && (
                        <Badge className="absolute top-2 ltr:left-2 rtl:right-2">
                            {Math.round(
                                ((product.base_price -
                                    product.price_after_discount!) /
                                    product.base_price) *
                                    100
                            )}
                            % {t("off")}
                        </Badge>
                    )}
                </div>
                <CardHeader className="pb-3">
                    <h3 className="font-semibold text-lg leading-tight line-clamp-1">
                        {product.name}
                    </h3>
                    <p className="text-sm text-muted-foreground line-clamp-2">
                        {product.description}
                    </p>
                </CardHeader>
                <CardFooter className="flex items-center justify-between pt-0">
                    <div className="space-y-1">
                        <div className="text-2xl font-bold">
                            {product.price.toFixed(2)} {t("currency")}
                        </div>
                        {hasDiscount && (
                            <div className="text-sm text-muted-foreground line-through">
                                {product.base_price.toFixed(2)} {t("currency")}
                            </div>
                        )}
                        {product.rating && (
                            <div className="flex items-center gap-1 text-sm">
                                <Star className="h-3.5 w-3.5 fill-yellow-400 text-yellow-400" />
                                <span className="font-medium">
                                    {product.rating}
                                </span>
                            </div>
                        )}
                    </div>
                    <Button
                        size="icon"
                        className="shrink-0"
                        onClick={handleAddToCart}
                        disabled={isAddingToCart}
                    >
                        <ShoppingCart className="h-4 w-4" />
                    </Button>
                </CardFooter>
            </Card>
        </Link>
    );
}
