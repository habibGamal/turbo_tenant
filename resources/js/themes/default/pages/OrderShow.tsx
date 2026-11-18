import React from "react";
import { Link } from "react-router-dom";
import { Head } from "@/components/Head";
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
    Phone,
} from "lucide-react";
import { OrderShowSkeleton } from "@/components/ui/page-skeletons";
import { useTranslation } from "react-i18next";
import { useAuth } from "@/core/context/AuthContext";
import { Order, PageProps } from "@/types";
import { getPaymentMethodLabel } from "@/lib/payment";
import { useOrderDetails } from "@/plugins/order-management";
import { useFormatMoney } from "@/lib/money";
import { formatPlaceLabel, formatSavedAddressLines } from "@/lib/locationFormatters";
import { formatWeightLabel } from "@/lib/weight";
import {
    OrderStatusBadge,
    OrderTimeline,
    getPaymentStatusColor,
} from "@/themes/default/plugins/order-management/components/status";

interface OrderShowPageProps extends Partial<PageProps> {
    order?: Order;
    orderId?: number | string;
}

export default function OrderShow({ order: initialOrder, orderId, auth }: OrderShowPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const BackArrowIcon = isRTL ? ArrowRight : ArrowLeft;
    const formatMoney = useFormatMoney();
    const { isAuthenticated, isLoading: isAuthLoading } = useAuth();

    const resolvedId = orderId || initialOrder?.id || (typeof window !== 'undefined' ? window.location.pathname.split('/').filter(Boolean).pop() : undefined);
    const { data: fetchedOrder, isLoading, error } = useOrderDetails(resolvedId, {
        enabled: Boolean(resolvedId) && (Boolean(initialOrder) || (!isAuthLoading && isAuthenticated)),
        refetchInterval: (query) => {
            const current = query.state.data;
            if (!current || ["completed", "delivered", "cancelled"].includes(current.status)) {
                return false;
            }
            return 20000;
        },
    });

    const order = fetchedOrder || initialOrder;

    if ((isAuthLoading && !order) || (isLoading && !order)) {
        return (
            <MainLayout>
                <OrderShowSkeleton />
            </MainLayout>
        );
    }

    if (!isAuthLoading && !isAuthenticated && !initialOrder) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-24 text-center max-w-md space-y-4">
                    <h2 className="text-xl font-semibold">{t("ordersSignInTitle", "Sign in to view your orders")}</h2>
                    <p className="text-sm text-muted-foreground">{t("ordersSignInDescription")}</p>
                    <div className="flex flex-col sm:flex-row justify-center gap-3">
                        <Button asChild><Link to={`/login?redirect=${encodeURIComponent(window.location.pathname)}`}>{t("login")}</Link></Button>
                        <Button asChild variant="outline"><Link to="/track-order">{t("trackOrder")}</Link></Button>
                    </div>
                </div>
            </MainLayout>
        );
    }

    if ((error || !order) && !isLoading) {
        return (
            <MainLayout>
                <div className="container mx-auto px-4 py-24 text-center">
                    <XCircle className="w-12 h-12 text-destructive mx-auto mb-4" />
                    <h2 className="text-xl font-semibold mb-2">{t("orderNotFound", "Order not found")}</h2>
                    <Link to="/orders">
                        <Button variant="outline">{t("backToOrders", "Back to My Orders")}</Button>
                    </Link>
                </div>
            </MainLayout>
        );
    }

    if (!order) return null;

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
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

    return (
        <MainLayout>
            <Head title={`${t("order")} #${order.order_number}`} />

            <div className="container mx-auto max-w-7xl px-4 py-8 md:py-12" data-testid="order-details-container">
                <div className="mb-8 flex items-start gap-3 sm:items-center sm:gap-4">
                    <Button variant="outline" size="icon" asChild className="shrink-0 rounded-full shadow-none" aria-label={t("backToOrders", "Back to My Orders")}>
                        <Link to="/orders"><BackArrowIcon className="h-5 w-5" /></Link>
                    </Button>
                    <div>
                        <h1 className="mb-1 text-2xl font-bold tracking-tight md:text-4xl" data-testid="order-number-display">
                            {t("order")} #{order.order_number}
                        </h1>
                        <p className="text-muted-foreground">
                            {formatDate(order.created_at)}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3 lg:gap-8">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center justify-between gap-3">
                                    <span>{t("orderStatus")}</span>
                                    <OrderStatusBadge status={order.status} />
                                </CardTitle>
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
                                <CardTitle>
                                    {t("orderItems")} ({order.items.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4" data-testid="order-items-list">
                                {order.items.map((item, index) => (
                                    <div key={item.id}>
                                        {index > 0 && <Separator />}
                                        <div className="flex items-start justify-between gap-3 py-3">
                                            <div className="min-w-0 flex-1">
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
                                                        {getText(item.variant_name, item.variant_name_ar)}
                                                    </div>
                                                )}
                                                {item.product?.sell_by_weight && item.weight_option_value && (
                                                    <div className="text-sm text-muted-foreground">
                                                        <span>
                                                            {formatWeightLabel(
                                                                item.weight_option_value,
                                                                item.product?.weight_option?.unit || "kg",
                                                                isRTL
                                                            )}{" "}
                                                        </span>
                                                        <span>
                                                            × {formatMoney(item.unit_price)}
                                                        </span>
                                                    </div>
                                                )}
                                                {item.type === 'package' && item.components && item.components.length > 0 && (
                                                    <div className="text-sm text-muted-foreground mt-1 space-y-0.5 ps-2 border-s border-border" data-testid="order-item-components">
                                                        {item.components.map((component, componentIndex) => {
                                                            const adjustment = Number(component.unit_price_adjustment) || 0;

                                                            return (
                                                                <div key={componentIndex} className="space-y-0.5">
                                                                    <div>
                                                                        {component.group_name && (
                                                                            <span className="font-medium">{getText(component.group_name, component.group_name_ar ?? undefined)}: </span>
                                                                        )}
                                                                        {Number(component.quantity) || 1}× {getText(component.product_name, component.product_name_ar ?? undefined)}
                                                                        {component.variant_name ? ` (${getText(component.variant_name, component.variant_name_ar ?? undefined)})` : ''}
                                                                        {adjustment > 0 ? ` +${formatMoney(adjustment)}` : ''}
                                                                    </div>
                                                                    {component.extras && component.extras.length > 0 && (
                                                                        <div className="ps-2 space-y-0.5 text-xs text-muted-foreground">
                                                                            {component.extras.map((extra: any, extraIndex: number) => {
                                                                                const extraName = getText(extra.name || extra.extra_name, extra.name_ar || extra.extra_name_ar);
                                                                                const extraPrice = Number(extra.price || extra.extra_price) || 0;
                                                                                const extraQty = Number(extra.quantity) || 1;
                                                                                return (
                                                                                    <div key={extraIndex} className="flex items-center gap-1">
                                                                                        <span>
                                                                                            + {extraName}
                                                                                            {extraQty > 1 && <span className="ms-1">×{extraQty}</span>}
                                                                                        </span>
                                                                                        {extraPrice > 0 && (
                                                                                            <span className="ms-auto font-medium">+{formatMoney(extraPrice * extraQty)}</span>
                                                                                        )}
                                                                                    </div>
                                                                                );
                                                                            })}
                                                                        </div>
                                                                    )}
                                                                    {component.notes && (
                                                                        <div className="ps-2 text-xs text-muted-foreground italic">
                                                                            "{component.notes}"
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                {item.type === 'package' && item.notes && (
                                                    <div className="text-sm text-muted-foreground italic mt-1">"{item.notes}"</div>
                                                )}
                                                {item.extras.length > 0 && (
                                                    <div className="text-sm text-muted-foreground mt-1 space-y-1">
                                                        {item.extras.map((extra) => (
                                                            <div key={extra.id} className="flex items-center gap-2">
                                                                <span className="text-xs">+</span>
                                                                <span>
                                                                    {getText(extra.extra_name, extra.extra_name_ar)}
                                                                    {extra.quantity > 1 && (
                                                                        <span className="ms-1">
                                                                            × {extra.quantity}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="ms-auto">
                                                                    {formatMoney(extra.extra_price * extra.quantity)}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="shrink-0 font-bold">
                                                {formatMoney(item.total)}
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
                    <div className="space-y-6 lg:col-span-1">
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
                                        {formatMoney(order.sub_total)}
                                    </span>
                                </div>
                                {order.discount > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t("discount")}
                                        </span>
                                        <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                            -
                                            {formatMoney(order.discount)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t("tax")}
                                    </span>
                                    <span className="font-medium">
                                        {formatMoney(order.tax)}
                                    </span>
                                </div>
                                {order.service > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t("codFees")}
                                        </span>
                                        <span className="font-medium">
                                            {formatMoney(order.service)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t("deliveryFee")}
                                    </span>
                                    <span className="font-medium">
                                        {order.delivery_fee > 0
                                            ? formatMoney(
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
                                    <span className="font-bold text-2xl text-primary" data-testid="order-total-display">
                                        {formatMoney(order.total)}
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
                                        variant="outline"
                                        className={getPaymentStatusColor(
                                            order.payment_status
                                        )}
                                    >
                                        {t(order.payment_status)}
                                    </Badge>
                                </div>
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
                                    <div className="font-medium flex items-center justify-between gap-2 mt-1">
                                        <span dir="auto">{order.branch?.name || t("unknown")}</span>
                                        {((order.branch as any)?.phone || (order.branch as any)?.phone_number || (order.branch as any)?.contact_phone) && (
                                            <Button variant="outline" size="sm" asChild className="gap-2 shrink-0 border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card">
                                                <a href={`tel:${(order.branch as any)?.phone || (order.branch as any)?.phone_number || (order.branch as any)?.contact_phone}`}>
                                                    <Phone className="w-4 h-4" />
                                                    {t("callBranch", "اتصل بالفرع")}
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                {order.type === "web_delivery" &&
                                    order.address && (
                                        <div>
                                            <div className="text-sm text-muted-foreground">
                                                {t("deliveryAddress")}
                                            </div>
                                            <div className="font-medium text-sm space-y-0.5">
                                                <div>
                                                    {formatSavedAddressLines(
                                                        order.address,
                                                        { floor: t("floor"), apt: t("apt") },
                                                        isRTL
                                                    )}
                                                </div>
                                                {order.address?.area && (
                                                    <div>
                                                        {formatPlaceLabel(
                                                            order.address.area.governorate?.name,
                                                            order.address.area.governorate?.name_ar,
                                                            order.address.area.name,
                                                            order.address.area.name_ar,
                                                            i18n.language,
                                                            ", "
                                                        )}
                                                    </div>
                                                )}
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
