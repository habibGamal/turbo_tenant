import React from "react";
import { useTranslation } from "react-i18next";
import { usePackages } from "@/plugins/combo-packages/api/usePackages";
import ThemedPackageCard from "./components/ThemedPackageCard";

export default function ThemedPackagesSection() {
    const { t } = useTranslation();
    const { data: packages, isLoading } = usePackages();

    const sortedPackages = React.useMemo(() => {
        if (!packages) return [];
        return [...packages].sort((a, b) => {
            if (a.is_featured !== b.is_featured) {
                return a.is_featured ? -1 : 1;
            }
            return (a.sort_order ?? 0) - (b.sort_order ?? 0);
        });
    }, [packages]);

    if (isLoading || !packages || packages.length === 0) {
        return null;
    }

    return (
        <section data-testid="combos-section" className="py-12 bg-background border-b border-border/50">
            <div className="container mx-auto px-4">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h2 className="text-3xl font-bold tracking-tight">
                            {t("comboPackages", "Combo Packages & Meals")}
                        </h2>
                        <p className="text-muted-foreground mt-1">
                            {t("comboPackagesSubtitle", "Special meal packages curated for you")}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {sortedPackages.map((pkg) => (
                        <ThemedPackageCard key={pkg.id} packageItem={pkg} />
                    ))}
                </div>
            </div>
        </section>
    );
}
