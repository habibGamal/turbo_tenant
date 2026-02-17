import React from "react";
import { Head, Link } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    Clock,
    CheckCircle,
    XCircle,
    Package,
    Truck,
    MapPin,
    Store,
    CreditCard,
    FileText,
    ArrowLeft,
    ArrowRight,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order, PageProps } from "@/types";
import { getPaymentMethodLabel } from "@/lib/payment";

interface OrderShowPageProps extends PageProps {
    order: Order;
}

export default function OrderShow({ order, auth }: OrderShowPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const BackArrowIcon = isRTL ? ArrowRight : ArrowLeft;

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

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

    const orderTimeline = [
        { status: "pending", label: t("orderPlaced") },
        { status: "processing", label: t("preparing") },
        {
            status: "out_for_delivery",
            label:
                order.type === "web_delivery"
                    ? t("outForDelivery")
                    : t("readyForPickup"),
        },
        {
            status: "completed",
            label:
                order.type === "web_delivery" ? t("delivered") : t("completed"),
        },
    ];

    const currentStatusIndex = orderTimeline.findIndex(
        (item) => item.status === order.status
    );

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={`${t("order")} #${order.order_number}`} />

            <div className="container mx-auto px-4 py-8 md:py-12">
                <div className="flex items-center gap-4 mb-8">
                    <Link href={route("orders.index")}>
                        <Button variant="ghost" size="icon">
                            <BackArrowIcon className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-3xl md:text-4xl font-bold mb-2">
                            {t("order")} #{order.order_number}
                        </h1>
                        <p className="text-muted-foreground">
                            {formatDate(order.created_at)}
                        </p>
                    </div>
                </div>

                <div className="grid lg:grid-cols-3 gap-8">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span>{t("orderStatus")}</span>
                                    <Badge
                                        className={`flex items-center gap-1 ${getStatusColor(
                                            order.status
                                        )}`}
                                    >
                                        {getStatusIcon(order.status)}
                                        {t(order.status)}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {order.status !== "cancelled" && (
                                    <div className="relative">
                                        <div className="flex justify-between mb-4">
                                            {orderTimeline.map(
                                                (item, index) => {
                                                    const isCompleted =
                                                        index <=
                                                        currentStatusIndex;
                                                    const isCurrent =
                                                        index ===
                                                        currentStatusIndex;

                                                    return (
                                                        <div
                                                            key={
                                                                item.status
                                                            }
                                                            className="flex flex-col items-center flex-1"
                                                        >
                                                            <div
                                                                className={`w-10 h-10 rounded-full flex items-center justify-center mb-2 transition-colors ${isCompleted
                                                                    ? "bg-primary text-primary-foreground"
                                                                    : "bg-muted text-muted-foreground"
                                                                    } ${isCurrent
                                                                        ? "ring-4 ring-primary/20"
                                                                        : ""
                                                                    }`}
                                                            >
                                                                {isCompleted ? (
                                                                    <CheckCircle className="w-5 h-5" />
                                                                ) : (
                                                                    <Clock className="w-5 h-5" />
                                                                )}
                                                            </div>
                                                            <div className="text-xs text-center font-medium">
                                                                {item.label}
                                                            </div>
                                                        </div>
                                                    );
                                                }
                                            )}
                                        </div>
                                        <div className="absolute top-5 left-0 right-0 h-0.5 bg-muted -z-10">
                                            <div
                                                className="h-full bg-primary transition-all"
                                                style={{
                                                    width: `${(currentStatusIndex /
                                                        (orderTimeline.length -
                                                            1)) *
                                                        100
                                                        }%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}

                                {order.status === "cancelled" && (
                                    <div className="flex items-center justify-center py-8 text-destructive">
                                        <XCircle className="w-12 h-12 ltr:mr-4 rtl:ml-4" />
                                        <div>
                                            <div className="text-lg font-semibold">
                                                {t("orderCancelled")}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {t(
                                                    "orderWasCancelledMessage"
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Order Items */}
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t("orderItems")} ({order.items.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {order.items.map((item, index) => (
                                    <div key={item.id}>
                                        {index > 0 && <Separator />}
                                        <div className="flex justify-between items-start py-3">
                                            <div className="flex-1">
                                                <div className="font-semibold">
                                                    {item.product?.sell_by_weight ? (
                                                        <>
                                                            {item.weight_multiplier} x {item.product ? getText(item.product.name, item.product.name_ar) : getText(item.product_name, item.product_name_ar)}
                                                        </>
                                                    ) : (
                                                        <>
                                                            {parseFloat(item.quantity).toFixed(0)} x {item.product ? getText(item.product.name, item.product.name_ar) : getText(item.product_name, item.product_name_ar)}
                                                        </>
                                                    )}
                                                </div>
                                                {item.variant_name && (
                                                    <div className="text-sm text-muted-foreground">
                                                        {item.variant_name}
                                                    </div>
                                                )}
                                                {item.product?.sell_by_weight && item.weight_option_value && (
                                                    <div className="text-sm text-muted-foreground">
                                                        <span> {item.weight_option_value.value}
                                                        </span>
                                                        <span>
                                                            × {formatCurrency(item.unit_price)}
                                                        </span>
                                                    </div>
                                                )}
                                                {item.extras.length > 0 && (
                                                    <div className="text-sm text-muted-foreground mt-1 space-y-1">
                                                        {item.extras.map((extra) => (
                                                            <div key={extra.id} className="flex items-center gap-2">
                                                                <span className="text-xs">+</span>
                                                                <span>
                                                                    {extra.extra_name}
                                                                    {extra.quantity > 1 && (
                                                                        <span className="ltr:ml-1 rtl:mr-1">
                                                                            × {extra.quantity}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="ltr:ml-auto rtl:mr-auto">
                                                                    {formatCurrency(extra.extra_price * extra.quantity)}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="font-bold ltr:ml-4 rtl:mr-4">
                                                {formatCurrency(item.total)}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Order Notes */}
                        {order.note && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="w-5 h-5" />
                                        {t("orderNotes")}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground">
                                        {order.note}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Order Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("orderSummary")}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t("subtotal")}
                                    </span>
                                    <span className="font-medium">
                                        {formatCurrency(order.sub_total)}
                                    </span>
                                </div>
                                {order.discount > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t("discount")}
                                        </span>
                                        <span className="font-medium text-green-600 dark:text-green-400">
                                            -
                                            {formatCurrency(order.discount)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t("tax")}
                                    </span>
                                    <span className="font-medium">
                                        {formatCurrency(order.tax)}
                                    </span>
                                </div>
                                {order.service > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t("codFees")}
                                        </span>
                                        <span className="font-medium">
                                            {formatCurrency(order.service)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t("deliveryFee")}
                                    </span>
                                    <span className="font-medium">
                                        {order.delivery_fee > 0
                                            ? formatCurrency(
                                                order.delivery_fee
                                            )
                                            : t("free")}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex justify-between">
                                    <span className="font-semibold text-lg">
                                        {t("total")}
                                    </span>
                                    <span className="font-bold text-2xl text-primary">
                                        {formatCurrency(order.total)}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Payment Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="w-5 h-5" />
                                    {t("paymentInformation")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {t("paymentMethod")}
                                    </div>
                                    <div className="font-medium">
                                        {t(getPaymentMethodLabel(order.payment_method))}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {t("paymentStatus")}
                                    </div>
                                    <Badge
                                        className={getPaymentStatusColor(
                                            order.payment_status
                                        )}
                                    >
                                        {t(order.payment_status)}
                                    </Badge>
                                </div>
                                {order.transaction_id && (
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {t("transactionId")}
                                        </div>
                                        <div className="font-mono text-sm">
                                            {order.transaction_id}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Delivery Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {order.type === "web_delivery" ? (
                                        <MapPin className="w-5 h-5" />
                                    ) : (
                                        <Store className="w-5 h-5" />
                                    )}
                                    {order.type === "web_delivery"
                                        ? t("deliveryInformation")
                                        : t("pickupInformation")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {t("branch")}
                                    </div>
                                    <div className="font-medium">
                                        {order.branch?.name || t("unknown")}
                                    </div>
                                </div>
                                {order.type === "web_delivery" &&
                                    order.address && (
                                        <div>
                                            <div className="text-sm text-muted-foreground">
                                                {t("deliveryAddress")}
                                            </div>
                                            <div className="font-medium text-sm">
                                                {order.address?.street},{" "}
                                                {order.address?.building}
                                                <br />
                                                {t("floor")}{" "}
                                                {order.address?.floor},{" "}
                                                {t("apt")}{" "}
                                                {order.address?.apartment}
                                                <br />
                                                {order.address?.area?.name}
                                            </div>
                                        </div>
                                    )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
