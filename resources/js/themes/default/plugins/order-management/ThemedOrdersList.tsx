import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Head } from "@/components/Head";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    XCircle,
    ShoppingBag,
    ArrowRight,
    ArrowLeft,
} from "lucide-react";
import { OrdersListSkeleton } from "@/components/ui/page-skeletons";
import { useTranslation } from "react-i18next";
import { useOrders } from "@/plugins/order-management";
import { useFormatMoney } from "@/lib/money";
import { getPaymentMethodLabel } from "@/lib/payment";
import {
    OrderStatusBadge,
    getPaymentStatusColor,
} from "./components/status";

export interface ThemedOrdersListProps {
    initialPage?: number;
}

export default function ThemedOrdersList({ initialPage = 1 }: ThemedOrdersListProps) {
    const { t, i18n } = useTranslation();

    return (
        <div className="container mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12" dir={i18n.dir()}>
            <Head title={t("myOrders")} />
            <header className="mb-8 border-b border-border pb-6 text-start">
                <h1 className="text-2xl font-bold tracking-tight md:text-4xl">{t("myOrders")}</h1>
            </header>
            <OrdersListContent initialPage={initialPage} />
        </div>
    );
}

function OrdersListContent({ initialPage = 1 }: ThemedOrdersListProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;
    const [page, setPage] = useState<number>(initialPage);

    const [search, setSearch] = useState("");
    const [debouncedSearch, setDebouncedSearch] = useState("");
    const [status, setStatus] = useState("all");
    const [paymentStatus, setPaymentStatus] = useState("all");
    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
        return () => window.clearTimeout(timer);
    }, [search]);

    const hasFilters = Boolean(search.trim()) || status !== "all" || paymentStatus !== "all";
    const resetFilters = () => {
        setSearch("");
        setDebouncedSearch("");
        setStatus("all");
        setPaymentStatus("all");
        setPage(1);
    };
    const { data: paginatedOrders, isLoading, error, refetch } = useOrders({
        page,
        ...(debouncedSearch && { search: debouncedSearch }),
        ...(status !== "all" && { status }),
        ...(paymentStatus !== "all" && { payment_status: paymentStatus }),
    });
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

    const filters = (
        <section aria-label={t("ordersFilters")} className="mb-6 rounded-2xl border bg-card p-4 sm:p-5">
            <div className="grid items-end gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                <div className="space-y-2">
                    <Label htmlFor="orders-search">{t("searchOrderNumber")}</Label>
                    <Input id="orders-search" value={search} maxLength={100} placeholder={t("searchOrderNumberPlaceholder")}
                        onChange={(event) => { setSearch(event.target.value); setPage(1); }} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="orders-status">{t("orderStatusFilter")}</Label>
                    <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1); }}>
                        <SelectTrigger id="orders-status"><SelectValue /></SelectTrigger>
                        <SelectContent><SelectItem value="all">{t("allStatuses")}</SelectItem>
                            {["pending", "processing", "out_for_delivery", "completed", "cancelled"].map(value => <SelectItem key={value} value={value}>{t(value)}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="orders-payment-status">{t("paymentStatusFilter")}</Label>
                    <Select value={paymentStatus} onValueChange={(value) => { setPaymentStatus(value); setPage(1); }}>
                        <SelectTrigger id="orders-payment-status"><SelectValue /></SelectTrigger>
                        <SelectContent><SelectItem value="all">{t("allStatuses")}</SelectItem>
                            {["pending", "processing", "completed", "failed", "refunded"].map(value => <SelectItem key={value} value={value}>{t(value)}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>
                <Button type="button" variant="outline" onClick={resetFilters} disabled={!hasFilters}>{t("clearAllFilters")}</Button>
            </div>
        </section>
    );

    if (isLoading && !paginatedOrders) {
        return <>{filters}<OrdersListSkeleton /></>;
    }

    if (error && !paginatedOrders) {
        return (
            <>{filters}<Card className="py-16 border-destructive/30">
                <CardContent className="flex flex-col items-center justify-center gap-4 text-center">
                    <XCircle className="w-10 h-10 text-destructive" />
                    <p className="text-destructive font-medium">{t("errorLoadingOrders", "Failed to load orders")}</p>
                    <Button variant="outline" onClick={() => refetch()}>
                        {t("tryAgain", "Try Again")}
                    </Button>
                </CardContent>
            </Card></>
        );
    }

    const orders = paginatedOrders?.data || [];

    if (orders.length === 0) {
        if (hasFilters) {
            return <>{filters}<Card className="py-16 text-center" data-testid="no-matching-orders">
                <CardContent className="space-y-4"><p>{t("noMatchingOrders")}</p><Button type="button" variant="outline" onClick={resetFilters}>{t("clearAllFilters")}</Button></CardContent>
            </Card></>;
        }
        return (
            <>{filters}<Card className="py-16" data-testid="empty-orders-message">
                <CardContent className="flex flex-col items-center gap-6">
                    <div className="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center">
                        <ShoppingBag className="w-12 h-12 text-primary" />
                    </div>
                    <div className="text-center space-y-2">
                        <h2 className="text-2xl font-semibold">
                            {t("noOrdersYet")}
                        </h2>
                        <p className="text-muted-foreground">
                            {t("startShoppingToPlaceOrder")}
                        </p>
                    </div>
                    <Link to="/">
                        <Button size="lg" className="gap-2">
                            {t("startShopping")}
                            <ArrowIcon className="h-4 w-4" />
                        </Button>
                    </Link>
                </CardContent>
            </Card></>
        );
    }

    return (
        <>{filters}<div className="space-y-5" data-testid="orders-history-list">
            {orders.map((order) => (
                <Card
                    key={order.id}
                    data-testid="order-history-item"
                    className="overflow-hidden rounded-2xl border-border/80 shadow-sm transition-shadow duration-200 hover:shadow-md"
                >
                    <CardHeader className="bg-muted/20">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-2">
                                <CardTitle className="flex flex-wrap items-center gap-1 text-lg sm:text-xl" data-testid="order-number-display">
                                    {t("order")} #
                                    {order.order_number}
                                </CardTitle>
                                <CardDescription>
                                    {formatDate(order.created_at)}
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <OrderStatusBadge status={order.status} />
                                <Badge
                                    variant="outline"
                                    data-testid="order-payment-status-badge"
                                    className={`rounded-full border-transparent px-3 py-1 ${getPaymentStatusColor(order.payment_status)}`}
                                >
                                    {t(order.payment_status)}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] md:gap-10">
                            {/* Order Details */}
                            <div className="min-w-0 space-y-3">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {t("branch")}
                                    </div>
                                    <div className="font-medium">
                                        {order.branch?.name || t("unknown")}
                                    </div>
                                </div>
                                {order.address && (
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {t("deliveryAddress")}
                                        </div>
                                        <div className="font-medium text-sm">
                                            {order.address.street}, {order.address.building}, {order.address.area?.name || ""}
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        {t("paymentMethod")}
                                    </div>
                                    <div className="font-medium">
                                        {t(getPaymentMethodLabel(order.payment_method))}
                                    </div>
                                </div>
                            </div>

                            {/* Order Items */}
                            <div className="min-w-0">
                                <div className="text-sm text-muted-foreground mb-2">
                                    {t("items")} ({order.items.length})
                                </div>
                                <div className="space-y-2">
                                    {order.items.slice(0, 3).map((item: any) => {
                                        const isPackage = item.type === 'package';

                                        return (
                                            <div
                                                key={item.id}
                                                className="space-y-1 text-sm"
                                            >
                                                <div className="flex justify-between gap-3">
                                                    <span className="flex min-w-0 items-center gap-1.5 flex-wrap">
                                                        {isPackage ? (
                                                            <>
                                                                <Badge variant="secondary" className="text-[10px] py-0 px-1">
                                                                    {t("combo", "Combo")}
                                                                </Badge>
                                                                <span>
                                                                    {parseFloat(item.quantity).toFixed(0)} x {item.product_name}
                                                                </span>
                                                            </>
                                                        ) : item.product?.sell_by_weight ? (
                                                            <>
                                                                {item.weight_multiplier} x {item.product_name}
                                                                {item.weight_option_value && (
                                                                    <span className="text-muted-foreground text-xs block">
                                                                        {item.weight_option_value.label || `${parseFloat(item.quantity).toFixed(2)}`}
                                                                    </span>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <>
                                                                {parseFloat(item.quantity).toFixed(0)} x {item.product_name}
                                                            </>
                                                        )}
                                                        {!isPackage && item.variant_name && (
                                                            <span className="text-muted-foreground">
                                                                {" "}({item.variant_name})
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="shrink-0 font-medium">
                                                        {formatMoney(item.total)}
                                                    </span>
                                                </div>

                                                {/* Package Components */}
                                                {isPackage && item.components && item.components.length > 0 && (
                                                    <div className="text-xs text-muted-foreground ps-2 border-s border-border/60 space-y-0.5">
                                                        {item.components.map((c: any, cIdx: number) => (
                                                            <div key={cIdx} className="space-y-0.5">
                                                                <div>
                                                                    <span className="font-medium">{c.group_name}:</span> {Number(c.quantity)}× {c.product_name}
                                                                    {c.variant_name ? ` (${c.variant_name})` : ''}
                                                                </div>
                                                                {c.extras && c.extras.length > 0 && (
                                                                    <div className="ps-2 space-y-0.5 text-[11px]">
                                                                        {c.extras.map((extra: any, eIdx: number) => {
                                                                            const extraName = extra.name || extra.extra_name;
                                                                            const extraPrice = Number(extra.price || extra.extra_price) || 0;
                                                                            const extraQty = Number(extra.quantity) || 1;
                                                                            return (
                                                                                <div key={eIdx}>
                                                                                    + {extraName}
                                                                                    {extraQty > 1 && <span className="ms-1">×{extraQty}</span>}
                                                                                    {extraPrice > 0 && <span className="ms-1">+{formatMoney(extraPrice * extraQty)}</span>}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                )}
                                                                {c.notes && (
                                                                    <div className="ps-2 text-[11px] italic">
                                                                        "{c.notes}"
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                    {order.items.length > 3 && (
                                        <div className="text-sm text-muted-foreground">
                                            +{order.items.length - 3} {t("moreItems")}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <Separator className="my-4" />

                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {t("total")}
                                </div>
                                <div className="text-2xl font-bold text-primary" data-testid="order-total-display">
                                    {formatMoney(order.total)}
                                </div>
                            </div>
                            <Button variant="outline" asChild data-testid="view-order-details-button">
                                <Link to={`/orders/${order.id}`}>
                                    {t("viewDetails")}
                                    <ArrowIcon className="ms-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ))}

            {/* Pagination */}
            {paginatedOrders && paginatedOrders.meta.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-8">
                    {Array.from(
                        { length: paginatedOrders.meta.last_page },
                        (_, i) => i + 1
                    ).map((p) => (
                        <Button
                            key={p}
                            variant={p === paginatedOrders.meta.current_page ? "default" : "outline"}
                            size="sm"
                            onClick={() => setPage(p)}
                        >
                            {p}
                        </Button>
                    ))}
                </div>
            )}
        </div></>
    );
}
