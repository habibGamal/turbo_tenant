import React, { useState, useEffect } from "react";
import { Head, usePage } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
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
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { ImageWithFallback } from "@/components/ui/image";
import {
    Loader2,
    Package,
    MapPin,
    Store,
    CreditCard,
    Clock,
    CheckCircle,
    XCircle,
    Truck,
    FileText,
    Search,
    Copy,
    Check,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order, PageProps } from "@/types";
import { getPaymentMethodLabel } from "@/lib/payment";
import axios from "axios";

export default function Track({ auth, settings }: PageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const { url } = usePage();

    // Get query parameters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlOrderNumber = urlParams.get('order_number') || '';
    const urlPhone = urlParams.get('phone') || '';

    const [orderNumber, setOrderNumber] = useState(urlOrderNumber);
    const [phone, setPhone] = useState(urlPhone);
    const [phoneCountryCode, setPhoneCountryCode] = useState("+20");
    const [isSearching, setIsSearching] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [order, setOrder] = useState<Order | null>(null);
    const [isCopied, setIsCopied] = useState(false);

    const formatCurrency = (amount: number | undefined) => {
        const safeAmount = amount ?? 0;
        return `${safeAmount.toFixed(2)} ${t("currency")}`;
    };

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

    const getStatusIcon = (status: string) => {
        switch (status) {
            case "pending":
                return <Clock className="w-5 h-5" />;
            case "processing":
                return <Package className="w-5 h-5" />;
            case "out_for_delivery":
                return <Truck className="w-5 h-5" />;
            case "delivered":
            case "completed":
                return <CheckCircle className="w-5 h-5" />;
            case "cancelled":
                return <XCircle className="w-5 h-5" />;
            default:
                return <Clock className="w-5 h-5" />;
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case "pending":
                return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400";
            case "processing":
                return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
            case "out_for_delivery":
                return "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400";
            case "delivered":
            case "completed":
                return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400";
            case "cancelled":
                return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400";
            default:
                return "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400";
        }
    };

    const getPaymentStatusColor = (status: string) => {
        switch (status) {
            case "pending":
                return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400";
            case "processing":
                return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
            case "completed":
                return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400";
            case "failed":
                return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400";
            default:
                return "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400";
        }
    };

    const searchOrder = async (orderNum: string, phoneNum: string) => {
        if (!orderNum.trim() || !phoneNum.trim()) {
            setError(t("pleaseProvideOrderNumberAndPhone"));
            return;
        }

        setIsSearching(true);
        setError(null);
        setOrder(null);

        try {
            const response = await axios.post(route("orders.track"), {
                order_number: orderNum.trim(),
                phone: phoneNum,
            });

            if (response.data.success) {
                setOrder(response.data.order);
            } else {
                setError(response.data.error || t("orderNotFound"));
            }
        } catch (err: any) {
            console.error("Order tracking error:", err);
            if (err.response?.data?.error) {
                setError(err.response.data.error);
            } else if (err.response?.data?.errors) {
                const errorMessages = Object.values(err.response.data.errors)
                    .flat()
                    .join(", ");
                setError(errorMessages);
            } else {
                setError(t("failedToTrackOrder"));
            }
        } finally {
            setIsSearching(false);
        }
    };

    // Auto-search on mount if URL params are present
    useEffect(() => {
        if (urlOrderNumber && urlPhone) {
            searchOrder(urlOrderNumber, urlPhone);
        }
    }, []);

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        await searchOrder(orderNumber, phone);
    };

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("trackOrder")} />

            <main className="container mx-auto px-4 py-8 md:py-12">
                <div className="mb-8">
                    <h1 className="text-3xl md:text-4xl font-bold mb-2">
                        {t("trackOrder")}
                    </h1>
                    <p className="text-muted-foreground">
                        {t("trackOrderDescription")}
                    </p>
                </div>

                <div className="max-w-2xl mx-auto space-y-6">
                    {/* Search Form */}
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
                            <form onSubmit={handleSearch} className="space-y-4">
                                {/* Order Number */}
                                <div className="space-y-2">
                                    <Label htmlFor="order_number">
                                        {t("orderNumber")}
                                    </Label>
                                    <Input
                                        id="order_number"
                                        type="text"
                                        value={orderNumber}
                                        onChange={(e) =>
                                            setOrderNumber(e.target.value)
                                        }
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
                                        value={phone}
                                        onChange={(value) => {
                                            setPhone(value || "");
                                        }}
                                        defaultCountry="EG"
                                        countries={['EG', 'SA', 'AE']}
                                        placeholder={t("phoneNumberPlaceholder")}
                                        disabled={isSearching}
                                    />
                                </div>

                                {error && (
                                    <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                                        <p className="text-sm text-destructive">
                                            {error}
                                        </p>
                                    </div>
                                )}

                                <Button
                                    type="submit"
                                    className="w-full"
                                    size="lg"
                                    disabled={isSearching}
                                >
                                    {isSearching ? (
                                        <>
                                            <Loader2 className="ltr:mr-2 rtl:ml-2 h-4 w-4 animate-spin" />
                                            {t("searching")}
                                        </>
                                    ) : (
                                        <>
                                            <Search className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                            {t("trackOrder")}
                                        </>
                                    )}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Order Details */}
                    {order && (
                        <div className="space-y-6">
                            {/* Order Header */}
                            <Card>
                                <CardHeader>
                                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg mb-3">
                                                {t("orderDetails")}
                                            </CardTitle>
                                            <div className="flex items-center gap-2 p-3 bg-primary/10 dark:bg-primary/20 rounded-lg border-2 border-primary/30">
                                                <div className="flex-1">
                                                    <p className="text-xs text-muted-foreground mb-1">
                                                        {t("orderNumber")}
                                                    </p>
                                                    <p className="text-xl font-mono font-bold text-primary">
                                                        {order.order_number}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    onClick={copyOrderNumber}
                                                    className="shrink-0"
                                                >
                                                    {isCopied ? (
                                                        <Check className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <Copy className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Badge
                                                className={`${getStatusColor(
                                                    order.status
                                                )} flex items-center gap-2 justify-center px-3 py-1`}
                                            >
                                                {getStatusIcon(order.status)}
                                                {t(order.status)}
                                            </Badge>
                                            <Badge
                                                className={`${getPaymentStatusColor(
                                                    order.payment_status
                                                )} flex items-center gap-1 justify-center text-xs`}
                                            >
                                                <CreditCard className="w-3 h-3" />
                                                {t(order.payment_status)}
                                            </Badge>
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
                                                    {getPaymentMethodLabel(
                                                        order.payment_method
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Branch */}
                                        {order.branch && (
                                            <div className="flex items-start gap-3">
                                                <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                                    <Store className="w-5 h-5 text-primary" />
                                                </div>
                                                <div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {t("branch")}
                                                    </div>
                                                    <div className="font-medium">
                                                        {order.branch.name}
                                                    </div>
                                                </div>
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
                                                    <div className="text-sm text-muted-foreground">
                                                        {order.guest_user.full_phone}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Delivery Address for guest */}
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
                                                <div className="text-sm bg-muted/50 rounded-lg p-3">
                                                    {order.note}
                                                </div>
                                            </div>
                                        </>
                                    )}
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
                                                {/* Item Image */}
                                                <div className="w-16 h-16 rounded-lg bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden shrink-0">
                                                    <ImageWithFallback
                                                        src={item.product?.image}
                                                        alt={item.product_name}
                                                        className="w-full h-full object-cover"
                                                    />
                                                </div>

                                                {/* Item Details */}
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
                                                                    {parseFloat(item.quantity).toFixed(2)}{" "}
                                                                    {item.product.weight_option?.unit}
                                                                    {item.weight_multiplier > 1 &&
                                                                        ` × ${item.weight_multiplier}`}
                                                                </div>
                                                            )}
                                                            {!item.product?.sell_by_weight && (
                                                                <div className="text-sm text-muted-foreground">
                                                                    {t("qty")}: {parseFloat(item.quantity).toFixed(0)}
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="font-semibold text-primary shrink-0">
                                                            {formatCurrency(item.total)}
                                                        </div>
                                                    </div>

                                                    {/* Extras */}
                                                    {item.extras.length > 0 && (
                                                        <div className="mt-2 text-sm text-muted-foreground space-y-1">
                                                            {item.extras.map((extra) => (
                                                                <div
                                                                    key={extra.id}
                                                                    className="flex items-center gap-1"
                                                                >
                                                                    <span>+ {extra.extra_name}</span>
                                                                    {extra.quantity > 1 && (
                                                                        <span>× {extra.quantity}</span>
                                                                    )}
                                                                    <span className="text-xs">
                                                                        ({formatCurrency(extra.extra_price * extra.quantity)})
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
                                        <span className="text-muted-foreground">
                                            {t("subtotal")}
                                        </span>
                                        <span className="font-medium">
                                            {formatCurrency(order.sub_total)}
                                        </span>
                                    </div>

                                    {order.discount > 0 && (
                                        <div className="flex items-center justify-between text-sm text-green-600 dark:text-green-400">
                                            <span>{t("discount")}</span>
                                            <span className="font-medium">
                                                - {formatCurrency(order.discount)}
                                            </span>
                                        </div>
                                    )}

                                    {order.tax > 0 && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t("tax")}
                                            </span>
                                            <span className="font-medium">
                                                {formatCurrency(order.tax)}
                                            </span>
                                        </div>
                                    )}

                                    {order.service > 0 && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t("serviceFee")}
                                            </span>
                                            <span className="font-medium">
                                                {formatCurrency(order.service)}
                                            </span>
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t("deliveryFee")}
                                        </span>
                                        <span className="font-medium">
                                            {order.delivery_fee > 0
                                                ? formatCurrency(order.delivery_fee)
                                                : t("free")}
                                        </span>
                                    </div>

                                    <Separator />

                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-lg">
                                            {t("total")}
                                        </span>
                                        <span className="font-bold text-2xl text-primary">
                                            {formatCurrency(order.total)}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </main>
        </MainLayout>
    );
}
