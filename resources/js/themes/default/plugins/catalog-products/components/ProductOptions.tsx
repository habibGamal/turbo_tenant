import React from "react";
import { Button } from "@/components/ui/button";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Separator } from "@/components/ui/separator";
import { Check, Minus, Plus, ShoppingCart, Heart, LoaderIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product } from "@/types";
import { NormalizedProduct } from "@/plugins/catalog-products/types";
import { getEffectivePrice } from "@/lib/pricing";
import { useFormatMoney } from "@/lib/money";
import { formatWeightLabel } from "@/lib/weight";

interface ProductOptionsProps {
    product: NormalizedProduct | Product;
    selectedVariant: number | null;
    setSelectedVariant: (id: number) => void;
    selectedExtras: Map<number, number>;
    handleExtraToggle: (id: number) => void;
    handleExtraQuantityChange: (id: number, delta: number) => void;
    quantity: number;
    handleQuantityChange: (delta: number) => void;
    selectedWeightValue: number | null;
    setSelectedWeightValue: (id: number) => void;
    setQuantity: (qty: number) => void;
    handleAddToCart: () => void;
    isAddingToCart: boolean;
    isFavorite: boolean;
    handleToggleFavorite: () => void;
    canAddToCart: boolean;
    missingRequiredSelections: boolean;
    limitReachedFor?: number | boolean | null;
    totalPrice?: number;
    hideActions?: boolean;
}

