import React, { useState } from "react";
import { Link } from "react-router-dom";
import { Card, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Utensils, Sparkles, Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ComboPackage } from "@/plugins/combo-packages/types";
import { useFormatMoney } from "@/lib/money";
import {
    getLocalizedName,
    getLocalizedDescription,
    getLocalizedBadge,
    savingsLabelData,
    endsSoon,
    summarizeContents,
    formatAvailability,
    orderTypeLabel,
    availabilityBadge,
} from "@/plugins/combo-packages/lib/packageDisplay";
import ThemedPackageConfigurator from "./ThemedPackageConfigurator";

interface ThemedPackageCardProps {
    packageItem: ComboPackage;
}

export default function ThemedPackageCard({ packageItem }: ThemedPackageCardProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();
    const [isConfiguratorOpen, setIsConfiguratorOpen] = useState(false);

    const name = getLocalizedName(packageItem, i18n.language);
    const description = getLocalizedDescription(packageItem, i18n.language);
    const badgeText = getLocalizedBadge(packageItem, i18n.language) || t("combo", "Combo");
    const savings = savingsLabelData(packageItem);
    const isEndingSoon = endsSoon(packageItem);
    const contents = summarizeContents(packageItem, i18n.language);
    const isAvailable = packageItem.available_now !== false;
    const availLabel = formatAvailability(packageItem, i18n.language);
    const orderType = orderTypeLabel(packageItem, i18n.language);
    const badge = availabilityBadge(packageItem, undefined, i18n.language);

    return (
        <Card
            data-testid="combo-package-card"
            className="overflow-hidden transition-colors duration-200 flex flex-col justify-between group hover:border-primary/40"
        >
            <div className="relative aspect-video w-full overflow-hidden bg-background dark:bg-card border-b border-border/60">
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

                {packageItem.is_featured && (
                    <div className="absolute top-3 start-3">
                        <Badge
                            variant="secondary"
                            className="shadow-none bg-amber-500 text-amber-950 font-semibold flex items-center gap-1"
                        >
                            <Sparkles className="h-3 w-3" />
                            {t("featured", "Featured")}
                        </Badge>
                    </div>
                )}

                <div className="absolute top-3 end-3 flex flex-col gap-1.5 items-end">
                    <Badge className="shadow-none">
                        {badgeText}
                    </Badge>
                    {isEndingSoon && (
                        <Badge variant="destructive" className="shadow-none flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {t("endsSoon", "Ends soon")}
                        </Badge>
                    )}
                </div>
            </div>

            <CardContent className="p-5 flex-1 flex flex-col justify-between">
                <div>
                    <Link to={`/packages/${packageItem.id}`}>
                        <h3
                            data-testid="combo-package-title"
                            className="font-bold text-lg mb-1 hover:text-primary transition-colors cursor-pointer"
                        >
                            {name}
                        </h3>
                    </Link>
                    {description && (
                        <p className="text-sm text-muted-foreground line-clamp-2 mb-2">
                            {description}
                        </p>
                    )}

                    {/* Availability and Dayparts */}
                    {(availLabel || (!isAvailable && badge.kind === 'outside_hours')) && (
                        <div
                            data-testid="combo-package-availability"
                            className="flex items-center gap-1.5 text-xs text-muted-foreground mb-2"
                        >
                            <Clock className="w-3.5 h-3.5 shrink-0" />
                            <span>
                                {!isAvailable && badge.kind === 'outside_hours'
                                    ? (availLabel ? `${availLabel} • ${badge.text}` : badge.text)
                                    : availLabel}
                            </span>
                        </div>
                    )}

                    {/* Order Type and Stock Badges */}
                    {(orderType || badge.kind === 'sold_out' || badge.kind === 'few_left') && (
                        <div className="flex flex-wrap items-center gap-1.5 mb-3">
                            {orderType && (
                                <Badge
                                    variant="secondary"
                                    data-testid="combo-package-order-type"
                                    className="text-xs font-medium"
                                >
                                    {orderType}
                                </Badge>
                            )}
                            {badge.kind === 'sold_out' && (
                                <Badge
                                    variant="destructive"
                                    data-testid="combo-package-stock"
                                    className="text-xs font-medium"
                                >
                                    {badge.text}
                                </Badge>
                            )}
                            {badge.kind === 'few_left' && (
                                <Badge
                                    variant="outline"
                                    data-testid="combo-package-stock"
                                    className="text-xs font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20"
                                >
                                    {badge.text}
                                </Badge>
                            )}
                        </div>
                    )}

                    {contents.length > 0 && (
                        <div data-testid="combo-package-contents" className="space-y-1 mb-4">
                            {contents.slice(0, 3).map((line, idx) => (
                                <div key={idx} className="text-xs text-muted-foreground flex items-center gap-1.5">
                                    <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary/60 shrink-0" />
                                    <span className="truncate">{line}</span>
                                </div>
                            ))}
                            {contents.length > 3 && (
                                <div className="text-xs text-muted-foreground font-medium ps-3">
                                    {t("andMore", "and more")}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="mt-4 pt-3 border-t flex items-baseline justify-between gap-2">
                    <div>
                        <span className="text-xs text-muted-foreground block">{t("price", "Price")}</span>
                        <div className="flex items-baseline gap-2 flex-wrap">
                            <span data-testid="combo-package-price" className="text-xl font-bold text-primary">
                                {formatMoney(packageItem.price)}
                            </span>
                            {savings && (
                                <span
                                    data-testid="combo-package-original-price"
                                    className="text-sm text-muted-foreground line-through"
                                >
                                    {formatMoney(savings.original)}
                                </span>
                            )}
                        </div>
                    </div>
                    {savings && (
                        <span
                            data-testid="combo-package-savings"
                            className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"
                        >
                            {t("saveAmount", "Save {{amount}}", { amount: formatMoney(savings.savings) })}
                        </span>
                    )}
                </div>
            </CardContent>

            <CardFooter className="p-5 pt-0 flex flex-col gap-2">
                {!isAvailable && (
                    <p className="text-xs text-destructive font-medium text-center">
                        {badge.text || t("packageUnavailable", "This combo package is currently unavailable.")}
                    </p>
                )}

                <Dialog open={isConfiguratorOpen && isAvailable} onOpenChange={(open) => isAvailable && setIsConfiguratorOpen(open)}>
                    <DialogTrigger asChild>
                        <Button
                            data-testid="combo-add-to-cart"
                            disabled={!isAvailable}
                            title={!isAvailable ? (badge.text || t("packageUnavailable", "This combo package is currently unavailable.")) : undefined}
                            className="w-full shadow-none font-medium"
                        >
                            {t("selectPackage", "Select Package")}
                        </Button>
                    </DialogTrigger>
                    {isAvailable && (
                        <DialogContent className="max-w-2xl p-0 overflow-hidden sm:max-h-[85vh]">
                            <ThemedPackageConfigurator
                                packageData={packageItem}
                                onClose={() => setIsConfiguratorOpen(false)}
                            />
                        </DialogContent>
                    )}
                </Dialog>
            </CardFooter>
        </Card>
    );
}
