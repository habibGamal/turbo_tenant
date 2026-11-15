import React, { useState } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ImageWithFallback } from "@/components/ui/image";
import { Separator } from "@/components/ui/separator";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Minus, Plus, X, ChevronDown, ChevronUp, Edit2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { CartItem as CartItemType } from "@/plugins/cart-engine/types";
import { useFormatMoney } from "@/lib/money";
import { usePackage } from "@/plugins/combo-packages/api/usePackage";
import ThemedPackageConfigurator from "@/themes/default/plugins/combo-packages/components/ThemedPackageConfigurator";

interface CartItemProps {
    item: CartItemType | any;
    isUpdating: boolean;
    onRemove: () => void;
    onIncrement: () => void;
    onDecrement: () => void;
    onWeightChange: (value: string) => void;
    formatCurrency?: (amount: number | string | null | undefined) => string;
}

function PackageEditDialog({
    item,
    open,
    onOpenChange,
}: {
    item: any;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const { data: pkg, isLoading } = usePackage(item.reference_id);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl p-0 overflow-hidden sm:max-h-[85vh]">
                {isLoading || !pkg ? (
                    <div className="p-8 text-center text-sm text-muted-foreground animate-pulse">
                        {t("loading", "Loading...")}
                    </div>
                ) : (
                    <ThemedPackageConfigurator
                        packageData={pkg}
                        cartItem={item}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
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

    const [isComponentsOpen, setIsComponentsOpen] = useState(true);
    const [isEditOpen, setIsEditOpen] = useState(false);

    const isPackage = item.type === "package";

    const getText = (text: string, textAr?: string | null) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const hasOptions =
        !isPackage &&
        ((item.extras && item.extras.length > 0) ||
        item.variant ||
        item.weight_option_value);

    return (
        <div data-testid="cart-item" className="flex gap-4 py-4 first:pt-0 last:pb-0">
            {/* Product / Package Image */}
            {isPackage ? (
                <div className="relative aspect-square h-24 w-24 min-w-[6rem] overflow-hidden rounded-lg bg-muted">
                    <ImageWithFallback
                        src={item.product?.image}
                        alt={item.product?.name || ""}
                        className="h-full w-full object-cover"
                    />
                </div>
            ) : (
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
            )}

            {/* Product / Package Details */}
            <div className="flex flex-1 flex-col justify-between">
                <div>
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            {isPackage ? (
                                <div className="flex items-center gap-2">
                                    <span data-testid="cart-item-title" className="font-semibold line-clamp-1">
                                        {getText(item.product?.name || "", item.product?.name_ar)}
                                    </span>
                                    <Badge variant="secondary" className="text-[10px] py-0 px-1.5 shrink-0">
                                        {t("combo", "Combo")}
                                    </Badge>
                                </div>
                            ) : (
                                <Link
                                    to={`/products/${item.product.id}`}
                                    data-testid="cart-item-title"
                                    className="font-semibold hover:underline line-clamp-1"
                                >
                                    {getText(item.product.name, item.product.name_ar)}
                                </Link>
                            )}
                            <p data-testid="cart-item-price" className="text-sm font-medium text-primary">
                                {format(item.price)}
                            </p>
                        </div>
                        <div className="flex items-center gap-1">
                            {isPackage && (
                                <Button
                                    data-testid="cart-item-edit"
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    onClick={() => setIsEditOpen(true)}
                                    disabled={isUpdating}
                                    title={t("edit", "Edit")}
                                >
                                    <Edit2 className="h-4 w-4" />
                                    <span className="sr-only">{t("edit", "Edit")}</span>
                                </Button>
                            )}
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
                    </div>

                    {/* Package Components List & Notes */}
                    {isPackage && (
                        <div className="mt-1 space-y-1">
                            {item.components && item.components.length > 0 && (
                                <div className="text-xs">
                                    <button
                                        type="button"
                                        onClick={() => setIsComponentsOpen(!isComponentsOpen)}
                                        className="text-muted-foreground hover:text-foreground flex items-center gap-1 font-medium text-[11px]"
                                    >
                                        {isComponentsOpen ? (
                                            <ChevronUp className="w-3 h-3" />
                                        ) : (
                                            <ChevronDown className="w-3 h-3" />
                                        )}
                                        <span>
                                            {t("packageComponents", "Package contents")} ({item.components.length})
                                        </span>
                                    </button>

                                    {isComponentsOpen && (
                                        <div data-testid="cart-item-components" className="space-y-0.5 mt-1 ps-2 border-s-2 border-border/60">
                                            {item.components.map((c: any, idx: number) => {
                                                const groupName = getText(c.group_name, c.group_name_ar);
                                                const prodName = getText(c.product_name, c.product_name_ar);
                                                const varName = c.variant_name ? getText(c.variant_name, c.variant_name_ar) : null;
                                                const adj = Number(c.unit_price_adjustment) || 0;
                                                const qty = Number(c.quantity) || 1;

                                                return (
                                                    <div key={idx} className="space-y-0.5">
                                                        <div
                                                            data-testid="cart-item-component"
                                                            className="text-[11px] text-muted-foreground flex items-baseline justify-between gap-1"
                                                        >
                                                            <span>
                                                                <span className="font-medium text-foreground/80">{groupName}:</span> {qty}× {prodName}
                                                                {varName ? ` (${varName})` : ''}
                                                            </span>
                                                            {adj > 0 && (
                                                                <span className="shrink-0 font-medium text-foreground/70">
                                                                    +{format(adj)}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {c.extras && c.extras.length > 0 && (
                                                            <div className="ps-2 space-y-0.5 text-[10px] text-muted-foreground">
                                                                {c.extras.map((extra: any, extraIdx: number) => {
                                                                    const extraName = getText(extra.name || extra.extra_name, extra.name_ar || extra.extra_name_ar);
                                                                    const extraQty = Number(extra.quantity) || 1;
                                                                    const extraPrice = Number(extra.price || extra.extra_price) || 0;
                                                                    return (
                                                                        <div key={extraIdx} className="flex items-center justify-between gap-1">
                                                                            <span>
                                                                                + {extraName}
                                                                                {extraQty > 1 && <span className="ms-1">×{extraQty}</span>}
                                                                            </span>
                                                                            {extraPrice > 0 && (
                                                                                <span className="shrink-0">+{format(extraPrice * extraQty)}</span>
                                                                            )}
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        )}
                                                        {c.notes && (
                                                            <div className="ps-2 text-[10px] text-muted-foreground italic">
                                                                "{c.notes}"
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )}

                            {item.notes && (
                                <div data-testid="cart-item-notes" className="text-[11px] text-muted-foreground italic bg-muted/40 px-2 py-1 rounded">
                                    "{item.notes}"
                                </div>
                            )}
                        </div>
                    )}

                    {/* Options/Variants/Extras for Standard Products */}
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
                            {item.product?.sell_by_weight &&
                                item.product?.weight_option && (
                                    <div className="flex items-center gap-2 mt-2">
                                        <Label
                                            htmlFor={`weight-${item.id}`}
                                            className="text-xs"
                                        >
                                            {t("weight")}:
                                        </Label>
                                        <Select
                                            value={
                                                item.weight_option_value?.id
                                                    ? String(item.weight_option_value.id)
                                                    : ""
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
                                                    (val: any) => (
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
                                    {item.extras.map((extra: any, idx: number) => (
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
                                (!isPackage && item.product?.sell_by_weight
                                    ? item.weight_multiplier <= 1
                                    : item.quantity <= 1)
                            }
                        >
                            <Minus className="h-3 w-3" />
                        </Button>
                        <span data-testid="cart-item-qty" className="w-8 text-center text-sm font-medium">
                            {!isPackage && item.product?.sell_by_weight
                                ? item.weight_multiplier
                                : item.quantity}
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

            {/* Edit Dialog for Packages */}
            {isPackage && isEditOpen && (
                <PackageEditDialog
                    item={item}
                    open={isEditOpen}
                    onOpenChange={setIsEditOpen}
                />
            )}
        </div>
    );
}