export default function ProductOptions({
    product,
    selectedVariant,
    setSelectedVariant,
    selectedExtras,
    handleExtraToggle,
    handleExtraQuantityChange,
    quantity,
    handleQuantityChange,
    selectedWeightValue,
    setSelectedWeightValue,
    setQuantity,
    handleAddToCart,
    isAddingToCart,
    isFavorite,
    handleToggleFavorite,
    canAddToCart,
    missingRequiredSelections,
    limitReachedFor,
    totalPrice,
    hideActions = false,
}: ProductOptionsProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();

    const isMissingRequired = missingRequiredSelections;
    const isCanAddToCart = canAddToCart;
    const isLimitReached = (limitReachedFor === product.extraOption?.id || limitReachedFor === true) && Boolean(product.extraOption?.max_selections);

    const defaultProductPrice = getEffectivePrice(product);

    const calculateRunningTotal = () => {
        if (totalPrice !== undefined) return totalPrice;

        let basePrice = getEffectivePrice(product);
        if (selectedVariant && product.variants) {
            const variant = product.variants.find((v) => v.id === selectedVariant);
            if (variant && variant.price !== null && variant.price !== undefined) {
                basePrice = variant.price;
            }
        }

        let extrasTotal = 0;
        if (product.extraOption?.items) {
            selectedExtras.forEach((qty, extraId) => {
                const extra = product.extraOption!.items.find((item) => item.id === extraId);
                if (extra) {
                    extrasTotal += extra.price * qty;
                }
            });
        }

        if (product.sell_by_weight) {
            return (basePrice * quantity) + extrasTotal;
        } else {
            return (basePrice + extrasTotal) * quantity;
        }
    };

    const runningTotal = calculateRunningTotal();

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };
    return (
        <div className="space-y-6">
            {/* Variants Selection */}
            {product.variants && product.variants.length > 0 && (
                <div className="space-y-3">
                    <label className="text-base sm:text-lg font-semibold ">
                        {t("selectSize")}
                    </label>
                    <RadioGroup
                        value={selectedVariant?.toString()}
                        onValueChange={(value) => setSelectedVariant(parseInt(value))}
                        dir={i18n.dir()}
                    >
                        <div className={`grid grid-cols-1 ${product.variants.length === 3 ? "sm:grid-cols-3" : "sm:grid-cols-2"} gap-2.5 sm:gap-3 mt-2`}>
                            {product.variants.map((variant) => (
                                <label
                                    key={variant.id}
                                    data-testid="product-variant-option"
                                    className={`flex items-center justify-between p-3 sm:p-4 border-2 rounded-xl cursor-pointer transition-all gap-2 ${selectedVariant === variant.id
                                        ? "border-primary bg-primary/5 shadow-sm"
                                        : "border-border hover:border-primary/50"
                                        }`}
                                >
                                    <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
                                        <RadioGroupItem
                                            value={variant.id.toString()}
                                            className="data-[state=checked]:bg-primary data-[state=checked]:border-primary shrink-0"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="font-medium text-sm sm:text-base truncate">
                                                {getText(variant.name, variant.name_ar)}
                                            </div>
                                            <div className="text-xs sm:text-sm text-muted-foreground">
                                                {formatMoney((variant.price !== null && variant.price !== undefined && variant.price > 0)
                                                    ? variant.price
                                                    : defaultProductPrice)}
                                            </div>
                                        </div>
                                    </div>
                                    {selectedVariant === variant.id && (
                                        <Check className="h-4 w-4 sm:h-5 sm:w-5 text-primary shrink-0 animate-in zoom-in duration-200" />
                                    )}
                                </label>
                            ))}
                        </div>
                    </RadioGroup>
                </div>
            )}

            {/* Extra Options */}
            {product.extraOption && product.extraOption.items.length > 0 && (
                <>
                    <Separator />
                    <div className="space-y-3">
                        <div>
                            <label className="text-base sm:text-lg font-semibold">
                                {getText(product.extraOption.name, product.extraOption.name_ar)}
                            </label>
                            {product.extraOption.description && (
                                <p className="text-xs sm:text-sm text-muted-foreground mt-1">
                                    {product.extraOption.description}
                                </p>
                            )}
                            {isMissingRequired ? (
                                <p className="text-xs text-destructive font-medium mt-1">
                                    {t("selectAtLeast", { min: product.extraOption.min_selections ?? 0 })}
                                </p>
                            ) : (
                                ((product.extraOption.min_selections ?? 0) > 0 || Boolean(product.extraOption.max_selections)) && (
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {(product.extraOption.min_selections ?? 0) > 0 && product.extraOption.max_selections
                                            ? t("selectBetween", {
                                                min: product.extraOption.min_selections ?? 0,
                                                max: product.extraOption.max_selections,
                                            })
                                            : (product.extraOption.min_selections ?? 0) > 0
                                                ? t("selectAtLeast", { min: product.extraOption.min_selections ?? 0 })
                                                : t("selectUpTo", { max: product.extraOption.max_selections })}
                                    </p>
                                )
                            )}
                        </div>
                        <div className="space-y-2">
                            {product.extraOption.items.map((item) => {
                                const isSelected = selectedExtras.has(item.id);
                                const qty = selectedExtras.get(item.id) || 1;

                                return (
                                    <div
                                        key={item.id}
                                        className={`flex items-center justify-between p-3 sm:p-4 border-2 rounded-xl transition-all gap-2 ${isSelected
                                            ? "border-primary bg-primary/5 shadow-sm"
                                            : "border-border hover:border-primary/30"
                                            }`}
                                    >
                                        <label className="flex items-center gap-2.5 sm:gap-3 flex-1 min-w-0 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                data-testid="extra-option-checkbox"
                                                checked={isSelected}
                                                onChange={() => handleExtraToggle(item.id)}
                                                className="w-4 h-4 sm:w-5 sm:h-5 rounded border-primary text-primary focus:ring-primary shrink-0"
                                            />
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-sm sm:text-base truncate">
                                                    {getText(item.name, item.name_ar)}
                                                </div>
                                                <div className="text-xs sm:text-sm text-muted-foreground flex items-center flex-wrap gap-x-2">
                                                    <span className="inline-flex items-center gap-0.5">
                                                        <span dir="ltr">+</span>
                                                        <span>{formatMoney(item.price)}</span>
                                                    </span>
                                                    {item.allow_quantity && isSelected && (
                                                        <span className="font-semibold text-primary">
                                                            × {qty}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </label>
                                        {isSelected && item.allow_quantity && (
                                            <div className="flex items-center gap-1.5 sm:gap-2 shrink-0 animate-in fade-in duration-200">
                                                <Button
                                                    type="button"
                                                    data-testid="extra-qty-decrement"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={t("decreaseQuantity", "Decrease quantity")}
                                                    className="h-10 w-10 sm:h-8 sm:w-8 rounded-full"
                                                    onClick={() => handleExtraQuantityChange(item.id, -1)}
                                                    disabled={qty <= 1}
                                                >
                                                    <Minus className="h-3.5 w-3.5" />
                                                </Button>
                                                <span className="w-6 sm:w-8 text-center text-xs sm:text-sm font-medium">{qty}</span>
                                                <Button
                                                    type="button"
                                                    data-testid="extra-qty-increment"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={t("increaseQuantity", "Increase quantity")}
                                                    className="h-10 w-10 sm:h-8 sm:w-8 rounded-full"
                                                    onClick={() => handleExtraQuantityChange(item.id, 1)}
                                                >
                                                    <Plus className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        )}
                                        {isSelected && !item.allow_quantity && (
                                            <Check className="h-4 w-4 sm:h-5 sm:w-5 text-primary shrink-0 animate-in zoom-in duration-200" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        {isLimitReached && (
                            <p className="text-xs text-destructive font-medium mt-2">
                                {t("maxSelectionsReached", { max: product.extraOption.max_selections })}
                            </p>
                        )}
                    </div>
                </>
            )}

            {/* Weight Options Selector */}
            {product.sell_by_weight && product.weight_option?.values && (
                <>
                    <Separator />
                    <div className="space-y-3">
                        <label className="text-base sm:text-lg font-semibold">
                            {t("weight", "Weight")}
                        </label>
                        <RadioGroup
                            value={selectedWeightValue?.toString()}
                            onValueChange={(value) => {
                                const valueId = parseInt(value);
                                const weightValue = product.weight_option!.values.find(
                                    (v) => v.id === valueId
                                );
                                if (weightValue) {
                                    setSelectedWeightValue(valueId);
                                    setQuantity(parseFloat(String(weightValue.value)));
                                }
                            }}
                            className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 w-full"
                            aria-label={t("weight", "Weight")}
                        >
                            {product.weight_option.values.map((value) => (
                                <label
                                    key={value.id}
                                    htmlFor={`weight-${value.id}`}
                                    data-testid="product-weight-option"
                                    className={`flex min-h-11 items-center justify-center w-full px-3 py-2.5 rounded-xl border-2 text-center cursor-pointer transition-colors ${selectedWeightValue === value.id
                                        ? "border-primary bg-primary/10 font-semibold text-primary"
                                        : "border-border hover:border-primary/50"
                                        }`}
                                >
                                    <RadioGroupItem
                                        value={value.id.toString()}
                                        id={`weight-${value.id}`}
                                        className="sr-only"
                                    />
                                    <span className="truncate text-xs sm:text-sm font-medium">
                                        {formatWeightLabel(value, product.weight_option!.unit, i18n.language === 'ar')}
                                    </span>
                                </label>
                            ))}
                        </RadioGroup>
                    </div>
                </>
            )}

            {/* Quantity & Add to Cart */}
            {!hideActions && (
                <div className={`flex gap-3 pt-4 ${product.sell_by_weight ? "flex-col" : "flex-col sm:flex-row sm:items-center"}`} >
                    {/* Quantity Selector (for non-weight products) */}
                    {!product.sell_by_weight && (
                        <div className="w-full sm:w-auto shrink-0">
                            <div className="flex h-12 items-center justify-between sm:justify-start bg-muted/50 border border-border rounded-xl px-1 w-full sm:w-fit">
                                <Button
                                    type="button"
                                    data-testid="product-quantity-decrement"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t("decreaseQuantity", "Decrease quantity")}
                                    className="h-10 w-10 md:h-10 md:w-10 rounded-lg hover:bg-background shadow-none"
                                    onClick={() => handleQuantityChange(-1)}
                                    disabled={quantity <= 1}
                                >
                                    <Minus className="h-4 w-4" />
                                </Button>
                                <div className="px-4 text-center min-w-[52px] flex-1 sm:flex-none">
                                    <div data-testid="product-quantity-value" className="text-lg font-bold leading-none">
                                        {quantity}
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    data-testid="product-quantity-increment"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t("increaseQuantity", "Increase quantity")}
                                    className="h-10 w-10 md:h-10 md:w-10 rounded-lg hover:bg-background shadow-none"
                                    onClick={() => handleQuantityChange(1)}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Actions Wrapper */}
                    <div className="flex w-full sm:flex-1 min-w-0 items-center gap-2.5 sm:gap-3">
                        {/* Add to Cart Button */}
                        <Button
                            type="button"
                            size="lg"
                            data-testid="product-detail-add-to-cart"
                            className="flex-1 min-w-0 h-12 md:h-12 px-4 sm:px-6 text-base sm:text-lg gap-2 rounded-xl shadow-none [&_svg]:size-5 transition-colors"
                            onClick={handleAddToCart}
                            disabled={isAddingToCart || !isCanAddToCart}
                        >
                            <ShoppingCart className="h-5 w-5" />
                            {isAddingToCart ? (
                                <>
                                    {t("adding")}
                                    <LoaderIcon className="h-4 w-4 animate-spin" />
                                </>
                            ) : (
                                `${t("addToCart")} · ${formatMoney(runningTotal)}`
                            )}
                        </Button>

                        {/* Favorite Button */}
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            aria-label={
                                isFavorite
                                    ? t("removeFromFavorites", "Remove from favorites")
                                    : t("addToFavorites", "Add to favorites")
                            }
                            aria-pressed={isFavorite}
                            className="h-12 w-12 md:h-12 md:w-12 p-0 rounded-xl border shrink-0 shadow-none [&_svg]:size-5"
                            onClick={handleToggleFavorite}
                        >
                            <Heart
                                className={`h-6 w-6 transition-colors ${isFavorite
                                    ? "fill-destructive text-destructive"
                                    : "text-muted-foreground"
                                    }`}
                            />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
