import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "react-i18next";

interface OrderNotesSectionProps {
    value: string;
    onChange: (value: string) => void;
}

export default function OrderNotesSection({ value, onChange }: OrderNotesSectionProps) {
    const { t } = useTranslation();

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("orderNotes")}</CardTitle>
                <CardDescription>
                    {t("addOrderNotesOptional")}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <Textarea
                    placeholder={t("orderNotesPlaceholder")}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    rows={4}
                />
            </CardContent>
        </Card>
    );
}
