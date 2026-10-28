import React from "react";
import { Head } from "@inertiajs/react";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { useTranslation } from "react-i18next";
import { PageProps } from "@/types";
import { PluginSlot } from "@/core/slots/PluginSlot";
import DefaultOrdersList from "@/plugins/order-management/components/DefaultOrdersList";

export default function MyOrders(props: Partial<PageProps>) {
    const { t } = useTranslation();

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("myOrders")} />

            <div className="container mx-auto px-4 py-8 md:py-12">
                <div className="mb-8">
                    <h1 className="text-3xl md:text-4xl font-bold mb-2">
                        {t("myOrders")}
                    </h1>
                    <p className="text-muted-foreground">
                        {t("trackYourOrders")}
                    </p>
                </div>

                <PluginSlot
                    name="customer.orders"
                    pluginId="order-management"
                    fallback={<DefaultOrdersList />}
                />
            </div>
        </MainLayout>
    );
}
