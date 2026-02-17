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
        <Card>
            <CardHeader>
                <CardTitle>{t("orderType")}</CardTitle>
            </CardHeader>
            <CardContent>
                <RadioGroup
                    value={value}
                    onValueChange={onChange}
                    className="space-y-3"
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <div className="flex items-center space-x-2">
                        <RadioGroupItem
                            value="web_delivery"
                            id="delivery"
                        />
                        <Label
                            htmlFor="delivery"
                            className="flex-1 cursor-pointer"
                        >
                            {t("delivery")}
                        </Label>
                    </div>
                    <div className="flex items-center space-x-2">
                        <RadioGroupItem
                            value="web_takeaway"
                            id="takeaway"
                        />
                        <Label
                            htmlFor="takeaway"
                            className="flex-1 cursor-pointer"
                        >
                            {t("takeaway")}
                        </Label>
                    </div>
                </RadioGroup>
            </CardContent>
        </Card>
    );
}
