import React from "react";
import { Head } from "@/components/Head";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { useTranslation } from "react-i18next";
import { PageProps } from "@/types";
import { PluginSlot } from "@/core/slots/PluginSlot";
import ThemedGuestOrderTracking from "@/themes/default/plugins/guest-checkout/ThemedGuestOrderTracking";

export default function Track({ auth, settings }: PageProps) {
    const { t } = useTranslation();

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

                <PluginSlot
                    name="order.tracking"
                    pluginId="guest-checkout"
                    fallback={<ThemedGuestOrderTracking />}
                />
            </main>
        </MainLayout>
    );
}
