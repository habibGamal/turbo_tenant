import React from "react";
import { Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ImageWithFallback } from "@/components/ui/image";
import { Separator } from "@/components/ui/separator";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Minus, Plus, X } from "lucide-react";
import { useTranslation } from "react-i18next";
import { CartItem as CartItemType } from "@/types";

interface CartItemProps {
    item: CartItemType;
    isUpdating: boolean;
    onRemove: () => void;
    onIncrement: () => void;
    onDecrement: () => void;
    onWeightChange: (value: string) => void;
    formatCurrency: (amount: number) => string;
}

export default function CartItem({
    item,
    isUpdating,
    onRemove,
    onIncrement,
    onDecrement,
    onWeightChange,
    formatCurrency,
}: CartItemProps) {
    const { t } = useTranslation();

    return (
        <div>
            <div className="flex gap-3 sm:gap-4">
                {/* Product Image */}
                <Link
                    href={route("products.show", item.product_id)}
                    className="shrink-0"
                >
                    <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden">
                        <ImageWithFallback
                            src={item.product?.image}
                            alt={item.product?.name || t("product")}
                            className="w-full h-full object-cover"
                        />
                    </div>
                </Link>

                {/* Product Info */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2 mb-1 sm:mb-2">
                        <div className="flex-1 min-w-0">
                            <Link href={route("products.show", item.product_id)}>
                                <h3 className=" font-semibold text-base sm:text-lg leading-tight  hover:text-primary transition-colors">
                                    {item.product?.name || t("unknownProduct")}
                                </h3>
                            </Link>
                            {item.variant && (
                                <Badge variant="outline" className="mt-1 text-xs">
                                    {item.variant.name}
                                </Badge>
                            )}
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={onRemove}
                            disabled={isUpdating}
                            className="flex-shrink-0 h-8 w-8 -mr-2 -mt-2 sm:mr-0 sm:mt-0 text-muted-foreground hover:text-destructive"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>

                    {/* Extras */}
                    {item.extras.length > 0 && (
                        <div className="text-xs sm:text-sm text-muted-foreground mb-2 space-y-1">
                            {item.extras.map((extra) => (
                                <div key={extra.id} className="flex items-center gap-2">
                                    <span className="text-xs">+</span>
                                    <span>
                                        {extra.name}
                                        {extra.quantity > 1 && (
                                            <span className="ltr:ml-1 rtl:mr-1">
                                                × {extra.quantity}
                                            </span>
                                        )}
                                    </span>
                                    <span className="ltr:ml-auto rtl:mr-auto">
                                        {formatCurrency(extra.price * extra.quantity)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Weight Selection */}
                    {item.product?.sell_by_weight && item.product.weight_option && (
                        <div className="mb-2">
                            <Label className="text-xs text-muted-foreground mb-1 block">
                                {t("weight")}
                            </Label>
                            <Select
                                value={item.weight_option_value_id?.toString() || ""}
                                onValueChange={onWeightChange}
                                disabled={isUpdating}
                            >
                                <SelectTrigger className="w-full h-8 text-xs sm:text-sm">
                                    <SelectValue placeholder={t("selectWeight")} />
                                </SelectTrigger>
                                <SelectContent>
                                    {item.product.weight_option.values.map((weightValue) => (
                                        <SelectItem
                                            key={weightValue.id}
                                            value={weightValue.id.toString()}
                                            className="text-xs sm:text-sm"
                                        >
                                            {weightValue.label ||
                                                `${weightValue.value} ${item.product.weight_option!.unit}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Quantity and Price */}
                    <div className="flex flex-wrap items-end justify-between gap-x-2 gap-y-2 mt-2">
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-7 w-7 sm:h-8 sm:w-8"
                                onClick={onDecrement}
                                disabled={
                                    isUpdating ||
                                    (item.product?.sell_by_weight
                                        ? item.weight_multiplier <= 1
                                        : parseFloat(item.quantity) <= 1)
                                }
                            >
                                <Minus className="h-3 w-3" />
                            </Button>
                            <div className="w-12 sm:w-16 text-center font-medium text-sm sm:text-base">
                                {item.product?.sell_by_weight ? (
                                    <>
                                        <span className="text-xs text-muted-foreground">×</span>
                                        {item.weight_multiplier}
                                    </>
                                ) : (
                                    parseFloat(item.quantity).toFixed(0)
                                )}
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-7 w-7 sm:h-8 sm:w-8"
                                onClick={onIncrement}
                                disabled={isUpdating}
                            >
                                <Plus className="h-3 w-3" />
                            </Button>
                        </div>
                        <div className="text-right ltr:ml-auto rtl:mr-auto sm:ml-0 sm:mr-0">
                            <div className="font-bold text-base sm:text-lg">
                                {formatCurrency(item.subtotal)}
                            </div>
                            <div className="text-[10px] sm:text-xs text-muted-foreground space-y-0.5">
                                {item.product?.sell_by_weight && item.weight_option_value ? (
                                    <>
                                        <div className="ltr">
                                            {parseFloat(item.quantity).toFixed(2)}{" "}
                                            {item.product.weight_option?.unit} ×{" "}
                                            {formatCurrency(item.price)}
                                        </div>
                                        {item.extras_total > 0 && (
                                            <div>
                                                + {t("extras")}:{" "}
                                                {formatCurrency(item.extras_total)} ×{" "}
                                                {item.weight_multiplier}
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <div className="">
                                        <span>
                                            {formatCurrency(item.price + item.extras_total)}{" "}
                                        </span>
                                        × {parseFloat(item.quantity).toFixed(0)}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <Separator className="mt-4" />
        </div>
    );
}
