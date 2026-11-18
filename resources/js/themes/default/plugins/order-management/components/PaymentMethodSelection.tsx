import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { CreditCard, DollarSign, LucideIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useFormatMoney } from "@/lib/money";

interface PaymentMethod {
    value: string;
    label: string;
    icon: LucideIcon;
}

interface PaymentMethodSelectionProps {
    value: string;
    onChange: (value: string) => void;
    onlinePaymentsEnabled: boolean;
    codFee?: number;
}

export default function PaymentMethodSelection({
    value,
    onChange,
    onlinePaymentsEnabled,
    codFee,
}: PaymentMethodSelectionProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const formatMoney = useFormatMoney();

    const paymentMethods: PaymentMethod[] = [
        { value: "cod", label: t("cashOnDelivery"), icon: DollarSign },
        { value: "card", label: t("creditCard"), icon: CreditCard },
    ].filter((method) => {
        if (method.value === "card" && !onlinePaymentsEnabled) {
            return false;
        }
        return true;
    });

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("paymentMethod")}</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <RadioGroup
                    data-testid="payment-method-selector"
                    value={value}
                    onValueChange={onChange}
                    className="space-y-3"
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    {paymentMethods.map((method) => {
                        const Icon = method.icon;
                        const isSelected = value === method.value;
                        return (
                            <Label
                                key={method.value}
                                htmlFor={method.value}
                                className={`flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-xl border p-3.5 transition-colors ${
                                    isSelected
                                        ? "border-primary bg-background font-medium dark:bg-card"
                                        : "border-border/90 bg-background/85 hover:border-primary/40 hover:bg-background dark:bg-card/75 dark:hover:bg-card"
                                }`}
                            >
                                <div className="flex items-center gap-3">
                                    <RadioGroupItem
                                        value={method.value}
                                        id={method.value}
                                        data-testid={method.value === "cod" ? "payment-cod-radio" : "payment-online-radio"}
                                    />
                                    <Icon className="w-5 h-5 text-muted-foreground" />
                                    <span>{method.label}</span>
                                </div>
                                {method.value === "cod" && codFee !== undefined && codFee > 0 && (
                                    <span className="text-xs font-medium text-muted-foreground">
                                        <span dir="ltr">+{formatMoney(codFee)}</span>
                                    </span>
                                )}
                            </Label>
                        );
                    })}
                </RadioGroup>
            </CardContent>
        </Card>
    );
}
