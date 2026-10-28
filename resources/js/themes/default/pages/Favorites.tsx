import React from "react";
import { Head } from "@/components/Head";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { useTranslation } from "react-i18next";
import { PluginSlot } from "@/core/slots/PluginSlot";
import ThemedCustomerFavorites from "@/themes/default/plugins/customer-favorites/ThemedCustomerFavorites";

export default function Favorites() {
    const { t } = useTranslation();

    return (
        <MainLayout>
            <Head title={t("myFavorites", "My Favorites")} />
            <PluginSlot
                name="favorites.page"
                pluginId="customer-favorites"
                fallback={<ThemedCustomerFavorites />}
            />
        </MainLayout>
    );
}
