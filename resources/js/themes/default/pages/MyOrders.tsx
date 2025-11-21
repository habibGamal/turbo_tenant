import React from "react";
import { Head, Link } from "@inertiajs/react";
import Navigation from "@/themes/default/components/Navigation";
import Footer from "@/themes/default/components/Footer";
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
    Clock,
    CheckCircle,
    XCircle,
    Package,
    Truck,
    ShoppingBag,
    ArrowRight,
    ArrowLeft,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order, PageProps } from "@/types";

interface MyOrdersPageProps extends PageProps {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function MyOrders({ orders, auth }: MyOrdersPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;

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
            case "confirmed":
            case "preparing":
                return <Package className="w-5 h-5" />;
            case "ready":
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
            case "confirmed":
            case "preparing":
                return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
            case "ready":
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

    return (
        <>
            <Head title={t("myOrders")} />
            <div className="min-h-screen bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
                <Navigation />

                <main className="container mx-auto px-4 py-8 md:py-12">
                    <div className="mb-8">
                        <h1 className="text-3xl md:text-4xl font-bold mb-2">
                            {t("myOrders")}
                        </h1>
                        <p className="text-muted-foreground">
                            {t("trackYourOrders")}
                        </p>
                    </div>

                    {orders.data.length === 0 ? (
                        <Card className="py-16">
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
                                <Link href={route("home")}>
                                    <Button size="lg" className="gap-2">
                                        {t("startShopping")}
                                        <ArrowIcon className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {orders.data.map((order) => (
                                <Card
                                    key={order.id}
                                    className="hover:shadow-lg transition-shadow"
                                >
                                    <CardHeader>
                                        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                            <div className="space-y-2">
                                                <CardTitle className="flex items-center gap-2">
                                                    {t("order")} #
                                                    {order.order_number}
                                                </CardTitle>
                                                <CardDescription>
                                                    {formatDate(
                                                        order.created_at
                                                    )}
                                                </CardDescription>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Badge
                                                    className={`flex items-center gap-1 ${getStatusColor(
                                                        order.status
                                                    )}`}
                                                >
                                                    {getStatusIcon(order.status)}
                                                    {t(order.status)}
                                                </Badge>
                                                <Badge
                                                    variant="outline"
                                                    className={getPaymentStatusColor(
                                                        order.payment_status
                                                    )}
                                                >
                                                    {t(order.payment_status)}
                                                </Badge>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <Separator />
                                    <CardContent className="pt-6">
                                        <div className="grid md:grid-cols-2 gap-6">
                                            {/* Order Details */}
                                            <div className="space-y-3">
                                                <div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {t("branch")}
                                                    </div>
                                                    <div className="font-medium">
                                                        {order.branch?.name ||
                                                            t("unknown")}
                                                    </div>
                                                </div>
                                                {order.address && (
                                                    <div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {t(
                                                                "deliveryAddress"
                                                            )}
                                                        </div>
                                                        <div className="font-medium text-sm">
                                                            {
                                                                order.address
                                                                    .street
                                                            }
                                                            ,{" "}
                                                            {
                                                                order.address
                                                                    .building
                                                            }
                                                            ,{" "}
                                                            {order.address.area
                                                                ?.name || ""}
                                                        </div>
                                                    </div>
                                                )}
                                                <div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {t("paymentMethod")}
                                                    </div>
                                                    <div className="font-medium">
                                                        {t(order.payment_method)}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Order Items */}
                                            <div>
                                                <div className="text-sm text-muted-foreground mb-2">
                                                    {t("items")} (
                                                    {order.items.length})
                                                </div>
                                                <div className="space-y-2">
                                                    {order.items
                                                        .slice(0, 3)
                                                        .map((item) => (
                                                            <div
                                                                key={item.id}
                                                                className="flex justify-between text-sm"
                                                            >
                                                                <span>
                                                                    {parseFloat(
                                                                        item.quantity
                                                                    ).toFixed(
                                                                        0
                                                                    )}
                                                                    x{" "}
                                                                    {
                                                                        item.product_name
                                                                    }
                                                                    {item.variant_name && (
                                                                        <span className="text-muted-foreground">
                                                                            {" "}
                                                                            (
                                                                            {
                                                                                item.variant_name
                                                                            }
                                                                            )
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="font-medium">
                                                                    {formatCurrency(
                                                                        item.total
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    {order.items.length > 3 && (
                                                        <div className="text-sm text-muted-foreground">
                                                            +
                                                            {order.items.length -
                                                                3}{" "}
                                                            {t("moreItems")}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <Separator className="my-4" />

                                        <div className="flex items-center justify-between">
                                            <div>
                                                <div className="text-sm text-muted-foreground">
                                                    {t("total")}
                                                </div>
                                                <div className="text-2xl font-bold text-primary">
                                                    {formatCurrency(
                                                        order.total
                                                    )}
                                                </div>
                                            </div>
                                            <Link
                                                href={route(
                                                    "orders.show",
                                                    order.id
                                                )}
                                            >
                                                <Button variant="outline">
                                                    {t("viewDetails")}
                                                    <ArrowIcon className="ltr:ml-2 rtl:mr-2 h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            {/* Pagination */}
                            {orders.last_page > 1 && (
                                <div className="flex justify-center gap-2 mt-8">
                                    {Array.from(
                                        { length: orders.last_page },
                                        (_, i) => i + 1
                                    ).map((page) => (
                                        <Link
                                            key={page}
                                            href={route("orders.index", {
                                                page,
                                            })}
                                        >
                                            <Button
                                                variant={
                                                    page ===
                                                    orders.current_page
                                                        ? "default"
                                                        : "outline"
                                                }
                                                size="sm"
                                            >
                                                {page}
                                            </Button>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </main>

                <Footer />
            </div>
        </>
    );
}
