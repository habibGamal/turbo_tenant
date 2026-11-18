import React, { useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { PhoneInput } from "@/components/ui/phone-input";
import { Separator } from "@/components/ui/separator";
import { ImageWithFallback } from "@/components/ui/image";
import {
    LoaderIcon,
    Package,
    MapPin,
    Store,
    CreditCard,
    Clock,
    CheckCircle,
    XCircle,
    Truck,
    Search,
    Copy,
    Check,
    Phone,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order } from "@/types";
import { getPaymentMethodLabel } from "@/lib/payment";
import { useFormatMoney } from "@/lib/money";
import { formatWeightLabel } from "@/lib/weight";
import { useGuestOrderDetails } from "@/plugins/guest-checkout/api/useGuestOrderTracking";
import type { TrackOrderPayload } from "@/plugins/guest-checkout/types";
import { OrderTimeline } from "@/themes/default/plugins/order-management/components/status";

export interface ThemedGuestOrderTrackingProps {
    initialOrderNumber?: string;
    initialPhone?: string;
    className?: string;
}

export default function ThemedGuestOrderTracking({
    initialOrderNumber,
    initialPhone,
    className = "",
}: ThemedGuestOrderTrackingProps) {
    const { t, i18n } = useTranslation();
    const [searchParams] = useSearchParams();

    const paramOrderNumber = searchParams.get('order_number') ?? '';
    const paramToken = searchParams.get('token') ?? '';
    const paramPhone = searchParams.get('phone') ?? '';

    const defaultOrderNumber = initialOrderNumber ?? paramOrderNumber;
    const defaultToken = paramToken;
    const defaultPhone = initialPhone ?? paramPhone;

    const [orderNumber, setOrderNumber] = useState(defaultOrderNumber);
    const [phone, setPhone] = useState(defaultPhone);
    const [error, setError] = useState<string | null>(null);
    const [isCopied, setIsCopied] = useState(false);

    const [searchPayload, setSearchPayload] = useState<TrackOrderPayload | null>(() => {
        if (defaultOrderNumber && defaultToken) {
            return {
                order_number: defaultOrderNumber.trim(),
                token: defaultToken.trim(),
            };
        }
        if (defaultOrderNumber && defaultPhone) {
            return {
                order_number: defaultOrderNumber.trim(),
                phone: defaultPhone.trim(),
            };
        }
        return null;
    });

    useEffect(() => {
        const nextOrderNumber = initialOrderNumber ?? paramOrderNumber;
        const nextPhone = initialPhone ?? paramPhone;

        if (nextOrderNumber) {
            setOrderNumber(nextOrderNumber);
        }
        if (nextPhone) {
            setPhone(nextPhone);
        }

        const trimmedOrder = nextOrderNumber.trim();
        const trimmedToken = paramToken.trim();
        const trimmedPhone = nextPhone.trim();

        if (trimmedOrder && trimmedToken) {
            setError(null);
            setSearchPayload({
                order_number: trimmedOrder,
                token: trimmedToken,
            });
        } else if (trimmedOrder && trimmedPhone) {
            setError(null);
            setSearchPayload({
                order_number: trimmedOrder,
                phone: trimmedPhone,
            });
        }
    }, [initialOrderNumber, initialPhone, paramOrderNumber, paramToken, paramPhone]);

    const {
        data: order,
        isLoading: isQueryLoading,
        error: queryError,
        refetch,
    } = useGuestOrderDetails(searchPayload, {
        refetchInterval: (query) => {
            const currentOrder = query.state.data;
            if (
                !currentOrder ||
                ["completed", "delivered", "cancelled"].includes(
                    currentOrder.status
                )
            ) {
                return false;
            }
            return 20000;
        },
    });

    const isSearching = isQueryLoading && !order;

    const displayError =
        error ||
        (queryError
            ? (queryError as any)?.response?.data?.message || (queryError as any)?.response?.data?.error || t("failedToTrackOrder")
            : null);

    const formatMoney = useFormatMoney();

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat(i18n.language, {
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        }).format(date);
    };

    const copyOrderNumber = async () => {
        if (!order?.order_number) return;

        try {
            await navigator.clipboard.writeText(order.order_number);
            setIsCopied(true);
            setTimeout(() => setIsCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmedOrder = orderNumber.trim();
        const trimmedPhone = phone.trim();

        if (!trimmedOrder || !trimmedPhone) {
            const msg = t("pleaseProvideOrderNumberAndPhone");
            setError(msg);
            toast.error(msg);
            return;
        }

        setError(null);
        if (
            searchPayload?.order_number === trimmedOrder &&
            searchPayload?.phone === trimmedPhone
        ) {
            refetch();
        } else {
            setSearchPayload({
                order_number: trimmedOrder,
                phone: trimmedPhone,
            });
        }
    };

    return (
        <main className={`min-h-[calc(100vh-16rem)] py-12 px-4 sm:px-6 lg:px-8 bg-background ${className}`}>
            <div className="max-w-4xl mx-auto space-y-8">
                {/* Header */}
                <div className="text-center space-y-3">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 text-primary mb-2">
                        <Package className="w-8 h-8" />
                    </div>
                    <h1 className="text-3xl sm:text-4xl font-bold tracking-tight">
                        {t("trackOrderTitle", "Track Your Order")}
                    </h1>
                    <p className="text-muted-foreground max-w-md mx-auto text-sm sm:text-base">
                        {t("trackOrderSubtitle", "Enter your order number and phone number to track your order status in real time.")}
                    </p>
                </div>

                {/* Search Form Card */}
                <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Search className="w-5 h-5" />
                        {t("findYourOrder")}
                    </CardTitle>
                    <CardDescription>
                        {t("findYourOrderDescription")}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form data-testid="track-order-form" onSubmit={handleSearch} className="space-y-4">
                        {/* Order Number */}
                        <div className="space-y-2">
                            <Label htmlFor="order_number">
                                {t("orderNumber")}
                            </Label>
                            <Input
                                id="order_number"
                                data-testid="track-order-input"
                                type="text"
                                value={orderNumber}
                                onChange={(e) => setOrderNumber(e.target.value)}
                                placeholder={t("orderNumberPlaceholder")}
                                disabled={isSearching}
                            />
                        </div>

                        {/* Phone Number */}
                        <div className="space-y-2">
                            <Label htmlFor="phone">
                                {t("phoneNumber")}
                            </Label>
                            <PhoneInput
                                id="phone"
                                data-testid="track-phone-input"
                                value={phone}
                                onChange={(value) => setPhone(value || "")}
                                defaultCountry="EG"
                                countries={['EG', 'SA', 'AE']}
                                placeholder={t("phoneNumberPlaceholder")}
                                disabled={isSearching}
                            />
                        </div>

                        {displayError && (
                            <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                                <p className="text-sm text-destructive">{displayError}</p>
                            </div>
                        )}

                        <Button
                            data-testid="track-submit-button"
                            type="submit"
                            className="w-full shadow-none"
                            size="lg"
                            disabled={isSearching}
                        >
                            {isSearching ? (
                                <>
                                    <LoaderIcon className="me-2 h-4 w-4 animate-spin" />
                                    {t("searching")}
                                </>
                            ) : (
                                <>
                                    <Search className="me-2 h-4 w-4" />
                                    {t("trackOrder")}
                                </>
                            )}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            {/* Order Details Display */}
            {order && (
                <div className="space-y-6">
                    {/* Order Header Card */}
                    <Card>
                        <CardHeader>
                            <div>
                                <CardTitle className="text-lg mb-3">
                                    {t("orderDetails")}
                                </CardTitle>
                                <div className="flex items-center gap-2 p-3 bg-background dark:bg-card rounded-lg border border-border/90">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs text-muted-foreground mb-1">
                                            {t("orderNumber")}
                                        </p>
                                        <p className="text-xl font-mono font-bold text-primary truncate" dir="ltr">
                                            {order.order_number}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={copyOrderNumber}
                                        className="shrink-0 border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card"
                                    >
                                        {isCopied ? (
                                            <Check className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid md:grid-cols-2 gap-4">
                                {/* Order Date */}
                                <div className="flex items-start gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                        <Clock className="w-5 h-5 text-primary" />
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {t("orderDate")}
                                        </div>
                                        <div className="font-medium">
                                            {formatDate(order.created_at)}
                                        </div>
                                    </div>
                                </div>

                                {/* Payment Method */}
                                <div className="flex items-start gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                        <CreditCard className="w-5 h-5 text-primary" />
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {t("paymentMethod")}
                                        </div>
                                        <div className="font-medium">
                                            {t(getPaymentMethodLabel(order.payment_method))}
                                        </div>
                                    </div>
                                </div>

                                {/* Branch */}
                                {order.branch && (
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                                <Store className="w-5 h-5 text-primary" />
                                            </div>
                                            <div>
                                                <div className="text-sm text-muted-foreground">
                                                    {t("branch")}
                                                </div>
                                                <div className="font-medium" dir="auto">
                                                    {order.branch.name}
                                                </div>
                                            </div>
                                        </div>
                                        {((order.branch as any)?.phone || (order.branch as any)?.phone_number || (order.branch as any)?.contact_phone) && (
                                            <Button variant="outline" size="sm" asChild className="gap-2 shrink-0 border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card">
                                                <a href={`tel:${(order.branch as any)?.phone || (order.branch as any)?.phone_number || (order.branch as any)?.contact_phone}`}>
                                                    <Phone className="w-4 h-4" />
                                                    {t("callBranch", "اتصل بالفرع")}
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                )}

                                {/* Guest Info */}
                                {order.guest_user && (
                                    <div className="flex items-start gap-3">
                                        <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                            <MapPin className="w-5 h-5 text-primary" />
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground">
                                                {t("customerInfo")}
                                            </div>
                                            <div className="font-medium">
                                                {order.guest_user.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground w-fit" dir="ltr">
                                                {order.guest_user.full_phone}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Delivery Address */}
                            {order.type === "web_delivery" && order.guest_user && (
                                <>
                                    <Separator />
                                    <div className="flex items-start gap-3">
                                        <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                            <MapPin className="w-5 h-5 text-primary" />
                                        </div>
                                        <div className="flex-1">
                                            <div className="text-sm text-muted-foreground mb-1">
                                                {t("deliveryAddress")}
                                            </div>
                                            <div className="font-medium">
                                                {[
                                                    order.guest_user.street,
                                                    order.guest_user.building,
                                                    order.guest_user.floor && `${t("floor")} ${order.guest_user.floor}`,
                                                    order.guest_user.apartment && `${t("apt")} ${order.guest_user.apartment}`,
                                                ]
                                                    .filter(Boolean)
                                                    .join(", ")}
                                            </div>
                                            {order.guest_user.area && (
                                                <div className="text-sm text-muted-foreground mt-1">
                                                    {i18n.language === 'ar' ? order.guest_user.area.name_ar : order.guest_user.area.name}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}

                            {order.note && (
                                <>
                                    <Separator />
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">
                                            {t("orderNotes")}
                                        </div>
                                        <div className="text-sm bg-background dark:bg-card border border-border/90 rounded-lg p-3">
                                            {order.note}
                                        </div>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Order Status & Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("orderStatus")}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <OrderTimeline
                                status={order.status}
                                orderType={order.type}
                            />
                        </CardContent>
                    </Card>

                    {/* Order Items */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("orderItems")}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {order.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex gap-4 pb-4 border-b border-border/50 last:border-0 last:pb-0"
                                    >
                                        <div className="w-16 h-16 rounded-lg border border-border/80 bg-background dark:bg-card relative overflow-hidden shrink-0">
                                            <ImageWithFallback
                                                src={item.product?.image}
                                                alt={item.product_name}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex-1 min-w-0">
                                                    <div className="font-medium truncate">
                                                        {item.product_name}
                                                    </div>
                                                    {item.variant_name && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {item.variant_name}
                                                        </div>
                                                    )}
                                                    {item.product?.sell_by_weight && item.weight_option_value && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {formatWeightLabel(
                                                                item.weight_option_value,
                                                                item.product.weight_option?.unit || "kg",
                                                                i18n.language === "ar"
                                                            )}
                                                            {item.weight_multiplier > 1 && ` × ${item.weight_multiplier}`}
                                                        </div>
                                                    )}
                                                    {!item.product?.sell_by_weight && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {t("qty")}: {parseFloat(item.quantity).toFixed(0)}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="font-semibold text-primary shrink-0">
                                                    {formatMoney(item.total)}
                                                </div>
                                            </div>

                                            {item.extras.length > 0 && (
                                                <div className="mt-2 text-sm text-muted-foreground space-y-1">
                                                    {item.extras.map((extra) => (
                                                        <div
                                                            key={extra.id}
                                                            className="flex items-center gap-1"
                                                        >
                                                            <span>+ {extra.extra_name}</span>
                                                            {extra.quantity > 1 && <span>× {extra.quantity}</span>}
                                                            <span className="text-xs">
                                                                ({formatMoney(extra.extra_price * extra.quantity)})
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Order Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t("orderSummary")}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">{t("subtotal")}</span>
                                <span className="font-medium">{formatMoney(order.sub_total)}</span>
                            </div>

                            {order.discount > 0 && (
                                <div className="flex items-center justify-between text-sm text-emerald-600 dark:text-emerald-400">
                                    <span>{t("discount")}</span>
                                    <span className="font-medium">- {formatMoney(order.discount)}</span>
                                </div>
                            )}

                            {order.tax > 0 && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">{t("tax")}</span>
                                    <span className="font-medium">{formatMoney(order.tax)}</span>
                                </div>
                            )}

                            {order.service > 0 && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">{t("serviceFee")}</span>
                                    <span className="font-medium">{formatMoney(order.service)}</span>
                                </div>
                            )}

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">{t("deliveryFee")}</span>
                                <span className="font-medium">
                                    {order.delivery_fee > 0 ? formatMoney(order.delivery_fee) : t("free")}
                                </span>
                            </div>

                            <Separator />

                            <div className="flex items-center justify-between">
                                <span className="font-semibold text-lg">{t("total")}</span>
                                <span className="font-bold text-2xl text-primary">
                                    {formatMoney(order.total)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}
            </div>
        </main>
    );
}
