import React from "react";
import { Card, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Utensils } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ComboPackage } from "@/plugins/combo-packages/types";
import { useFormatMoney } from "@/lib/money";

interface ThemedPackageCardProps {
    packageItem: ComboPackage;
}

export default function ThemedPackageCard({ packageItem }: ThemedPackageCardProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();
    const isAr = i18n.language === 'ar';
    const name = (isAr && packageItem.name_ar) ? packageItem.name_ar : packageItem.name;
    const isPackageActive = packageItem.is_active ?? true;

    return (
        <Card
            data-testid="combo-package-card"
            className={`overflow-hidden transition-all duration-300 flex flex-col justify-between group ${
                isPackageActive ? 'hover:shadow-lg' : 'opacity-70 grayscale cursor-not-allowed'
            }`}
        >
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
                <div className="absolute top-3 end-3 flex flex-col gap-1.5 items-end">
                    {!isPackageActive && (
                        <Badge variant="secondary" className="shadow-md bg-muted text-muted-foreground">
                            {t("unavailable", "Unavailable")}
                        </Badge>
                    )}
                    <Badge className="shadow-md">
                        {t("combo", "Combo")}
                    </Badge>
                </div>
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
                            {formatMoney(packageItem.price)}
                        </div>
                    </div>
                </div>
            </CardContent>

            <CardFooter className="p-5 pt-0">
                <Button data-testid="combo-add-to-cart" disabled={!isPackageActive} className="w-full shadow-sm font-medium">
                    {t("selectPackage", "Select Package")}
                </Button>
            </CardFooter>
        </Card>
    );
}
