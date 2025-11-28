import React from "react";
import { Button } from "@/components/ui/button";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Separator } from "@/components/ui/separator";
import { Check, Minus, Plus, ShoppingCart, Heart } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Product } from "@/types";
import { Spinner } from "@/components/ui/spinner";

interface ProductOptionsProps {
    product: Product;
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
}: ProductOptionsProps) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            {/* Variants Selection */}
            {product.variants && product.variants.length > 0 && (
                <div className="space-y-3">
                    <label className="text-lg font-semibold">
                        {t("selectSize")}
                    </label>
                    <RadioGroup
                        value={selectedVariant?.toString()}
                        onValueChange={(value) => setSelectedVariant(parseInt(value))}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            {product.variants.map((variant) => (
                                <label
                                    key={variant.id}
                                    className={`flex items-center justify-between p-4 border-2 rounded-xl cursor-pointer transition-all ${selectedVariant === variant.id
                                        ? "border-primary bg-primary/5 shadow-sm"
                                        : "border-border hover:border-primary/50"
                                        }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <RadioGroupItem
                                            value={variant.id.toString()}
                                            className="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                        />
                                        <div>
                                            <div className="font-medium">
                                                {variant.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                +{(variant.price ?? product.price).toFixed(2)} {t("currency")}
                                            </div>
                                        </div>
                                    </div>
                                    {selectedVariant === variant.id && (
                                        <Check className="h-5 w-5 text-primary animate-in zoom-in duration-200" />
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
                                        className={`flex items-center justify-between p-4 border-2 rounded-xl transition-all ${isSelected
                                            ? "border-primary bg-primary/5 shadow-sm"
                                            : "border-border hover:border-primary/30"
                                            }`}
                                    >
                                        <label className="flex items-center gap-3 flex-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => handleExtraToggle(item.id)}
                                                className="w-5 h-5 rounded border-primary text-primary focus:ring-primary"
                                            />
                                            <div className="flex-1">
                                                <div className="font-medium">
                                                    {item.name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    +{item.price.toFixed(2)} {t("currency")}
                                                    {item.allow_quantity && isSelected && (
                                                        <span className="ltr:ml-2 rtl:mr-2 font-semibold text-primary">
                                                            × {qty}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </label>
                                        {isSelected && item.allow_quantity && (
                                            <div className="flex items-center gap-2 animate-in fade-in slide-in-from-right-5 duration-200">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="h-8 w-8 rounded-full"
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
                                                    className="h-8 w-8 rounded-full"
                                                    onClick={() => handleExtraQuantityChange(item.id, 1)}
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        )}
                                        {isSelected && !item.allow_quantity && (
                                            <Check className="h-5 w-5 text-primary animate-in zoom-in duration-200" />
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
            <div className="flex flex-col sm:flex-row gap-4 pt-4">
                {/* Quantity Selector */}
                <div className="w-full sm:w-auto">
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
                                    className={`flex items-center justify-center px-4 py-3 rounded-xl border-2 cursor-pointer transition-all ${selectedWeightValue === value.id
                                        ? "border-primary bg-primary/10 font-semibold text-primary"
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
                        <div className="flex items-center justify-between sm:justify-start bg-muted/50 border border-border rounded-xl p-1 w-full sm:w-auto">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-10 w-10 rounded-lg hover:bg-background shadow-sm"
                                onClick={() => handleQuantityChange(-1)}
                                disabled={quantity <= 1}
                            >
                                <Minus className="h-4 w-4" />
                            </Button>
                            <div className="px-4 py-2 text-center min-w-[60px] flex-1 sm:flex-none">
                                <div className="text-lg font-bold">
                                    {quantity}
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-10 w-10 rounded-lg hover:bg-background shadow-sm"
                                onClick={() => handleQuantityChange(1)}
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    )}
                </div>

                {/* Actions Wrapper */}
                <div className="flex flex-1 gap-4">
                    {/* Add to Cart Button */}
                    <Button
                        size="lg"
                        className="flex-1 text-lg gap-2 h-12 rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 transition-all"
                        onClick={handleAddToCart}
                        disabled={isAddingToCart}

                    >
                        <ShoppingCart className="h-5 w-5" />
                        {isAddingToCart ? t("adding") : t("addToCart")}
                        {
                            isAddingToCart && <Spinner />
                        }
                    </Button>

                    {/* Favorite Button */}
                    <Button
                        size="lg"
                        variant="outline"
                        className="h-12 w-12 rounded-xl border-2 shrink-0"
                        onClick={handleToggleFavorite}
                    >
                        <Heart
                            className={`h-6 w-6 transition-colors ${isFavorite
                                ? "fill-red-500 text-red-500"
                                : "text-muted-foreground"
                                }`}
                        />
                    </Button>
                </div>
            </div>
        </div>
    );
}
