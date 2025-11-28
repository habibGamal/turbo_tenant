import React from "react";
import { Head, Link } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { CheckCircle, XCircle, Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Order, PageProps } from "@/types";

interface PaymentCallbackProps extends PageProps {
    success: boolean;
    message: string;
    order: Order | null;
}

export default function PaymentCallback({
    success,
    message,
    order,
}: PaymentCallbackProps) {
    const { t } = useTranslation();

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("paymentStatus")} />

            <main className="container mx-auto px-4 py-8 md:py-16">
                <Card className="max-w-2xl mx-auto">
                    <CardContent className="pt-16 pb-16">
                        <div className="flex flex-col items-center gap-6 text-center">
                            {success ? (
                                <>
                                    <div className="w-24 h-24 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                        <CheckCircle className="w-12 h-12 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div className="space-y-2">
                                        <h1 className="text-3xl font-bold text-green-600 dark:text-green-400">
                                            {t("paymentSuccessful")}
                                        </h1>
                                        <p className="text-muted-foreground text-lg">
                                            {message}
                                        </p>
                                    </div>
                                    {order && (
                                        <div className="bg-muted/50 p-4 rounded-lg">
                                            <div className="text-sm text-muted-foreground mb-1">
                                                {t("orderNumber")}
                                            </div>
                                            <div className="text-2xl font-bold">
                                                #{order.order_number}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex flex-col sm:flex-row gap-3 mt-4">
                                        {order && (
                                            <Link
                                                href={route(
                                                    "orders.show",
                                                    order.id
                                                )}
                                            >
                                                <Button size="lg">
                                                    {t("viewOrder")}
                                                </Button>
                                            </Link>
                                        )}
                                        <Link href={route("home")}>
                                            <Button
                                                variant="outline"
                                                size="lg"
                                            >
                                                {t("backToHome")}
                                            </Button>
                                        </Link>
                                    </div>
                                </>
                            ) : message.includes("processing") ? (
                                <>
                                    <div className="w-24 h-24 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <Clock className="w-12 h-12 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div className="space-y-2">
                                        <h1 className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                            {t("paymentProcessing")}
                                        </h1>
                                        <p className="text-muted-foreground text-lg">
                                            {message}
                                        </p>
                                    </div>
                                    {order && (
                                        <div className="bg-muted/50 p-4 rounded-lg">
                                            <div className="text-sm text-muted-foreground mb-1">
                                                {t("orderNumber")}
                                            </div>
                                            <div className="text-2xl font-bold">
                                                #{order.order_number}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex flex-col sm:flex-row gap-3 mt-4">
                                        {order && (
                                            <Link
                                                href={route(
                                                    "orders.show",
                                                    order.id
                                                )}
                                            >
                                                <Button size="lg">
                                                    {t("viewOrder")}
                                                </Button>
                                            </Link>
                                        )}
                                        <Link href={route("orders.index")}>
                                            <Button
                                                variant="outline"
                                                size="lg"
                                            >
                                                {t("viewMyOrders")}
                                            </Button>
                                        </Link>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="w-24 h-24 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                        <XCircle className="w-12 h-12 text-red-600 dark:text-red-400" />
                                    </div>
                                    <div className="space-y-2">
                                        <h1 className="text-3xl font-bold text-red-600 dark:text-red-400">
                                            {t("paymentFailed")}
                                        </h1>
                                        <p className="text-muted-foreground text-lg">
                                            {message}
                                        </p>
                                    </div>
                                    {order && (
                                        <div className="bg-muted/50 p-4 rounded-lg">
                                            <div className="text-sm text-muted-foreground mb-1">
                                                {t("orderNumber")}
                                            </div>
                                            <div className="text-2xl font-bold">
                                                #{order.order_number}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex flex-col sm:flex-row gap-3 mt-4">
                                        <Link href={route("checkout")}>
                                            <Button size="lg">
                                                {t("tryAgain")}
                                            </Button>
                                        </Link>
                                        <Link href={route("home")}>
                                            <Button
                                                variant="outline"
                                                size="lg"
                                            >
                                                {t("backToHome")}
                                            </Button>
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </main>
        </MainLayout>
    );
}
