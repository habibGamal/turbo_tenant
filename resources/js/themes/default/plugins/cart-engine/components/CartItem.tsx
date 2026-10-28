import React from "react";
import { Link } from "react-router-dom";
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
import { useFormatMoney } from "@/lib/money";

interface CartItemProps {
    item: CartItemType;
    isUpdating: boolean;
    onRemove: () => void;
    onIncrement: () => void;
    onDecrement: () => void;
    onWeightChange: (value: string) => void;
    formatCurrency?: (amount: number | string | null | undefined) => string;
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
    const { t, i18n } = useTranslation();
    const fallbackFormatMoney = useFormatMoney();
    const format = formatCurrency ?? fallbackFormatMoney;

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const hasOptions =
        (item.extras && item.extras.length > 0) ||
        item.variant ||
        item.weight_option_value;

    return (
        <div data-testid="cart-item" className="flex gap-4 py-4 first:pt-0 last:pb-0">
            {/* Product Image */}
            <Link
                to={`/products/${item.product.id}`}
                className="relative aspect-square h-24 w-24 min-w-[6rem] overflow-hidden rounded-lg bg-muted"
            >
                <ImageWithFallback
                    src={item.product.image}
                    alt={item.product.name}
                    className="h-full w-full object-cover"
                />
            </Link>

            {/* Product Details */}
            <div className="flex flex-1 flex-col justify-between">
                <div>
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <Link
                                to={`/products/${item.product.id}`}
                                data-testid="cart-item-title"
                                className="font-semibold hover:underline line-clamp-1"
                            >
                                {getText(item.product.name, item.product.name_ar)}
                            </Link>
                            <p data-testid="cart-item-price" className="text-sm font-medium text-primary">
                                {format(item.price)}
                            </p>
                        </div>
                        <Button
                            data-testid="cart-item-remove"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground hover:text-destructive"
                            onClick={onRemove}
                            disabled={isUpdating}
                        >
                            <X className="h-4 w-4" />
                            <span className="sr-only">{t("remove")}</span>
                        </Button>
                    </div>

                    {/* Options/Variants/Extras */}
                    {hasOptions && (
                        <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                            {item.variant && (
                                <div data-testid="cart-item-variant">
                                    <span className="font-medium">
                                        {t("variant")}:
                                    </span>{" "}
                                    {getText(item.variant.name, item.variant.name_ar)}
                                </div>
                            )}

                            {/* Weight Options */}
                            {item.product.sell_by_weight &&
                                item.product.weight_option && (
                                    <div className="flex items-center gap-2 mt-2">
                                        <Label
                                            htmlFor={`weight-${item.id}`}
                                            className="text-xs"
                                        >
                                            {t("weight")}:
                                        </Label>
                                        <Select
                                            value={
                                                item.weight_option_value_id?.toString() ||
                                                ""
                                            }
                                            onValueChange={onWeightChange}
                                            disabled={isUpdating}
                                        >
                                            <SelectTrigger
                                                id={`weight-${item.id}`}
                                                data-testid="cart-item-weight-select"
                                                className="h-7 text-xs w-[120px]"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        "selectWeight"
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {item.product.weight_option.values?.map(
                                                    (val) => (
                                                        <SelectItem
                                                            key={val.id}
                                                            value={val.id.toString()}
                                                            className="text-xs"
                                                        >
                                                            {val.label} (
                                                            {val.value}{" "}
                                                            {
                                                                item.product
                                                                    .weight_option
                                                                    ?.unit
                                                            }
                                                            )
                                                        </SelectItem>
                                                    )
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                            {item.extras && item.extras.length > 0 && (
                                <div data-testid="cart-item-extras">
                                    <span className="font-medium">
                                        {t("extras")}:
                                    </span>{" "}
                                    {item.extras.map((extra, idx) => (
                                        <span key={extra.id}>
                                            {extra.quantity > 1
                                                ? `${extra.quantity}x `
                                                : ""}
                                            {getText(extra.name, extra.name_ar)}
                                            {idx < item.extras.length - 1
                                                ? ", "
                                                : ""}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Quantity Controls and Total */}
                <div className="flex items-center justify-between mt-2">
                    <div className="flex items-center gap-1 border border-border rounded-md p-1">
                        <Button
                            data-testid="cart-qty-decrement"
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6"
                            onClick={onDecrement}
                            disabled={
                                isUpdating ||
                                (item.product.sell_by_weight
                                    ? item.weight_multiplier <= 1
                                    : parseFloat(item.quantity) <= 1)
                            }
                        >
                            <Minus className="h-3 w-3" />
                        </Button>
                        <span data-testid="cart-item-qty" className="w-8 text-center text-sm font-medium">
                            {item.product.sell_by_weight
                                ? item.weight_multiplier
                                : parseFloat(item.quantity)}
                        </span>
                        <Button
                            data-testid="cart-qty-increment"
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6"
                            onClick={onIncrement}
                            disabled={isUpdating}
                        >
                            <Plus className="h-3 w-3" />
                        </Button>
                    </div>
                    <div className="text-end">
                        <span className="text-sm font-semibold">
                            {format((item as any).total ?? item.subtotal)}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
