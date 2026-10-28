import React from "react";
import { Card, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Utensils } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ComboPackage } from "@/plugins/combo-packages/types";

interface ThemedPackageCardProps {
    packageItem: ComboPackage;
}

export default function ThemedPackageCard({ packageItem }: ThemedPackageCardProps) {
    const { t, i18n } = useTranslation();
    const isAr = i18n.language === 'ar';
    const name = (isAr && packageItem.name_ar) ? packageItem.name_ar : packageItem.name;

    return (
        <Card data-testid="combo-package-card" className="overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col justify-between group">
            <div className="relative aspect-video w-full overflow-hidden bg-muted">
                {packageItem.image ? (
                    <img
                        src={packageItem.image}
                        alt={name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                        <Utensils className="h-12 w-12 opacity-30" />
                    </div>
                )}
                <Badge className="absolute top-3 right-3 shadow-md">
                    {t("combo", "Combo")}
                </Badge>
            </div>

            <CardContent className="p-5 flex-1 flex flex-col justify-between">
                <div>
                    <h3 data-testid="combo-package-title" className="font-bold text-lg mb-1 group-hover:text-primary transition-colors">
                        {name}
                    </h3>
                    {packageItem.description && (
                        <p className="text-sm text-muted-foreground line-clamp-2 mb-3">
                            {packageItem.description}
                        </p>
                    )}
                </div>

                <div className="mt-4 pt-3 border-t flex items-baseline justify-between">
                    <div>
                        <span className="text-xs text-muted-foreground">{t("price", "Price")}</span>
                        <div className="text-xl font-bold text-primary">
                            {packageItem.price} {t("currency", "EGP")}
                        </div>
                    </div>
                </div>
            </CardContent>

            <CardFooter className="p-5 pt-0">
                <Button data-testid="combo-add-to-cart" className="w-full shadow-sm font-medium">
                    {t("selectPackage", "Select Package")}
                </Button>
            </CardFooter>
        </Card>
    );
}
