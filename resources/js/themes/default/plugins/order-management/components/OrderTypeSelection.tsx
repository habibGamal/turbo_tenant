import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { useTranslation } from "react-i18next";

interface OrderTypeSelectionProps {
    value: string;
    onChange: (value: string) => void;
}

export default function OrderTypeSelection({ value, onChange }: OrderTypeSelectionProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("orderType")}</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <RadioGroup
                    value={value}
                    onValueChange={onChange}
                    className="space-y-3"
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <Label
                        htmlFor="delivery"
                        className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors ${
                            value === "web_delivery"
                                ? "border-primary bg-background font-medium dark:bg-card"
                                : "border-border/90 bg-background/85 hover:border-primary/40 hover:bg-background dark:bg-card/75 dark:hover:bg-card"
                        }`}
                    >
                        <RadioGroupItem
                            value="web_delivery"
                            id="delivery"
                            data-testid="order-type-delivery"
                        />
                        <span>{t("delivery")}</span>
                    </Label>
                    <Label
                        htmlFor="takeaway"
                        className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors ${
                            value === "web_takeaway"
                                ? "border-primary bg-background font-medium dark:bg-card"
                                : "border-border/90 bg-background/85 hover:border-primary/40 hover:bg-background dark:bg-card/75 dark:hover:bg-card"
                        }`}
                    >
                        <RadioGroupItem
                            value="web_takeaway"
                            id="takeaway"
                            data-testid="order-type-takeaway"
                        />
                        <span>{t("takeaway")}</span>
                    </Label>
                </RadioGroup>
            </CardContent>
        </Card>
    );
}
