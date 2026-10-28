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
        <Card>
            <CardHeader>
                <CardTitle>{t("paymentMethod")}</CardTitle>
            </CardHeader>
            <CardContent>
                <RadioGroup
                    value={value}
                    onValueChange={onChange}
                    className="space-y-3"
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    {paymentMethods.map((method) => {
                        const Icon = method.icon;
                        return (
                            <div
                                key={method.value}
                                className="flex items-center gap-3"
                            >
                                <RadioGroupItem
                                    value={method.value}
                                    id={method.value}
                                />
                                <Label
                                    htmlFor={method.value}
                                    className="flex items-center justify-between flex-1 cursor-pointer"
                                >
                                    <div className="flex items-center gap-3">
                                        <Icon className="w-5 h-5 text-muted-foreground" />
                                        <span>{method.label}</span>
                                    </div>
                                    {method.value === "cod" && codFee !== undefined && codFee > 0 && (
                                        <span className="text-xs font-medium text-muted-foreground">
                                            +{formatMoney(codFee)}
                                        </span>
                                    )}
                                </Label>
                            </div>
                        );
                    })}
                </RadioGroup>
            </CardContent>
        </Card>
    );
}
