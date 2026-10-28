import React from "react";
import { Head } from "@/components/Head";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { useTranslation } from "react-i18next";
import { PluginSlot } from "@/core/slots/PluginSlot";
import { ThemedCart } from "@/themes/default/plugins/cart-engine";

export default function Cart() {
    const { t } = useTranslation();

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("cart")} />
            <PluginSlot
                name="cart.page"
                pluginId="cart-engine"
                fallback={<ThemedCart />}
            />
        </MainLayout>
    );
}
